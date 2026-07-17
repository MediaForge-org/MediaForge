<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Catalog\CatalogIssue;
use App\Connectors\Sdk\Catalog\CatalogSnapshotRequest;
use App\Connectors\Sdk\Catalog\CatalogSnapshotResult;
use App\Connectors\Sdk\Catalog\CatalogSnapshotStatus;
use App\Connectors\Sdk\Catalog\SnapshotItem;
use App\Connectors\Sdk\Contracts\ConnectorProvider;
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
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The heart of the read-only catalog. Takes a READ-ONLY snapshot of one connector
 * library: it reads external items over the network and stores them as a read-only
 * connector read-model. It never imports media, never creates
 * media_items/editions/files, and never touches a file. The network reads happen
 * OUTSIDE the transaction; only the upsert + run + audit are transactional.
 *
 * V2 B: the snapshot pages through the remote a bounded page at a time (PAGE_SIZE),
 * up to a hard total cap (MAX_ITEMS_PER_SNAPSHOT), so larger libraries are captured
 * without ever looping unbounded. When the library holds more than the cap (or a
 * later page errors) the run is marked truncated and raises a warning review task.
 */
final class RunConnectorCatalogSnapshot extends AuditableAction
{
    /** Items requested per remote page. */
    public const PAGE_SIZE = 500;

    /** Hard cap on total items captured per snapshot run (bounds the page loop). */
    public const MAX_ITEMS_PER_SNAPSHOT = 5000;

    /** Rows per bulk upsert statement. */
    private const UPSERT_CHUNK = 500;

    /**
     * Columns refreshed when a captured item is seen again. Deliberately excludes
     * `id`, the conflict target and `first_seen_at`/`created_at`, so a re-captured
     * item keeps its identity and its original first-seen moment.
     */
    private const UPDATE_COLUMNS = [
        'connector_library_id',
        'snapshot_run_id',
        'external_parent_id',
        'media_kind',
        'title',
        'sort_title',
        'original_title',
        'year',
        'index_number',
        'parent_index_number',
        'runtime_seconds',
        'external_updated_at',
        'last_seen_at',
        'missing_since',
        'is_present',
        'metadata',
        'updated_at',
    ];

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly SecretStore $secrets,
        private readonly ConnectorRegistry $registry,
        private readonly CreateCatalogReviewTasks $reviewTasks,
        private readonly NormalizeConnectorCatalogItems $normalize,
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

        [$result, $complete] = $this->readAllPages($provider, $instance, $library);

