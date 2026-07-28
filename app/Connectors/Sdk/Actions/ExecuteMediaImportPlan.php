<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Import\ImportableMediaKind;
use App\Connectors\Sdk\Import\ImportEligibility;
use App\Connectors\Sdk\Import\ImportExecutionAction;
use App\Connectors\Sdk\Import\ImportExecutionReason;
use App\Connectors\Sdk\Import\ImportExecutionStatus;
use App\Connectors\Sdk\Import\ImportPlanItemGate;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\MediaExternalMapping;
use App\Connectors\Sdk\Models\MediaImportExecution;
use App\Connectors\Sdk\Models\MediaImportExecutionItem;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Models\MediaImportPlanItem;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Media\MediaItem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * The FIRST INTERNAL IMPORT (V2 E): turns the READY lines of a V2 D plan into real
 * MediaForge database records.
 *
 * WHAT IT WRITES: `media_items` (the canonical foundation catalog), their
 * `media_external_mappings`, one `media_import_executions` row, one
 * `media_import_execution_items` row per plan line it looked at, a deduplicated
 * review task and a sanitized audit entry.
 *
 * WHAT IT NEVER DOES: it copies, moves, deletes or renames NO file and stores NO
 * file path — there is no filesystem call anywhere in this class, and no
 * `media_files` row is created. It sends NO request to Jellyfin or Audiobookshelf:
 * no write, no library refresh, no scan trigger, no network at all. It accepts no
 * match and merges no duplicate.
 *
 * WHAT IT REFUSES: everything the plan did not mark ready. Needs-review, blocked,
 * warning, skipped, duplicate-suspect and unsupported lines are recorded as skipped
 * with a reason, never imported (see ImportPlanItemGate).
 *
 * IDEMPOTENT: the unique (connector_instance_id, external_id) on the mapping table
 * means running the same plan again links what already exists instead of creating a
 * second copy, and never overwrites an existing record.
 *
 * DETERMINISTIC AND BOUNDED: lines are processed in a fixed order (containers before
 * their children, then movies, then audiobooks/books, tie-broken on the monotonic
 * ULID), streamed in chunks, and capped per execution.
 */
final class ExecuteMediaImportPlan extends AuditableAction
{
    /** Hard cap on the plan lines one execution may process. */
    public const MAX_ITEMS_PER_EXECUTION = 5000;

    /** Plan lines loaded (and execution rows inserted) per chunk. */
    private const CHUNK = 500;

    /** Connector keys that map to a known mapping `source_type`. */
    private const SOURCE_TYPES = ['jellyfin', 'audiobookshelf'];

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly ImportPlanItemGate $gate,
        private readonly CreateMediaImportReviewTasks $reviewTasks,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(MediaImportPlan $plan, ?string $createdBy = null): MediaImportExecution
    {
        try {
            $execution = $this->run($plan, $createdBy);
        } catch (Throwable $error) {
            // The transaction is already rolled back, so nothing half-imported
            // survives. Record the failure itself — with a code, never the
            // exception message, which could carry a path or a payload.
            return $this->recordFailure($plan, $createdBy, $error);
        }

        $this->reviewTasks->execute($execution, $plan);

        return $execution;
    }

