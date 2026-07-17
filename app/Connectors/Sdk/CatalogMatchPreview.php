<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Catalog\NormalizationIssue;
use App\Connectors\Sdk\Catalog\NormalizationStatus;
use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only match SUGGESTIONS over the normalized catalog (V2 C).
 *
 * This is a PREVIEW and nothing else. It groups what the normalization already
 * computed and shows what a future import might have to reconcile. It accepts no
 * match, merges nothing, writes nothing, creates no media_items/editions/files,
 * touches no file and calls no network — the import plan arrives in V2 D.
 *
 * Everything is derived at query time from connector_catalog_item_normalizations;
 * there is deliberately no candidates table, because a suggestion that nobody can
 * accept has no state worth storing. Every group is bounded.
 */
final class CatalogMatchPreview
{
    /** Max groups returned per section. */
    private const MAX_GROUPS = 25;

    /** Max items listed inside one group. */
    private const MAX_PER_GROUP = 10;

    /** Max items listed in the flat weak-metadata section. */
    private const MAX_WEAK = 25;

    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?string $connectorKey = null, ?string $libraryId = null): array
    {
        return [
            'duplicate_suspects' => $this->duplicateSuspects($connectorKey, $libraryId),
            'episode_groups' => $this->episodeGroups($connectorKey, $libraryId),
            'audiobook_groups' => $this->audiobookGroups($connectorKey, $libraryId),
            'weak_metadata' => $this->weakMetadata($connectorKey, $libraryId),
            'note' => 'Matching preview only. No imports or merges in V2 C.',
        ];
    }

    /**
     * Items whose normalized identity (title + year + kind) is shared. These are
     * SUSPECTS: two libraries legitimately holding the same film look identical
     * here, which is exactly what a later import has to decide about.
     *
     * @return list<array<string, mixed>>
     */
    private function duplicateSuspects(?string $connectorKey, ?string $libraryId): array
    {
        $groups = $this->scoped($connectorKey, $libraryId)
            ->toBase()
            ->selectRaw('normalized_title, release_year, normalized_kind, COUNT(*) AS item_count')
            ->groupBy('normalized_title', 'release_year', 'normalized_kind')
            ->havingRaw('COUNT(*) > 1')
            ->orderByRaw('COUNT(*) DESC, normalized_title ASC')
            ->limit(self::MAX_GROUPS)
            ->get();

        $out = [];

        foreach ($groups as $group) {
            $title = is_string($group->normalized_title) ? $group->normalized_title : '';
            $kind = is_string($group->normalized_kind) ? $group->normalized_kind : 'unknown';
            $year = is_numeric($group->release_year ?? null) ? (int) $group->release_year : null;

            $out[] = [
                'group_key' => $this->groupKey(['duplicate', $kind, $title, (string) $year]),
                'title' => $title,
                'release_year' => $year,
                'kind' => $kind,
                'item_count' => is_numeric($group->item_count) ? (int) $group->item_count : 0,
                'score' => $this->duplicateScore($year),
                'reason' => $year !== null
                    ? 'Same normalized title, year and kind.'
                    : 'Same normalized title and kind, no year to tell them apart.',
                'items' => $this->groupItems(
                    $this->scoped($connectorKey, $libraryId)
                        ->where('normalized_title', $title)
                        ->where('normalized_kind', $kind)
                        ->whereRaw($year === null ? 'release_year IS NULL' : 'release_year = ?', $year === null ? [] : [$year]),
                ),
            ];
        }

        return $out;
    }

    /** A year makes a duplicate claim materially stronger. */
    private function duplicateScore(?int $year): int
    {
        return $year !== null ? 85 : 60;
    }

    /**
     * Episodes that belong to the same series+season, so a later import can see the
     * grouping the connector implied. Only items that actually carry a parent.
     *
     * @return list<array<string, mixed>>
     */
    private function episodeGroups(?string $connectorKey, ?string $libraryId): array
    {
        $groups = $this->scoped($connectorKey, $libraryId)
            ->where('normalized_kind', 'episode')
            ->whereNotNull('parent_title')
            ->toBase()
            ->selectRaw('parent_title, season_number, COUNT(*) AS item_count')
            ->selectRaw('COUNT(*) FILTER (WHERE episode_number IS NULL) AS missing_episode_count')
            ->groupBy('parent_title', 'season_number')
            ->orderByRaw('parent_title ASC, season_number ASC NULLS FIRST')
            ->limit(self::MAX_GROUPS)
            ->get();

        $out = [];

        foreach ($groups as $group) {
            $parent = is_string($group->parent_title) ? $group->parent_title : '';
            $season = is_numeric($group->season_number ?? null) ? (int) $group->season_number : null;
            $missing = is_numeric($group->missing_episode_count) ? (int) $group->missing_episode_count : 0;

            $out[] = [
                'group_key' => $this->groupKey(['series', $parent, (string) $season]),
                'parent_title' => $parent,
                'season_number' => $season,
                'item_count' => is_numeric($group->item_count) ? (int) $group->item_count : 0,
                'missing_episode_count' => $missing,
                'score' => $season !== null && $missing === 0 ? 90 : 65,
                'reason' => $season === null
                    ? 'Episodes of the same series with no season number.'
                    : ($missing > 0
                        ? "Episodes of the same series and season; {$missing} without an episode number."
                        : 'Episodes of the same series and season, fully numbered.'),
                'items' => $this->groupItems(
                    $this->scoped($connectorKey, $libraryId)
                        ->where('normalized_kind', 'episode')
                        ->where('parent_title', $parent)
                        ->whereRaw($season === null ? 'season_number IS NULL' : 'season_number = ?', $season === null ? [] : [$season])
                        ->reorder('episode_number'),
                ),
            ];
        }

        return $out;
    }

    /**
     * Audiobooks/books sharing a normalized title — the same work captured twice, or
     * an edition split. Grouped on title (+year when present); there is no author
     * field in the captured read-model, so we do not pretend to match on one.
     *
     * @return list<array<string, mixed>>
     */
    private function audiobookGroups(?string $connectorKey, ?string $libraryId): array
    {
        $groups = $this->scoped($connectorKey, $libraryId)
            ->whereIn('normalized_kind', ['audiobook', 'book'])
            ->toBase()
            ->selectRaw('normalized_title, release_year, COUNT(*) AS item_count')
            ->groupBy('normalized_title', 'release_year')
            ->havingRaw('COUNT(*) > 1')
            ->orderByRaw('COUNT(*) DESC, normalized_title ASC')
            ->limit(self::MAX_GROUPS)
            ->get();

        $out = [];

        foreach ($groups as $group) {
            $title = is_string($group->normalized_title) ? $group->normalized_title : '';
            $year = is_numeric($group->release_year ?? null) ? (int) $group->release_year : null;

            $out[] = [
                'group_key' => $this->groupKey(['audiobook', $title, (string) $year]),
                'title' => $title,
                'release_year' => $year,
                'item_count' => is_numeric($group->item_count) ? (int) $group->item_count : 0,
                'score' => $year !== null ? 80 : 55,
                'reason' => 'Same normalized title across audiobook/book entries.',
                'items' => $this->groupItems(
                    $this->scoped($connectorKey, $libraryId)
                        ->whereIn('normalized_kind', ['audiobook', 'book'])
                        ->where('normalized_title', $title)
                        ->whereRaw($year === null ? 'release_year IS NULL' : 'release_year = ?', $year === null ? [] : [$year]),
                ),
            ];
        }

        return $out;
    }

    /**
     * Items too thin to interpret — a later import would have nothing to match on.
     *
     * @return list<array<string, mixed>>
     */
    private function weakMetadata(?string $connectorKey, ?string $libraryId): array
    {
        $rows = $this->scoped($connectorKey, $libraryId)
            ->with(['item:id,connector_instance_id,connector_library_id,external_id', 'instance:id,connector_key', 'library:id,name'])
            ->where(function (Builder $query): void {
                $query->where('status', NormalizationStatus::NeedsReview->value)
                    ->orWhereJsonContains('issues', NormalizationIssue::WeakMetadata->value);
            })
            ->orderBy('confidence')
            ->orderBy('normalized_title')
            ->limit(self::MAX_WEAK)
            ->get();

        return array_values($rows->map(fn (ConnectorCatalogItemNormalization $row): array => $this->itemView($row))->all());
    }

    /**
     * @param  Builder<ConnectorCatalogItemNormalization>  $query
     * @return list<array<string, mixed>>
     */
    private function groupItems(Builder $query): array
    {
        return array_values($query
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->limit(self::MAX_PER_GROUP)
            ->get()
            ->map(fn (ConnectorCatalogItemNormalization $row): array => $this->itemView($row))
            ->all());
    }

    /** @return array<string, mixed> */
    private function itemView(ConnectorCatalogItemNormalization $row): array
    {
        return [
            'id' => $row->connector_catalog_item_id,
            'title' => $row->normalized_title,
            'kind' => $row->normalized_kind,
            'release_year' => $row->release_year,
            'season_number' => $row->season_number,
            'episode_number' => $row->episode_number,
            'parent_title' => $row->parent_title,
            'confidence' => $row->confidence,
            'status' => $row->status,
            'issues' => $this->issueCodes($row->issues),
            'connector' => $this->connectorLabel($row->instance?->connector_key),
            'library_name' => $row->library?->name,
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return list<array{code: string, message: string}>
     */
    private function issueCodes(array $codes): array
    {
        $views = [];

        foreach ($codes as $code) {
            $issue = NormalizationIssue::tryFrom($code);

            if ($issue !== null) {
                $views[] = ['code' => $issue->value, 'message' => $issue->message()];
            }
        }

        return $views;
    }

    /** @return array{key: string, label: string}|null */
    private function connectorLabel(?string $key): ?array
    {
        if ($key === null || !$this->registry->has($key)) {
            return null;
        }

        return ['key' => $key, 'label' => $this->registry->get($key)->label()];
    }

    /**
     * A stable, opaque React key for a group. Hashed so no title text ends up in a
     * DOM attribute, and stable so re-renders do not thrash.
     *
     * @param  list<string>  $parts
     */
    private function groupKey(array $parts): string
    {
        return substr(hash('xxh128', implode('|', $parts)), 0, 16);
    }

    /** @return Builder<ConnectorCatalogItemNormalization> */
    private function scoped(?string $connectorKey, ?string $libraryId): Builder
    {
        return ConnectorCatalogItemNormalization::query()
            ->when(
                $connectorKey !== null,
                fn ($sub) => $sub->whereHas('instance', fn ($instance) => $instance->where('connector_key', $connectorKey)),
            )
            ->when($libraryId !== null, fn ($sub) => $sub->where('connector_library_id', $libraryId));
    }
}
