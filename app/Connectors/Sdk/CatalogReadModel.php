<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Actions\CreateCatalogReviewTasks;
use App\Connectors\Sdk\Catalog\CatalogItemQuery;
use App\Connectors\Sdk\Catalog\CatalogSnapshotStatus;
use App\Connectors\Sdk\Catalog\ExternalMediaKind;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogSnapshotRun;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
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
    private function latestRuns(?string $instanceId = null, ?string $libraryId = null): array
    {
        return array_values(ConnectorCatalogSnapshotRun::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->when($instanceId !== null, fn ($query) => $query->where('connector_instance_id', $instanceId))
            ->when($libraryId !== null, fn ($query) => $query->where('connector_library_id', $libraryId))
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
    private function latestItems(?string $instanceId = null, ?string $libraryId = null): array
    {
        return array_values(ConnectorCatalogItem::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            ->where('is_present', true)
            ->when($instanceId !== null, fn ($query) => $query->where('connector_instance_id', $instanceId))
            ->when($libraryId !== null, fn ($query) => $query->where('connector_library_id', $libraryId))
            ->latest('last_seen_at')
            ->limit(self::LATEST_ITEMS)
            ->get()
            ->map(fn (ConnectorCatalogItem $item): array => $this->itemView($item))
            ->all());
    }

    /**
     * Paginated, filtered, sorted list of captured external items. Every dynamic
     * clause is allowlisted upstream (CatalogItemQuery), and search is a bound LIKE
     * parameter with its wildcards escaped — no raw request input reaches SQL. All
     * queries are scoped and bounded (fixed page size). No network, no secrets.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    public function items(CatalogItemQuery $q): array
    {
        $sort = in_array($q->sort, CatalogItemQuery::SORTS, true) ? $q->sort : 'title';
        $direction = in_array($q->direction, CatalogItemQuery::DIRECTIONS, true) ? $q->direction : 'asc';

        $query = ConnectorCatalogItem::query()
            ->with(['instance:id,connector_key', 'library:id,name'])
            // Scope by the connector KEY via the relation: a registered connector
            // with no configured instance must yield nothing, not everything.
            ->when(
                $q->connectorKey !== null,
                fn ($sub) => $sub->whereHas('instance', fn ($instance) => $instance->where('connector_key', $q->connectorKey)),
            )
            ->when($q->libraryId !== null, fn ($sub) => $sub->where('connector_library_id', $q->libraryId))
            ->when(
                $q->kind !== null && in_array($q->kind, ExternalMediaKind::values(), true),
                fn ($sub) => $sub->where('media_kind', $q->kind),
            )
            ->when($q->status === 'present', fn ($sub) => $sub->where('is_present', true))
            ->when($q->status === 'missing', fn ($sub) => $sub->where('is_present', false));

        $search = $q->search !== null ? trim($q->search) : '';

        if ($search !== '') {
            // Escape LIKE wildcards so a literal % or _ can't widen the match.
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($sub) use ($like): void {
                $sub->where('title', 'ILIKE', $like)
                    ->orWhere('original_title', 'ILIKE', $like)
                    ->orWhere('sort_title', 'ILIKE', $like);
            });
        }

        $paginator = $query
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($q->perPage, ['*'], 'page', max(1, $q->page));

        return [
            'data' => array_values($paginator->getCollection()
                ->map(fn (ConnectorCatalogItem $item): array => $this->itemView($item))
                ->all()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Library filter options (id, name, connector, capture counts), optionally
     * scoped to one connector KEY. Bounded to the discovered libraries only. A
     * registered connector with no configured instance scopes to an empty list
     * (`whereIn` over no ids) rather than falling back to every library.
     *
     * @return list<array<string, mixed>>
     */
    public function libraryOptions(?string $connectorKey = null): array
    {
        $instanceIds = $connectorKey !== null
            ? ConnectorInstance::query()->where('connector_key', $connectorKey)->pluck('id')->all()
            : null;

        $counts = ConnectorCatalogItem::query()
            ->toBase()
            ->whereNotNull('connector_library_id')
            ->when($instanceIds !== null, fn ($sub) => $sub->whereIn('connector_instance_id', $instanceIds))
            ->select('connector_library_id')
            ->selectRaw('COUNT(*) FILTER (WHERE is_present) AS present')
            ->selectRaw('COUNT(*) FILTER (WHERE NOT is_present) AS missing')
            ->groupBy('connector_library_id')
            ->get()
            ->keyBy('connector_library_id');

        return array_values(ConnectorLibrary::query()
            ->with('instance:id,connector_key')
            ->when($instanceIds !== null, fn ($sub) => $sub->whereIn('connector_instance_id', $instanceIds))
            ->orderBy('name')
            ->get()
            ->map(function (ConnectorLibrary $library) use ($counts): array {
                $row = $counts->get($library->id);

                return [
                    'id' => $library->id,
                    'name' => $library->name,
                    'type' => $library->collection_type,
                    'connector' => $this->connectorLabel($library->instance?->connector_key),
                    'present_item_count' => $this->countProp(is_object($row) ? $row : null, 'present'),
                    'missing_item_count' => $this->countProp(is_object($row) ? $row : null, 'missing'),
                    'is_enabled' => $library->is_enabled,
                    'discovery_status' => $library->discovery_status,
                ];
            })
            ->all());
    }

    /** Find a library that belongs to the given connector instance, or null. */
    public function findLibrary(string $connectorInstanceId, string $libraryId): ?ConnectorLibrary
    {
        return ConnectorLibrary::query()
            ->where('connector_instance_id', $connectorInstanceId)
            ->where('id', $libraryId)
            ->first();
    }

    /**
     * Scoped page payload for one connector: catalog summary (with per-library
     * capture counts), the connector's latest runs, latest items and library list.
     *
     * @return array{summary: array<string, mixed>, latest_runs: list<array<string, mixed>>, latest_items: list<array<string, mixed>>, libraries: list<array<string, mixed>>}
     */
    public function connectorScope(?ConnectorInstance $instance, bool $configured): array
    {
        return [
            'summary' => $this->connectorSummary($instance, $configured, true),
            'latest_runs' => $instance !== null ? $this->latestRuns($instance->id) : [],
            'latest_items' => $instance !== null ? $this->latestItems($instance->id) : [],
            'libraries' => $instance !== null ? $this->libraryOptions($instance->connector_key) : [],
        ];
    }

    /**
     * Scoped page payload for one library: capture counts, run count, the latest
     * run and the library's recent runs. Stored state only — no network, no secret.
     *
     * @return array<string, mixed>
     */
    public function libraryScope(ConnectorInstance $instance, ConnectorLibrary $library): array
    {
        $counts = ConnectorCatalogItem::query()
            ->where('connector_instance_id', $instance->id)
            ->where('connector_library_id', $library->id)
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE is_present) AS present')
            ->selectRaw('COUNT(*) FILTER (WHERE NOT is_present) AS missing')
            ->first();

        $latest = ConnectorCatalogSnapshotRun::query()
            ->where('connector_instance_id', $instance->id)
            ->where('connector_library_id', $library->id)
            ->latest('created_at')
            ->orderByDesc('id')
            ->first();

        return [
            'external_item_count' => $this->countProp($counts, 'total'),
            'present_item_count' => $this->countProp($counts, 'present'),
            'missing_item_count' => $this->countProp($counts, 'missing'),
            'snapshot_run_count' => ConnectorCatalogSnapshotRun::query()
                ->where('connector_instance_id', $instance->id)
                ->where('connector_library_id', $library->id)
                ->count(),
            'last_run' => $latest !== null ? $this->runView($latest) : null,
            'latest_runs' => $this->latestRuns($instance->id, $library->id),
        ];
    }

    /** @return array<string, mixed> The read-model view of one captured item. */
    private function itemView(ConnectorCatalogItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'media_kind' => $item->media_kind,
            'year' => $item->year,
            'index_number' => $item->index_number,
            'parent_index_number' => $item->parent_index_number,
            'runtime_seconds' => $item->runtime_seconds,
            'connector' => $this->connectorLabel($item->instance?->connector_key),
            'library_name' => $item->library?->name,
            'is_present' => $item->is_present,
            'last_seen_at' => $item->last_seen_at?->toIso8601String(),
        ];
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
