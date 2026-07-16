<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Actions\CreateCatalogReviewTasks;
use App\Connectors\Sdk\Catalog\CatalogSnapshotStatus;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogSnapshotRun;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Core\Review\ReviewTask;

/**
 * Read model for the external catalog (V2 A). Produces secret-free view arrays for
 * the /catalog page, the connector overview/detail catalog blocks and the dashboard
 * summary. Reads stored snapshot runs/items only — never the network, never a
 * secret. All list queries are bounded.
 */
final class CatalogReadModel
{
    private const LATEST_RUNS = 10;

    private const LATEST_ITEMS = 25;

    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /**
     * Per-connector catalog block for the overview cards, dashboard and detail
     * page. `withLibraries` adds a per-library breakdown for the detail page.
     *
     * @return array<string, mixed>
     */
    public function connectorSummary(?ConnectorInstance $instance, bool $configured, bool $withLibraries = false): array
    {
        if ($instance === null || !$configured) {
            return [
                'status' => 'not_ready',
                'external_item_count' => 0,
                'present_item_count' => 0,
                'missing_item_count' => 0,
                'snapshot_run_count' => 0,
                'open_review_count' => 0,
                'last_run' => null,
                'libraries' => [],
            ];
        }

        $itemCounts = ConnectorCatalogItem::query()
            ->where('connector_instance_id', $instance->id)
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE is_present) AS present')
            ->selectRaw('COUNT(*) FILTER (WHERE NOT is_present) AS missing')
            ->first();

        $openReviewCount = ReviewTask::query()
            ->where('task_type', CreateCatalogReviewTasks::TASK_TYPE)
            ->where('subject_type', CreateCatalogReviewTasks::SUBJECT_TYPE)
            ->where('subject_id', $instance->id)
            ->whereIn('status', ['open', 'in_review'])
            ->count();

        // Tie-break on the monotonic ULID: two runs can share a created_at second,
        // and the newest must win deterministically.
        $latest = ConnectorCatalogSnapshotRun::query()
            ->where('connector_instance_id', $instance->id)
            ->latest('created_at')
            ->orderByDesc('id')
            ->first();