    /**
     * The whole import in ONE transaction: the execution row, every media item and
     * mapping, every execution line and the audit entry commit together or not at
     * all. There is no state in which a mapping exists without its media item.
     */
    private function run(MediaImportPlan $plan, ?string $createdBy): MediaImportExecution
    {
        $now = Carbon::now();

        $execution = new MediaImportExecution([
            'media_import_plan_id' => $plan->id,
            'status' => ImportExecutionStatus::Empty->value,
            'created_by' => $createdBy,
            'summary' => [],
        ]);
        $execution->id = (string) Str::ulid();

        return $this->db->transaction(function () use ($plan, $execution, $now): MediaImportExecution {
            // Saved first: media_items.created_by_import_execution_id points here.
            $execution->save();

            [$rows, $counts, $reasonCounts, $examples] = $this->importLines($plan, $execution, $now);

            foreach (array_chunk($rows, self::CHUNK) as $batch) {
                MediaImportExecutionItem::query()->insert($batch);
            }

            $status = ImportExecutionStatus::fromCounts(
                $counts['candidates'],
                $counts['imported'],
                $counts['already_existing'],
                $counts['skipped'],
                $counts['failed'],
            );

            $execution->fill([
                'status' => $status->value,
                'imported_count' => $counts['imported'],
                'skipped_count' => $counts['skipped'],
                'already_existing_count' => $counts['already_existing'],
                'failed_count' => $counts['failed'],
                'summary' => $this->summary($plan, $counts, $reasonCounts, $examples),
            ]);

            // Nested in the same transaction (Laravel opens a savepoint), so the
            // audit entry commits atomically with the import it describes.
            $this->transact(
                $execution,
                new AuditChange(
                    $status === ImportExecutionStatus::Empty
                        ? 'media_import.execution_empty'
                        : 'media_import.execution_completed',
                    [
                        'imported' => $counts['imported'],
                        'already_existing' => $counts['already_existing'],
                        'skipped' => $counts['skipped'],
                        'failed' => $counts['failed'],
                    ],
                    [
                        'plan_id' => $plan->id,
                        'execution_id' => $execution->id,
                        'status' => $status->value,
                        'scope' => $plan->scope_type,
                        'reason_codes' => array_keys($reasonCounts),
                        'note' => 'Internal import only. No files were copied, moved, deleted or renamed and nothing was written to the media servers.',
                    ],
                ),
                static fn (): bool => $execution->save(),
            );

            return $execution;
        });
    }

    /**
     * Walk the plan once, in import order, and act on every line.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, int>, 2: array<string, int>, 3: array<string, list<string>>}
     */
    private function importLines(MediaImportPlan $plan, MediaImportExecution $execution, Carbon $now): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        $counts = ['candidates' => 0, 'imported' => 0, 'already_existing' => 0, 'skipped' => 0, 'failed' => 0];
        /** @var array<string, int> $reasonCounts */
        $reasonCounts = [];
        /** @var array<string, list<string>> $examples */
        $examples = [];

        // target_key => media_item id, for parents created earlier in THIS run.
        /** @var array<string, string> $createdTargets */
        $createdTargets = [];

        MediaImportPlanItem::query()
            ->where('media_import_plan_id', $plan->id)
            ->with(['catalogItem', 'normalization', 'instance:id,connector_key'])
            ->orderByRaw($this->importOrder())
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($items) use ($plan, $execution, $now, &$rows, &$counts, &$reasonCounts, &$examples, &$createdTargets): bool {
                foreach ($items as $item) {
                    if (count($rows) >= self::MAX_ITEMS_PER_EXECUTION) {
                        return false;
                    }

                    $eligibility = $this->gate->evaluate($item);

                    if ($eligibility->importable) {
                        $counts['candidates']++;
                    }

                    $outcome = $eligibility->importable
                        ? $this->importOne($item, $eligibility, $plan, $execution, $now, $createdTargets)
                        : ['action' => $eligibility->skipAction ?? ImportExecutionAction::SkippedNotReady, 'reasons' => $eligibility->reasons, 'media_item_id' => null];

                    /** @var ImportExecutionAction $action */
                    $action = $outcome['action'];
                    /** @var list<ImportExecutionReason> $reasons */
                    $reasons = $outcome['reasons'];

                    $this->countOutcome($action, $counts);

                    foreach ($reasons as $reason) {
                        $reasonCounts[$reason->value] = ($reasonCounts[$reason->value] ?? 0) + 1;
                        $seen = $examples[$reason->value] ?? [];

                        if (count($seen) < 3) {
                            $seen[] = $item->target_title;
                            $examples[$reason->value] = $seen;
                        }
                    }

                    $rows[] = [
                        'id' => (string) Str::ulid(),
                        'media_import_execution_id' => $execution->id,
                        'media_import_plan_item_id' => $item->id,
                        'media_item_id' => $outcome['media_item_id'],
                        'connector_catalog_item_id' => $item->connector_catalog_item_id,
                        'title' => $item->target_title,
                        'media_kind' => $item->planned_kind,
                        'action' => $action->value,
                        'status' => $action->status()->value,
                        'reason_codes' => json_encode(array_map(
                            static fn (ImportExecutionReason $reason): string => $reason->value,
                            $reasons,
                        )),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                return true;
            });

        return [$rows, $counts, $reasonCounts, $examples];
    }

