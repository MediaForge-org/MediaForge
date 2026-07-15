<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Diagnostics\ConnectorHealth;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\ConnectorSyncRun;
use App\Connectors\Sdk\Models\ConnectorSyncRunLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Connectors\Sdk\Sync\PlannedSyncAction;
use App\Connectors\Sdk\Sync\SyncIssue;
use App\Connectors\Sdk\Sync\SyncLibraryStatus;
use App\Connectors\Sdk\Sync\SyncRunStatus;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\Actor;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The heart of V1 F. Runs a "dry run" over a configured connector: it inspects the
 * STORED discovery + health state, builds a per-library plan and records a
 * ConnectorSyncRun. It never calls the network, never reads the secret VALUE
 * (only whether one exists), imports no media items, and touches no files. Any
 * attention conditions are turned into a single deduplicated review task.
 *
 * Discovery is considered stale after this many days without a re-discovery.
 */
final class RunConnectorDryRun extends AuditableAction
{
    private const STALE_DISCOVERY_DAYS = 14;

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly SecretStore $secrets,
        private readonly CreateSyncReviewTasks $reviewTasks,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(string $connectorKey): ConnectorSyncRun
    {
        $instance = ConnectorInstance::query()->where('connector_key', $connectorKey)->first();

        if ($instance === null) {
            throw new RuntimeException("Connector {$connectorKey} is not configured.");
        }

        /** @var array<int, ConnectorLibrary> $libraries */
        $libraries = ConnectorLibrary::query()
            ->where('connector_instance_id', $instance->id)
            ->orderBy('name')
            ->get()
            ->all();

        $issues = $this->detectIssues($instance, $libraries);
        $status = $issues === [] ? SyncRunStatus::Completed : SyncRunStatus::CompletedWithWarnings;
        $now = Carbon::now();

        $run = new ConnectorSyncRun([
            'connector_instance_id' => $instance->id,
            'mode' => 'dry_run',
            'status' => $status->value,
            'started_at' => $now,
            'finished_at' => $now,
            'summary' => $this->buildSummary($libraries, $issues, $status),
            'created_by' => Actor::current()->label,
        ]);

        $this->transact(
            $run,
            new AuditChange(
                'connector.dry_run_completed',
                ['status' => $status->value],
                [
                    'connector' => $connectorKey,
                    'issue_codes' => array_map(static fn (SyncIssue $i): string => $i->code, $issues),
                ],
            ),
            function () use ($run, $libraries): void {
                $run->save();

                foreach ($libraries as $library) {
                    [$libStatus, $plan] = $this->planLibrary($library);

                    ConnectorSyncRunLibrary::query()->create([
                        'connector_sync_run_id' => $run->id,
                        'connector_library_id' => $library->id,
                        'external_id' => $library->external_id,
                        'name' => $library->name,
                        'type' => $library->collection_type,
                        'status' => $libStatus->value,
                        'planned_action' => $plan->value,
                        'summary' => [
                            'selected' => $library->is_enabled,
                            'discovery_status' => $library->discovery_status,
                        ],
                    ]);
                }
            },
        );

        // Reconcile the review queue outside the run's own transaction.
        $this->reviewTasks->execute($instance, $connectorKey, $issues);

        return $run;
    }

    /**
     * @param  array<int, ConnectorLibrary>  $libraries
     * @return list<SyncIssue>
     */
    private function detectIssues(ConnectorInstance $instance, array $libraries): array
    {
        $issues = [];

        // Defensive — the controller guards "configured", but never trust that here.
        if (!$this->secrets->has($instance->secrets_ref)) {
            $issues[] = new SyncIssue('secret_missing', 'The API token is missing.', 'configure connector', true);
        }

        $health = ConnectorHealth::from($instance->health_status);

        if ($health === ConnectorHealth::Unknown) {
            $issues[] = new SyncIssue('not_tested', 'The connection has not been tested yet.', 'test connection', false);
        } elseif ($health->uiStatus() === 'unhealthy') {
            $issues[] = new SyncIssue('connector_unhealthy', 'The last connection test was not healthy.', 'test connection', true);
        }

        $selected = array_filter($libraries, static fn (ConnectorLibrary $l): bool => $l->is_enabled);
        $selectedMissing = array_filter($selected, static fn (ConnectorLibrary $l): bool => $l->discovery_status === 'missing');

        if ($libraries === []) {
            $issues[] = new SyncIssue('no_libraries_discovered', 'No libraries have been discovered yet.', 'discover libraries', true);
        } elseif ($selected === []) {
            $issues[] = new SyncIssue('no_selected_libraries', 'No libraries are selected for future sync.', 'select libraries', true);
        }

        if ($selectedMissing !== []) {
            $issues[] = new SyncIssue('selected_library_missing', 'A selected library was not found in the latest discovery.', 'discover libraries', false);
        }

        if ($this->discoveryIsStale($instance)) {
            $issues[] = new SyncIssue('discovery_stale', 'Library discovery is out of date.', 'discover libraries again', false);
        }

        return $issues;
    }

    private function discoveryIsStale(ConnectorInstance $instance): bool
    {
        $discoveredAt = $instance->libraries_discovered_at;

        return $discoveredAt !== null
            && $discoveredAt->lt(Carbon::now()->subDays(self::STALE_DISCOVERY_DAYS));
    }

    /**
     * @return array{0: SyncLibraryStatus, 1: PlannedSyncAction}
     */
    private function planLibrary(ConnectorLibrary $library): array
    {
        if (!$library->is_enabled) {
            return [SyncLibraryStatus::Skipped, PlannedSyncAction::SkippedNotSelected];
        }

        if ($library->discovery_status === 'missing') {
            return [SyncLibraryStatus::Warning, PlannedSyncAction::SkippedMissing];
        }

        return [SyncLibraryStatus::Ready, PlannedSyncAction::FutureSyncCandidate];
    }

    /**
     * @param  array<int, ConnectorLibrary>  $libraries
     * @param  list<SyncIssue>  $issues
     * @return array<string, mixed>
     */
    private function buildSummary(array $libraries, array $issues, SyncRunStatus $status): array
    {
        $selected = array_filter($libraries, static fn (ConnectorLibrary $l): bool => $l->is_enabled);
        $selectedPresent = array_filter($selected, static fn (ConnectorLibrary $l): bool => $l->discovery_status === 'present');
        $selectedMissing = array_filter($selected, static fn (ConnectorLibrary $l): bool => $l->discovery_status === 'missing');

        return [
            'discovered_count' => count($libraries),
            'selected_count' => count($selected),
            'selected_present_count' => count($selectedPresent),
            'selected_missing_count' => count($selectedMissing),
            'ready_for_future_sync' => $status === SyncRunStatus::Completed,
            'issues' => array_map(static fn (SyncIssue $i): array => $i->toArray(), $issues),
            'note' => 'Dry run only. No media import in V1 F. No files are copied, moved or deleted.',
        ];
    }
}