        return [
            'status' => $this->status($latest, $openReviewCount),
            'external_item_count' => $this->countProp($itemCounts, 'total'),
            'present_item_count' => $this->countProp($itemCounts, 'present'),
            'missing_item_count' => $this->countProp($itemCounts, 'missing'),
            'snapshot_run_count' => ConnectorCatalogSnapshotRun::query()->where('connector_instance_id', $instance->id)->count(),
            'open_review_count' => $openReviewCount,
            'last_run' => $latest !== null ? $this->runView($latest) : null,
            'libraries' => $withLibraries ? $this->libraryBreakdown($instance) : [],
        ];
    }

    /**
     * Per-library catalog counts keyed by connector_library_id, for the connector
     * detail library rows.
     *
     * @return array<string, array<string, mixed>>
     */
    private function libraryBreakdown(ConnectorInstance $instance): array
    {
        /** @var array<string, array<string, mixed>> $out */
        $out = [];

        $rows = ConnectorCatalogItem::query()
            ->where('connector_instance_id', $instance->id)
            ->whereNotNull('connector_library_id')
            ->toBase()
            ->select('connector_library_id')
            ->selectRaw('COUNT(*) FILTER (WHERE is_present) AS present')
            ->selectRaw('MAX(last_seen_at) AS last_seen_at')
            ->groupBy('connector_library_id')
            ->get();

        foreach ($rows as $row) {
            $libraryId = is_string($row->connector_library_id) ? $row->connector_library_id : null;

            if ($libraryId === null) {
                continue;
            }

            $out[$libraryId] = [
                'external_item_count' => $this->countProp($row, 'present'),
                'last_seen_at' => is_string($row->last_seen_at ?? null) ? $row->last_seen_at : null,
            ];
        }

        return $out;
    }

    /**
     * The /catalog page payload: summary counts, connector cards, latest runs and
     * latest external items. All bounded.
     *
     * @param  list<array<string, mixed>>  $connectors  ConnectorCatalog::overview()
     * @return array<string, mixed>
     */
    public function overview(array $connectors): array
    {
        $externalItems = ConnectorCatalogItem::query()->where('is_present', true)->count();
        $snapshotRuns = ConnectorCatalogSnapshotRun::query()->count();

        $librariesCaptured = ConnectorCatalogItem::query()
            ->where('is_present', true)
            ->whereNotNull('connector_library_id')
            ->distinct()
            ->count('connector_library_id');

        $attention = 0;
        foreach ($connectors as $connector) {
            if (is_array($connector['catalog'] ?? null) && ($connector['catalog']['status'] ?? null) === 'attention_required') {
                $attention++;
            }
        }

        return [
            'summary' => [
                'external_items' => $externalItems,
                'snapshot_runs' => $snapshotRuns,
                'libraries_captured' => $librariesCaptured,
                'attention_count' => $attention,
            ],
            'latest_runs' => $this->latestRuns(),
            'latest_items' => $this->latestItems(),
        ];
    }

    /**
     * Dashboard-level aggregate over the connector catalog blocks.
     *
     * @param  list<array<string, mixed>>  $connectors  ConnectorCatalog::overview()
     * @return array<string, mixed>
     */
    public function dashboardSummary(array $connectors): array
    {
        $externalItems = 0;
        $attention = 0;
        $lastSnapshotAt = null;

        foreach ($connectors as $connector) {
            /** @var array<string, mixed> $catalog */
            $catalog = is_array($connector['catalog'] ?? null) ? $connector['catalog'] : [];

            $present = $catalog['present_item_count'] ?? 0;
            $externalItems += is_numeric($present) ? (int) $present : 0;

            if (($catalog['status'] ?? null) === 'attention_required') {
                $attention++;
            }

            $finishedAt = is_array($catalog['last_run'] ?? null) ? ($catalog['last_run']['finished_at'] ?? null) : null;

            if (is_string($finishedAt) && ($lastSnapshotAt === null || $finishedAt > $lastSnapshotAt)) {
                $lastSnapshotAt = $finishedAt;
            }
        }

        return [
            'external_items' => $externalItems,
            'attention_count' => $attention,
            'last_snapshot_at' => $lastSnapshotAt,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function latestRuns(): array
    {
        return array_values(ConnectorCatalogSnapshotRun::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_RUNS)
            ->get()
            ->map(fn (ConnectorCatalogSnapshotRun $run): array => [
                'id' => $run->id,
                'status' => $run->status,
                'connector' => $this->connectorLabel($run->instance?->connector_key),
                'library_name' => $run->library?->name,
                'items_stored_count' => $run->items_stored_count,
                'items_seen_count' => $run->items_seen_count,
                'warnings_count' => $run->warnings_count,
                'errors_count' => $run->errors_count,
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function latestItems(): array
    {
        return array_values(ConnectorCatalogItem::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->where('is_present', true)
            ->latest('last_seen_at')
            ->limit(self::LATEST_ITEMS)
            ->get()
            ->map(fn (ConnectorCatalogItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'media_kind' => $item->media_kind,
                'year' => $item->year,
                'index_number' => $item->index_number,
                'runtime_seconds' => $item->runtime_seconds,
                'connector' => $this->connectorLabel($item->instance?->connector_key),
                'library_name' => $item->library?->name,
                'is_present' => $item->is_present,
                'last_seen_at' => $item->last_seen_at?->toIso8601String(),
            ])
            ->all());
    }

    /** @return array{key: string, label: string}|null */
    private function connectorLabel(?string $key): ?array
    {
        if ($key === null || !$this->registry->has($key)) {
            return null;
        }

        return ['key' => $key, 'label' => $this->registry->get($key)->label()];
    }

    private function status(?ConnectorCatalogSnapshotRun $latest, int $openReviewCount): string
    {
        $attention = $openReviewCount > 0
            || ($latest !== null && CatalogSnapshotStatus::from($latest->status)->needsAttention());

        if ($attention) {
            return 'attention_required';
        }

        if ($latest !== null && $latest->status === CatalogSnapshotStatus::Completed->value) {
            return 'last_snapshot_completed';
        }

        return 'ready_for_snapshot';
    }

    /** @return array<string, mixed> */
    private function runView(ConnectorCatalogSnapshotRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'items_seen_count' => $run->items_seen_count,
            'items_stored_count' => $run->items_stored_count,
            'warnings_count' => $run->warnings_count,
            'errors_count' => $run->errors_count,
            'summary' => $run->summary,
        ];
    }

    /** COUNT(*) FILTER results come back as numeric strings on the pgsql driver. */
    private function countProp(?object $row, string $key): int
    {
        $value = $row?->{$key};

        return is_numeric($value) ? (int) $value : 0;
    }
}