    /**
     * Import one ready line: link it if its external item is already imported,
     * otherwise create the record and its mapping.
     *
     * @param  array<string, string>  $createdTargets
     * @return array{action: ImportExecutionAction, reasons: list<ImportExecutionReason>, media_item_id: string|null}
     */
    private function importOne(
        MediaImportPlanItem $item,
        ImportEligibility $eligibility,
        MediaImportPlan $plan,
        MediaImportExecution $execution,
        Carbon $now,
        array &$createdTargets,
    ): array {
        $kind = $eligibility->kind;
        $catalogItem = $item->catalogItem;

        // The plan line outlived the captured item it was planned from. Without an
        // external identity there is nothing to key a mapping on, so we refuse.
        if ($kind === null || $catalogItem === null) {
            return ['action' => ImportExecutionAction::Failed, 'reasons' => [ImportExecutionReason::MissingSourceItem], 'media_item_id' => null];
        }

        $existing = MediaExternalMapping::query()
            ->where('connector_instance_id', $item->connector_instance_id)
            ->where('external_id', $catalogItem->external_id)
            ->first();

        if ($existing !== null) {
            // Already imported. Link it, never touch the existing record — a user
            // may have edited it since, and this import owns none of that.
            $this->rememberTarget($item, $existing->media_item_id, $createdTargets);

            return ['action' => ImportExecutionAction::LinkedExisting, 'reasons' => [ImportExecutionReason::AlreadyImported], 'media_item_id' => $existing->media_item_id];
        }

        $parentId = null;

        if ($kind->requiresParent()) {
            $resolved = $this->resolveParent($item, $catalogItem, $kind, $createdTargets);

            if ($resolved['reason'] !== null) {
                return ['action' => ImportExecutionAction::SkippedNotReady, 'reasons' => [$resolved['reason']], 'media_item_id' => null];
            }

            $parentId = $resolved['media_item_id'];
        }

        return $this->createRecord($item, $catalogItem, $kind, $parentId, $plan, $execution, $now, $createdTargets);
    }

    /**
     * Create the media item plus its mapping. A concurrent run that got there first
     * trips the unique index; we take that as "already imported" rather than as an
     * error, so two parallel executions can never produce two records.
     *
     * @param  array<string, string>  $createdTargets
     * @return array{action: ImportExecutionAction, reasons: list<ImportExecutionReason>, media_item_id: string|null}
     */
    private function createRecord(
        MediaImportPlanItem $item,
        ConnectorCatalogItem $catalogItem,
        ImportableMediaKind $kind,
        ?string $parentId,
        MediaImportPlan $plan,
        MediaImportExecution $execution,
        Carbon $now,
        array &$createdTargets,
    ): array {
        $connectorKey = $item->instance?->connector_key;
        $normalization = $item->normalization;

        try {
            return $this->db->transaction(function () use ($item, $catalogItem, $kind, $parentId, $plan, $execution, $now, $connectorKey, $normalization, &$createdTargets): array {
                $mediaItem = new MediaItem([
                    'library_id' => null, // a connector import is not bound to a local library
                    'media_type' => $kind->mediaType(),
                    'parent_id' => $parentId,
                    'sort_index' => $item->target_episode_number ?? $item->target_season_number,
                    'title' => $item->target_title,
                    'sort_title' => $normalization?->normalized_sort_title,
                    'year' => $item->target_year,
                    'season_number' => $item->target_season_number,
                    'episode_number' => $item->target_episode_number,
                    'runtime_ms' => $this->runtimeMs($normalization?->runtime_seconds),
                    'source' => 'connector_import',
                    'created_by_import_execution_id' => $execution->id,
                    // Sanitized and minimal on purpose: provenance hints only, never
                    // a raw API payload, a secret or a path.
                    'metadata' => [
                        'connector' => $connectorKey,
                        'source_kind' => $catalogItem->media_kind,
                        'import_plan_id' => $plan->id,
                    ],
                ]);
                $mediaItem->save();

                MediaExternalMapping::query()->create([
                    'media_item_id' => $mediaItem->id,
                    'connector_instance_id' => $item->connector_instance_id,
                    'connector_library_id' => $item->connector_library_id,
                    'connector_catalog_item_id' => $catalogItem->id,
                    'connector_catalog_item_normalization_id' => $item->connector_catalog_item_normalization_id,
                    'external_id' => $catalogItem->external_id,
                    'external_parent_id' => $catalogItem->external_parent_id,
                    'source_type' => in_array($connectorKey, self::SOURCE_TYPES, true) ? $connectorKey : 'unknown',
                    'source_kind' => $catalogItem->media_kind,
                    'imported_at' => $now,
                ]);

                $this->rememberTarget($item, $mediaItem->id, $createdTargets);

                return ['action' => ImportExecutionAction::Created, 'reasons' => [ImportExecutionReason::ImportedFromPlan], 'media_item_id' => $mediaItem->id];
            });
        } catch (UniqueConstraintViolationException) {
            $winner = MediaExternalMapping::query()
                ->where('connector_instance_id', $item->connector_instance_id)
                ->where('external_id', $catalogItem->external_id)
                ->first();

            if ($winner === null) {
                return ['action' => ImportExecutionAction::Failed, 'reasons' => [ImportExecutionReason::ConflictExistingMapping], 'media_item_id' => null];
            }

            $this->rememberTarget($item, $winner->media_item_id, $createdTargets);

            return ['action' => ImportExecutionAction::LinkedExisting, 'reasons' => [ImportExecutionReason::AlreadyImported], 'media_item_id' => $winner->media_item_id];
        }
    }