        return $this->recordResult($instance, $connectorKey, $library, $result, $complete);
    }

    /**
     * Page through the remote a bounded page at a time and aggregate the items into
     * a single result. The loop is bounded three ways so it can never run away: a
     * hard page count (derived from the cap), a hard item cap, and the remote's own
     * reported total. Duplicate ids across pages are collapsed. A first-page failure
     * fails the whole snapshot; a later-page failure keeps what was captured and
     * marks the run truncated (an incomplete read must not flag items missing).
     *
     * @return array{0: CatalogSnapshotResult, 1: bool} the aggregate result and whether the read was complete
     */
    private function readAllPages(ConnectorProvider $provider, ConnectorInstance $instance, ConnectorLibrary $library): array
    {
        $secret = $this->secrets->get($instance->secrets_ref);
        $maxPages = (int) ceil(self::MAX_ITEMS_PER_SNAPSHOT / self::PAGE_SIZE);

        /** @var array<string, SnapshotItem> $collected */
        $collected = [];
        $remoteTotal = null;
        $httpStatus = null;
        $offset = 0;
        $pagesRead = 0;
        $reachedCap = false;
        $partialFailure = false;

        for ($page = 0; $page < $maxPages; $page++) {
            $result = $provider->snapshotLibraryItems(new CatalogSnapshotRequest(
                baseUrl: $instance->base_url,
                secret: $secret,
                libraryExternalId: $library->external_id,
                libraryType: $library->collection_type,
                limit: self::PAGE_SIZE,
                offset: $offset,
            ));

            $pagesRead++;
            $httpStatus = $result->httpStatus ?? $httpStatus;

            if (!$result->ok) {
                if ($page === 0) {
                    // The very first page failed → the whole snapshot failed.
                    return [$result, false];
                }

                // A later page failed → keep the captured pages, read is incomplete.
                $partialFailure = true;
                break;
            }

            if ($result->totalSeen !== null) {
                $remoteTotal = $result->totalSeen;
            }

            $pageCount = count($result->items);

            foreach ($result->items as $item) {
                $collected[$item->externalId] ??= $item;

                if (count($collected) >= self::MAX_ITEMS_PER_SNAPSHOT) {
                    $reachedCap = true;
                    break;
                }
            }

            $offset += $pageCount;

            if ($reachedCap || $pageCount < self::PAGE_SIZE) {
                break; // hit the cap, or a short page means the remote is exhausted
            }

            if ($remoteTotal !== null && $offset >= $remoteTotal) {
                break; // consumed the full library
            }
        }

        $items = array_values($collected);
        $captured = count($items);

        // Truncated when a page failed, when the remote reports more than we
        // captured, or (when the remote hides its total) when we hit the hard cap.
        $truncated = $partialFailure
            || ($remoteTotal !== null ? $remoteTotal > $captured : $reachedCap);

        $detail = 'Captured '.$captured.' external '.($captured === 1 ? 'item' : 'items')
            .($pagesRead > 1 ? ' across '.$pagesRead.' pages' : '').'.';

        $aggregate = CatalogSnapshotResult::success($items, $detail, $truncated, $remoteTotal ?? $captured, $httpStatus);

        return [$aggregate, !$truncated];
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

    private function recordResult(ConnectorInstance $instance, string $connectorKey, ConnectorLibrary $library, CatalogSnapshotResult $result, bool $complete): ConnectorCatalogSnapshotRun
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
            function () use ($run, $instance, $library, $result, $complete, &$storedCount): void {
                $run->save();

                if (!$result->ok) {
                    return; // A failed read never wipes previously captured items.
                }

                $storedCount = $this->storeItems($instance, $library, $run->id, $result, $complete);
                $run->items_stored_count = $storedCount;
                $run->save();
            },
        );

        $this->reviewTasks->execute($instance, $connectorKey, $issues);

        // V2 C: interpret what we just captured so the catalog is understandable
        // straight after a snapshot. Read-model writes only — still no import.
        if ($result->ok) {
            $this->normalize->execute($instance, $connectorKey, $library);
        }

        return $run;
    }

    /**
     * Upsert the captured items and flag vanished ones. Read-model writes only —
     * no media_items/editions/files, no file operations. `$complete` is false when
     * the read was truncated/partial: an incomplete read must NOT flag the
     * unseen tail as missing, because those items were simply never looked at.
     */
    private function storeItems(ConnectorInstance $instance, ConnectorLibrary $library, string $runId, CatalogSnapshotResult $result, bool $complete): int
    {
        $now = Carbon::now();
        $seen = [];
        $rows = [];

        foreach ($result->items as $item) {
            $seen[] = $item->externalId;
            $rows[] = [
                // Only used when the row is new; ON CONFLICT keeps the existing id.
                'id' => (string) Str::ulid(),
                'connector_instance_id' => $instance->id,
                'connector_library_id' => $library->id,
                'snapshot_run_id' => $runId,
                'external_id' => $item->externalId,
                'external_parent_id' => $item->externalParentId,
                'media_kind' => $item->kind->value,
                'title' => $item->title,
                'sort_title' => $item->sortTitle,
                'original_title' => $item->originalTitle,
                'year' => $item->year,
                'index_number' => $item->indexNumber,
                'parent_index_number' => $item->parentIndexNumber,
                'runtime_seconds' => $item->runtimeSeconds,
                'external_updated_at' => $this->parseTimestamp($item->externalUpdatedAt),
                // first_seen_at is insert-only (not in UPDATE_COLUMNS), so a
                // re-captured item keeps the moment it was first ever seen.
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'missing_since' => null,
                'is_present' => true,
                'metadata' => json_encode($item->metadata),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk upsert in chunks: a capped 5000-item snapshot would otherwise cost
        // two queries per item. Conflict target = the (instance, external_id) unique.
        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            ConnectorCatalogItem::query()->upsert($chunk, ['connector_instance_id', 'external_id'], self::UPDATE_COLUMNS);
        }

        // Items previously captured for THIS library but not seen now → mark missing.
        // Only on a COMPLETE read: a truncated/partial read never saw the full
        // library, so it must not flag the un-read tail as missing.
        if ($complete) {
            ConnectorCatalogItem::query()
                ->where('connector_instance_id', $instance->id)
                ->where('connector_library_id', $library->id)
                ->where('is_present', true)
                ->when($seen !== [], fn ($query) => $query->whereNotIn('external_id', $seen))
                ->update(['is_present' => false, 'missing_since' => $now]);
        }

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
                'The library holds more items than the '.self::MAX_ITEMS_PER_SNAPSHOT.'-item snapshot cap; the read captured a bounded subset.',
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
            'captured_count' => $stored,
            'remote_total' => $seen,
            'cap' => self::MAX_ITEMS_PER_SNAPSHOT,
            'truncated' => $truncated,
            'http_status' => $httpStatus,
            'outcome' => $outcome,
            'issues' => array_map(static fn (CatalogIssue $i): array => $i->toArray(), $issues),
            'note' => 'Read-only snapshot. No media import. No files are copied, moved, deleted or renamed.',
        ];
    }
}
