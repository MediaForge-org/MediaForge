<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Actions\CreateSyncReviewTasks;
use App\Connectors\Sdk\Diagnostics\ConnectorHealth;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\ConnectorSyncRun;
use App\Connectors\Sdk\Models\ConnectorSyncRunLibrary;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Connectors\Sdk\Sync\SyncRunStatus;
use App\Core\Review\ReviewTask;

/**
 * Read model for the connector UI. Produces secret-free view arrays for the
 * overview, the detail pages and the dashboard cards. It exposes whether a secret
 * exists (`secret_configured`) but never the secret itself.
 */
final class ConnectorCatalog
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly SecretStore $secrets,
    ) {}

    /** @return list<array<string, mixed>> One entry per registered connector. */
    public function overview(): array
    {
        return array_map(
            fn (string $key): array => $this->view($key),
            $this->registry->keys(),
        );
    }

    /** @return array<string, mixed> */
    public function view(string $key): array
    {
        $provider = $this->registry->get($key);
        $instance = $this->instance($key);

        $secretConfigured = $instance !== null && $this->secrets->has($instance->secrets_ref);
        $configured = $instance !== null && $instance->base_url !== '' && $secretConfigured;

        $health = $instance !== null
            ? ConnectorHealth::from($instance->health_status)
            : ConnectorHealth::Unknown;

        return [
            'key' => $provider->key(),
            'label' => $provider->label(),
            'base_url' => $instance !== null ? $instance->base_url : '',
            'configured' => $configured,
            'secret_configured' => $secretConfigured,
            'status' => $configured ? $health->uiStatus() : 'not_configured',
            'health_status' => $health->value,
            'health_detail' => $instance?->health_detail,
            'last_checked_at' => $instance?->last_checked_at?->toIso8601String(),
            'last_healthy_at' => $instance?->last_healthy_at?->toIso8601String(),
            // Discovery aggregates only — the full library list is added by detail().
            'library_count' => $instance !== null
                ? ConnectorLibrary::query()->where('connector_instance_id', $instance->id)->count()
                : 0,
            'libraries_discovered_at' => $instance?->libraries_discovered_at?->toIso8601String(),
            'last_discovery_error' => $instance?->last_discovery_error,
            // Sync foundation aggregates (V1 F). Stored state only, no network.
            'sync' => $this->syncView($instance, $configured, false),
        ];
    }

    /**
     * The detail view: the aggregate view plus the full discovered-library list.
     * Only the current connector's libraries are loaded (no cross-connector data).
     *
     * @return array<string, mixed>
     */
    public function detail(string $key): array
    {
        $view = $this->view($key);
        $instance = $this->instance($key);

        $view['libraries'] = $instance !== null ? $this->libraries($instance) : [];
        // Re-render the sync block with the latest run's per-library breakdown.
        $view['sync'] = $this->syncView($instance, $view['configured'] === true, true);

        return $view;
    }

    public function instance(string $key): ?ConnectorInstance
    {
        return ConnectorInstance::query()
            ->where('connector_key', $key)
            ->first();
    }

    /**
     * Sync foundation read model for one connector. Reads stored runs/reviews only
     * — never the network, never the secret value. `withLastRunLibraries` adds the
     * latest run's per-library plan (detail page) but is skipped for the overview.
     *
     * @return array<string, mixed>
     */
    private function syncView(?ConnectorInstance $instance, bool $configured, bool $withLastRunLibraries): array
    {
        if ($instance === null || !$configured) {
            return [
                'status' => 'not_ready',
                'selected_count' => 0,
                'selected_present_count' => 0,
                'selected_missing_count' => 0,
                'discovered_count' => 0,
                'open_review_count' => 0,
                'last_run' => null,
            ];
        }

        $counts = ConnectorLibrary::query()
            ->where('connector_instance_id', $instance->id)
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE is_enabled) AS selected')
            ->selectRaw("COUNT(*) FILTER (WHERE is_enabled AND discovery_status = 'present') AS selected_present")
            ->selectRaw("COUNT(*) FILTER (WHERE is_enabled AND discovery_status = 'missing') AS selected_missing")
            ->first();

        $openReviewCount = ReviewTask::query()
            ->where('task_type', CreateSyncReviewTasks::TASK_TYPE)
            ->where('subject_type', CreateSyncReviewTasks::SUBJECT_TYPE)
            ->where('subject_id', $instance->id)
            ->whereIn('status', ['open', 'in_review'])
            ->count();

        $latest = ConnectorSyncRun::query()
            ->where('connector_instance_id', $instance->id)
            ->latest('created_at')
            ->first();

        return [
            'status' => $this->syncStatus($latest, $openReviewCount),
            'selected_count' => $this->countProp($counts, 'selected'),
            'selected_present_count' => $this->countProp($counts, 'selected_present'),
            'selected_missing_count' => $this->countProp($counts, 'selected_missing'),
            'discovered_count' => $this->countProp($counts, 'total'),
            'open_review_count' => $openReviewCount,
            'last_run' => $latest !== null ? $this->runView($latest, $withLastRunLibraries) : null,
        ];
    }

    /** COUNT(*) FILTER results come back as numeric strings on the pgsql driver. */
    private function countProp(?object $row, string $key): int
    {
        $value = $row?->{$key};

        return is_numeric($value) ? (int) $value : 0;
    }

    private function syncStatus(?ConnectorSyncRun $latest, int $openReviewCount): string
    {
        $attention = $openReviewCount > 0
            || ($latest !== null && SyncRunStatus::from($latest->status)->needsAttention());

        if ($attention) {
            return 'attention_required';
        }

        if ($latest !== null && $latest->status === SyncRunStatus::Completed->value) {
            return 'last_dry_run_completed';
        }

        return 'ready_for_dry_run';
    }

    /** @return array<string, mixed> */
    private function runView(ConnectorSyncRun $run, bool $withLibraries): array
    {
        $view = [
            'id' => $run->id,
            'mode' => $run->mode,
            'status' => $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'summary' => $run->summary,
        ];

        if ($withLibraries) {
            $view['libraries'] = ConnectorSyncRunLibrary::query()
                ->where('connector_sync_run_id', $run->id)
                ->orderBy('name')
                ->get()
                ->map(static fn (ConnectorSyncRunLibrary $library): array => [
                    'external_id' => $library->external_id,
                    'name' => $library->name,
                    'type' => $library->type,
                    'status' => $library->status,
                    'planned_action' => $library->planned_action,
                ])
                ->all();
        }

        return $view;
    }

    /** @return array<int, array<string, mixed>> */
    private function libraries(ConnectorInstance $instance): array
    {
        return ConnectorLibrary::query()
            ->where('connector_instance_id', $instance->id)
            ->orderBy('name')
            ->get()
            ->map(static fn (ConnectorLibrary $library): array => [
                'id' => $library->id,
                'external_id' => $library->external_id,
                'name' => $library->name,
                'type' => $library->collection_type,
                'path' => $library->path,
                'is_enabled' => $library->is_enabled,
                'discovery_status' => $library->discovery_status,
                'last_seen_at' => $library->last_seen_at?->toIso8601String(),
            ])
            ->all();
    }
}
