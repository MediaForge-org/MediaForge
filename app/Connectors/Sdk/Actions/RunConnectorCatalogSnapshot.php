<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Catalog\CatalogIssue;
use App\Connectors\Sdk\Catalog\CatalogSnapshotRequest;
use App\Connectors\Sdk\Catalog\CatalogSnapshotResult;
use App\Connectors\Sdk\Catalog\CatalogSnapshotStatus;
use App\Connectors\Sdk\Diagnostics\ConnectorHealth;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogSnapshotRun;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\Actor;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The heart of V2 A. Takes a READ-ONLY snapshot of one connector library: it reads
 * external items over the network and stores them as a read-only connector
 * read-model. It never imports media, never creates media_items/editions/files,
 * and never touches a file. The network read happens OUTSIDE the transaction; only
 * the upsert + run + audit are transactional. A bounded limit caps every run.
 */
final class RunConnectorCatalogSnapshot extends AuditableAction
{
    /** Hard cap on items captured per snapshot run (bounded — paginated later). */
    public const ITEM_LIMIT = 500;

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly SecretStore $secrets,
        private readonly ConnectorRegistry $registry,
        private readonly CreateCatalogReviewTasks $reviewTasks,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(string $connectorKey, string $libraryId): ConnectorCatalogSnapshotRun
    {
        $provider = $this->registry->get($connectorKey);
        $instance = ConnectorInstance::query()->where('connector_key', $connectorKey)->first();

        if ($instance === null) {
            throw new RuntimeException("Connector {$connectorKey} is not configured.");
        }

        // firstOrFail also enforces that the library belongs to this connector, so
        // an arbitrary/foreign library id can never be snapshotted.
        $library = ConnectorLibrary::query()
            ->where('connector_instance_id', $instance->id)
            ->where('id', $libraryId)
            ->firstOrFail();

        // Capability fallback — no network is touched when snapshots are unsupported.
        if (!$provider->supportsCatalogSnapshot()) {
            return $this->recordUnsupported($instance, $connectorKey, $library);
        }

        $result = $provider->snapshotLibraryItems(new CatalogSnapshotRequest(
            baseUrl: $instance->base_url,
            secret: $this->secrets->get($instance->secrets_ref),
            libraryExternalId: $library->external_id,
            libraryType: $library->collection_type,
            limit: self::ITEM_LIMIT,
        ));

        return $this->recordResult($instance, $connectorKey, $library, $result);
    }

    private function recordUnsupported(ConnectorInstance $instance, string $connectorKey, ConnectorLibrary $library): ConnectorCatalogSnapshotRun
    {
        $issues = [new CatalogIssue(
            'snapshot_unsupported',
            'Catalog snapshots are not supported yet for this connector in V2 A.',
            'wait for a later package',
            false,
        )];

        $now = Carbon::now();
        $run = new ConnectorCatalogSnapshotRun([
            'connector_instance_id' => $instance->id,
            'connector_library_id' => $library->id,
            'status' => CatalogSnapshotStatus::CompletedWithWarnings->value,
            'started_at' => $now,
            'finished_at' => $now,
            'warnings_count' => 1,
            'summary' => $this->summary($library, 0, 0, false, null, $issues, 'unsupported'),
            'created_by' => Actor::current()->label,
        ]);

        $this->transact(
            $run,
            new AuditChange('connector.catalog_snapshot_completed', ['status' => $run->status], [
                'connector' => $connectorKey,
                'library_external_id' => $library->external_id,
                'issue_codes' => ['snapshot_unsupported'],
            ]),
            fn (): bool => $run->save(),
        );

        $this->reviewTasks->execute($instance, $connectorKey, $issues);

        return $run;
    }

    private function recordResult(ConnectorInstance $instance, string $connectorKey, ConnectorLibrary $library, CatalogSnapshotResult $result): ConnectorCatalogSnapshotRun
    {
        $issues = $this->detectIssues($instance, $library, $result);
        $now = Carbon::now();

        $status = match (true) {
            !$result->ok => CatalogSnapshotStatus::Failed,
            $issues !== [] => CatalogSnapshotStatus::CompletedWithWarnings,
            default => CatalogSnapshotStatus::Completed,
        };

        $storedCount = 0;

        $run = new ConnectorCatalogSnapshotRun([
            'connector_instance_id' => $instance->id,
            'connector_library_id' => $library->id,
            'status' => $status->value,
            'started_at' => $now,
            'finished_at' => $now,
            'items_seen_count' => $result->totalSeen ?? count($result->items),
            'items_stored_count' => 0,
            'warnings_count' => $result->truncated ? 1 : 0,
            'errors_count' => $result->ok ? 0 : 1,
            'error_message' => $result->ok ? null : $result->detail,
            'summary' => $this->summary($library, count($result->items), $result->totalSeen ?? count($result->items), $result->truncated, $result->httpStatus, $issues, $result->ok ? 'ok' : 'failed'),
            'created_by' => Actor::current()->label,
        ]);

        $this->transact(
            $run,
            new AuditChange('connector.catalog_snapshot_completed', ['status' => $status->value], [
                'connector' => $connectorKey,
                'library_external_id' => $library->external_id,
                'http_status' => $result->httpStatus,
                'ok' => $result->ok,
                'issue_codes' => array_map(static fn (CatalogIssue $i): string => $i->code, $issues),
            ]),
            function () use ($run, $instance, $library, $result, &$storedCount): void {
                $run->save();

                if (!$result->ok) {
                    return; // A failed read never wipes previously captured items.
                }

                $storedCount = $this->storeItems($instance, $library, $run->id, $result);
                $run->items_stored_count = $storedCount;
                $run->save();
            },
        );

        $this->reviewTasks->execute($instance, $connectorKey, $issues);

        return $run;
    }