    /**
     * Find the one record this line must hang under — or refuse.
     *
     * Two exact sources are consulted, never a title guess:
     *   (a) the external parent id, resolved through an existing mapping. This is
     *       the strongest signal and works across executions.
     *   (b) the plan's own `target_parent_key`, matched against what THIS run
     *       created a moment ago (containers are imported before their children).
     *
     * Candidates are filtered by the REQUIRED KIND before they are counted, and
     * that ordering matters. A connector commonly reports an episode's parent as
     * the series while the plan's parent key names the season; those are not two
     * competing answers, because only one of them is a season at all. Judging
     * ambiguity before checking the shape would refuse a perfectly determinate
     * parent.
     *
     * So: keep only candidates of the right kind, then — none means the parent is
     * missing, more than one means it is genuinely ambiguous. Either way we stop.
     * Nothing is attached on a maybe.
     *
     * @param  array<string, string>  $createdTargets
     * @return array{media_item_id: string|null, reason: ImportExecutionReason|null}
     */
    private function resolveParent(
        MediaImportPlanItem $item,
        ConnectorCatalogItem $catalogItem,
        ImportableMediaKind $kind,
        array $createdTargets,
    ): array {
        $required = $kind->requiredParent();

        if ($required === null) {
            return ['media_item_id' => null, 'reason' => null];
        }

        $candidates = [];

        if ($catalogItem->external_parent_id !== null) {
            $mapped = MediaExternalMapping::query()
                ->where('connector_instance_id', $item->connector_instance_id)
                ->where('external_id', $catalogItem->external_parent_id)
                ->value('media_item_id');

            if (is_string($mapped)) {
                $candidates[$mapped] = true;
            }
        }

        if ($item->target_parent_key !== null && isset($createdTargets[$item->target_parent_key])) {
            $candidates[$createdTargets[$item->target_parent_key]] = true;
        }

        if ($candidates === []) {
            return ['media_item_id' => null, 'reason' => ImportExecutionReason::MissingParent];
        }

        // A season hangs under a show, an episode under a season. Anything of
        // another kind is simply not a parent, so it never competes.
        $valid = MediaItem::query()
            ->whereIn('id', array_keys($candidates))
            ->where('media_type', $required->mediaType())
            ->orderBy('id')
            ->get();

        if ($valid->count() > 1) {
            return ['media_item_id' => null, 'reason' => ImportExecutionReason::AmbiguousParent];
        }

        $parent = $valid->first();

        if ($parent === null) {
            return ['media_item_id' => null, 'reason' => ImportExecutionReason::MissingParent];
        }

        return ['media_item_id' => $parent->id, 'reason' => null];
    }

