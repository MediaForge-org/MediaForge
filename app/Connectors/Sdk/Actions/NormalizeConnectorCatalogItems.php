<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Catalog\CatalogItemNormalizer;
use App\Connectors\Sdk\Catalog\NormalizationStatus;
use App\Connectors\Sdk\Catalog\NormalizedItem;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Rebuilds the normalized read-model for a connector's captured catalog items
 * (V2 C). It reads stored catalog items, interprets them with the pure
 * CatalogItemNormalizer and upserts one normalization row per item.
 *
 * This is READ-ONLY with respect to media: it creates no media_items/editions/files,
 * touches no file, calls no network, changes nothing on Jellyfin/Audiobookshelf and
 * accepts no match. The only rows it writes are its own read-model rows plus a
 * deduplicated review task and a sanitized audit entry.
 *
 * Bounded: items are streamed in chunks and written with chunked bulk upserts, so a
 * capped 5000-item catalog costs a handful of statements rather than two per item.
 */
final class NormalizeConnectorCatalogItems extends AuditableAction
{
    /** Items loaded (and upserted) per chunk. */
    private const CHUNK = 500;

    /**
     * Columns refreshed when an item is normalized again. Excludes `id` and the
     * conflict target so a rebuilt row keeps its identity.
     */
    private const UPDATE_COLUMNS = [
        'connector_instance_id',
        'connector_library_id',
        'normalized_kind',
        'normalized_title',
        'normalized_sort_title',
        'normalized_original_title',
        'release_year',
        'season_number',
        'episode_number',
        'parent_title',
        'runtime_seconds',
        'confidence',
        'status',
        'issues',
        'normalized_data',
        'normalized_at',
        'updated_at',
    ];

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly CatalogItemNormalizer $normalizer,
        private readonly CreateNormalizationReviewTasks $reviewTasks,
    ) {
        parent::__construct($audit, $db);
    }

    /**
     * Normalize every captured item of one connector, optionally narrowed to a
     * single library. Returns the per-status counts of what was written.
     *
     * @return array<string, int>
     */
    public function execute(ConnectorInstance $instance, string $connectorKey, ?ConnectorLibrary $library = null): array
    {
        $parentTitles = $this->parentTitles($instance);
        $now = Carbon::now();
        $libraryId = $library?->id;

        $counts = ['normalized' => 0, 'clean' => 0, 'warning' => 0, 'needs_review' => 0, 'unsupported' => 0];
        /** @var array<string, int> $issueCounts */
        $issueCounts = [];

        ConnectorCatalogItem::query()
            ->where('connector_instance_id', $instance->id)
            ->when($libraryId !== null, fn ($query) => $query->where('connector_library_id', $libraryId))
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($items) use ($parentTitles, $now, &$counts, &$issueCounts): void {
                $rows = [];

                foreach ($items as $item) {
                    $parentTitle = $item->external_parent_id !== null
                        ? ($parentTitles[$item->external_parent_id] ?? null)
                        : null;

                    $normalized = $this->normalizer->normalize($item, $parentTitle);

                    $counts['normalized']++;
                    $counts[$normalized->status->value]++;

                    foreach ($normalized->issueCodes() as $code) {
                        $issueCounts[$code] = ($issueCounts[$code] ?? 0) + 1;
                    }

                    $rows[] = $this->row($item, $normalized, $now);
                }

                if ($rows !== []) {
                    ConnectorCatalogItemNormalization::query()
                        ->upsert($rows, ['connector_catalog_item_id'], self::UPDATE_COLUMNS);
                }
            });

        $this->recordRun($instance, $connectorKey, $library, $counts, $issueCounts);
        $this->reviewTasks->execute($instance, $connectorKey, $issueCounts, $counts['needs_review']);

        return $counts;
    }

    /**
     * external_id → title for this connector, so an episode can name its parent
     * without a per-item query and without ever calling the remote.
     *
     * @return array<string, string>
     */
    private function parentTitles(ConnectorInstance $instance): array
    {
        /** @var array<string, string> $titles */
        $titles = [];

        ConnectorCatalogItem::query()
            ->where('connector_instance_id', $instance->id)
            ->whereIn('media_kind', ['series', 'season'])
            ->toBase()
            ->select(['external_id', 'title'])
            ->orderBy('external_id')
            ->chunk(self::CHUNK, function ($rows) use (&$titles): void {
                foreach ($rows as $row) {
                    if (is_string($row->external_id) && is_string($row->title)) {
                        $titles[$row->external_id] = $row->title;
                    }
                }
            });

        return $titles;
    }

    /** @return array<string, mixed> */
    private function row(ConnectorCatalogItem $item, NormalizedItem $normalized, Carbon $now): array
    {
        return [
            'id' => (string) Str::ulid(),
            'connector_catalog_item_id' => $item->id,
            'connector_instance_id' => $item->connector_instance_id,
            'connector_library_id' => $item->connector_library_id,
            'normalized_kind' => $normalized->kind->value,
            'normalized_title' => $normalized->title,
            'normalized_sort_title' => $normalized->sortTitle,
            'normalized_original_title' => $normalized->originalTitle,
            'release_year' => $normalized->releaseYear,
            'season_number' => $normalized->seasonNumber,
            'episode_number' => $normalized->episodeNumber,
            'parent_title' => $normalized->parentTitle,
            'runtime_seconds' => $normalized->runtimeSeconds,
            'confidence' => $normalized->confidence,
            'status' => $normalized->status->value,
            'issues' => json_encode($normalized->issueCodes()),
            // Sanitized + minimal on purpose: counts and derived hints only, never a
            // raw API payload, never a secret, never a local path.
            'normalized_data' => json_encode([
                'source_kind' => $item->media_kind,
                'issue_count' => count($normalized->issues),
                'has_parent' => $normalized->parentTitle !== null,
            ]),
            'normalized_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * One sanitized audit entry per rebuild. Counts and issue codes only.
     *
     * @param  array<string, int>  $counts
     * @param  array<string, int>  $issueCounts
     */
    private function recordRun(ConnectorInstance $instance, string $connectorKey, ?ConnectorLibrary $library, array $counts, array $issueCounts): void
    {
        $this->transact(
            $instance,
            new AuditChange('catalog.normalization_rebuilt', [
                'normalized' => $counts['normalized'],
                'needs_review' => $counts['needs_review'],
            ], [
                'connector' => $connectorKey,
                'library_external_id' => $library?->external_id,
                'scope' => $library !== null ? 'library' : 'connector',
                'issue_codes' => array_keys($issueCounts),
            ]),
            static fn (): bool => true,
        );
    }

    /**
     * The statuses a caller may see, for building empty summaries.
     *
     * @return array<string, int>
     */
    public static function emptyCounts(): array
    {
        return array_merge(
            ['normalized' => 0],
            array_fill_keys(NormalizationStatus::values(), 0),
        );
    }
}