    /**
     * Upsert the captured items and flag vanished ones. Read-model writes only —
     * no media_items/editions/files, no file operations.
     */
    private function storeItems(ConnectorInstance $instance, ConnectorLibrary $library, string $runId, CatalogSnapshotResult $result): int
    {
        $now = Carbon::now();
        $seen = [];

        foreach ($result->items as $item) {
            $record = ConnectorCatalogItem::query()->firstOrNew([
                'connector_instance_id' => $instance->id,
                'external_id' => $item->externalId,
            ]);

            if (!$record->exists) {
                $record->first_seen_at = $now;
            }

            $record->connector_library_id = $library->id;
            $record->snapshot_run_id = $runId;
            $record->external_parent_id = $item->externalParentId;
            $record->media_kind = $item->kind->value;
            $record->title = $item->title;
            $record->sort_title = $item->sortTitle;
            $record->original_title = $item->originalTitle;
            $record->year = $item->year;
            $record->index_number = $item->indexNumber;
            $record->parent_index_number = $item->parentIndexNumber;
            $record->runtime_seconds = $item->runtimeSeconds;
            $record->external_updated_at = $this->parseTimestamp($item->externalUpdatedAt);
            $record->last_seen_at = $now;
            $record->missing_since = null;
            $record->is_present = true;
            $record->metadata = $item->metadata;
            $record->save();

            $seen[] = $item->externalId;
        }

        // Items previously captured for THIS library but not seen now → mark missing.
        ConnectorCatalogItem::query()
            ->where('connector_instance_id', $instance->id)
            ->where('connector_library_id', $library->id)
            ->where('is_present', true)
            ->when($seen !== [], fn ($query) => $query->whereNotIn('external_id', $seen))
            ->update(['is_present' => false, 'missing_since' => $now]);

        return count($seen);
    }

    /**
     * Providers hand back a remote timestamp as an opaque string. Parse it
     * defensively — a malformed value must never break a read-only snapshot.
     */
    private function parseTimestamp(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * @return list<CatalogIssue>
     */
    private function detectIssues(ConnectorInstance $instance, ConnectorLibrary $library, CatalogSnapshotResult $result): array
    {
        $issues = [];

        if (!$result->ok) {
            $issues[] = new CatalogIssue('snapshot_failed', $result->detail, 'test connection', true);

            return $issues;
        }

        if ($library->discovery_status === 'missing') {
            $issues[] = new CatalogIssue('library_missing', 'This library was not found in the latest discovery.', 'discover libraries again', false);
        }

        if (ConnectorHealth::from($instance->health_status)->uiStatus() === 'unhealthy') {
            $issues[] = new CatalogIssue('connector_unhealthy', 'The last connection test was not healthy.', 'test connection', false);
        }

        if ($result->truncated) {
            $issues[] = new CatalogIssue(
                'snapshot_truncated',
                'The library holds more items than the snapshot limit. Larger paginated snapshots arrive later.',
                'review the captured subset',
                false,
            );
        }

        return $issues;
    }

    /**
     * @param  list<CatalogIssue>  $issues
     * @return array<string, mixed>
     */
    private function summary(ConnectorLibrary $library, int $stored, int $seen, bool $truncated, ?int $httpStatus, array $issues, string $outcome): array
    {
        return [
            'library_external_id' => $library->external_id,
            'library_name' => $library->name,
            'items_stored' => $stored,
            'items_seen' => $seen,
            'truncated' => $truncated,
            'http_status' => $httpStatus,
            'outcome' => $outcome,
            'issues' => array_map(static fn (CatalogIssue $i): array => $i->toArray(), $issues),
            'note' => 'Read-only snapshot. No media import in V2 A. No files are copied, moved or deleted.',
        ];
    }
}