    /** @param array<string, string> $createdTargets */
    private function rememberTarget(MediaImportPlanItem $item, string $mediaItemId, array &$createdTargets): void
    {
        if ($item->target_key !== null) {
            $createdTargets[$item->target_key] = $mediaItemId;
        }
    }

    /** @param array<string, int> $counts */
    private function countOutcome(ImportExecutionAction $action, array &$counts): void
    {
        match ($action) {
            ImportExecutionAction::Created => $counts['imported']++,
            ImportExecutionAction::LinkedExisting => $counts['already_existing']++,
            ImportExecutionAction::Failed => $counts['failed']++,
            default => $counts['skipped']++,
        };
    }

    /** Runtime is stored in milliseconds by the foundation catalog. */
    private function runtimeMs(?int $seconds): ?int
    {
        return $seconds !== null && $seconds > 0 ? $seconds * 1000 : null;
    }

    /**
     * Deterministic import order: containers before the things that hang under
     * them, so a single pass can always find its parents. Built from the enum so
     * the SQL can never drift from the ranking it mirrors.
     */
    private function importOrder(): string
    {
        $cases = [];

        foreach (ImportableMediaKind::cases() as $kind) {
            $cases[] = "WHEN '{$kind->value}' THEN {$kind->importRank()}";
        }

        return 'CASE planned_kind '.implode(' ', $cases).' ELSE 99 END';
    }

    /**
     * The sanitized execution summary stored on the row and rendered in the UI.
     *
     * @param  array<string, int>  $counts
     * @param  array<string, int>  $reasonCounts
     * @param  array<string, list<string>>  $examples
     * @return array<string, mixed>
     */
    private function summary(MediaImportPlan $plan, array $counts, array $reasonCounts, array $examples): array
    {
        $reasons = [];

        foreach ($reasonCounts as $code => $count) {
            $reason = ImportExecutionReason::tryFrom($code);

            if ($reason !== null) {
                $reasons[] = [
                    'code' => $reason->value,
                    'message' => $reason->message(),
                    'item_count' => $count,
                    'examples' => $examples[$code] ?? [],
                ];
            }
        }

        return [
            'plan_id' => $plan->id,
            'scope' => $plan->scope_type,
            'connector' => is_string($plan->summary['connector'] ?? null) ? $plan->summary['connector'] : null,
            'candidate_count' => $counts['candidates'],
            'cap' => self::MAX_ITEMS_PER_EXECUTION,
            'reasons' => $reasons,
            'note' => 'Internal import only. No files are copied, moved, deleted or renamed.',
        ];
    }

    /**
     * Record that the run aborted. Written outside the rolled-back transaction and
     * deliberately free of the exception's message: a stack trace or a path must
     * never reach the database or the UI.
     */
    private function recordFailure(MediaImportPlan $plan, ?string $createdBy, Throwable $error): MediaImportExecution
    {
        $execution = new MediaImportExecution([
            'media_import_plan_id' => $plan->id,
            'status' => ImportExecutionStatus::Failed->value,
            'imported_count' => 0,
            'skipped_count' => 0,
            'already_existing_count' => 0,
            'failed_count' => 0,
            'created_by' => $createdBy,
            'summary' => [
                'plan_id' => $plan->id,
                'scope' => $plan->scope_type,
                'candidate_count' => 0,
                'cap' => self::MAX_ITEMS_PER_EXECUTION,
                'reasons' => [[
                    'code' => ImportExecutionReason::ImportFailedItems->value,
                    'message' => ImportExecutionReason::ImportFailedItems->message(),
                    'item_count' => 0,
                    'examples' => [],
                ]],
                // The class name is a safe, useful hint; the message is not.
                'error_type' => class_basename($error),
                'note' => 'The import was rolled back. Nothing was created and no file was touched.',
            ],
        ]);

        return $this->transact(
            $execution,
            new AuditChange('media_import.execution_failed', [], [
                'plan_id' => $plan->id,
                'status' => ImportExecutionStatus::Failed->value,
                'error_type' => class_basename($error),
                'note' => 'Rolled back. No media was created and no file was touched.',
            ]),
            function () use ($execution): MediaImportExecution {
                $execution->save();

                return $execution;
            },
        );
    }
}
