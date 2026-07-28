<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Import\DuplicateIdentity;
use App\Connectors\Sdk\Import\ImportPlanItemStatus;
use App\Connectors\Sdk\Import\ImportPlannedAction;
use App\Connectors\Sdk\Import\ImportPlanReason;
use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\Import\ImportPlanStatus;
use App\Connectors\Sdk\Import\PlanCatalogItemImport;
use App\Connectors\Sdk\Import\PlannedImportItem;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Models\MediaImportPlanItem;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds one import DRY RUN (V2 D) from what is already stored: the captured
 * catalog items, their normalized reading, and the duplicate suspicion the match
 * preview derives from the same rows.
 *
 * WHAT IT WRITES: `media_import_plans`, `media_import_plan_items`, one deduplicated
 * review task per affected connector, and one sanitized audit entry.
 *
 * WHAT IT NEVER WRITES: `media_items`, `media_editions`, `media_files`, or any real
 * library/playback/metadata table. It performs NO file operation — nothing is
 * copied, moved, deleted or renamed, and no path is even planned. It sends NO
 * request to Jellyfin/Audiobookshelf and changes nothing there. It accepts NO
 * match and merges NO duplicate. V2 D ends in a decision, not an action; the first
 * real internal import arrives in V2 E.
 *
 * Bounded and deterministic: items are streamed by ULID in chunks, a hard cap
 * limits one plan, and the pure PlanCatalogItemImport makes the same stored input
 * always produce the same plan.
 */
final class CreateMediaImportPlan extends AuditableAction
{
    /** Hard cap on the items one plan may hold. Beyond it the plan is truncated. */
    public const MAX_ITEMS_PER_PLAN = 5000;

    /** Items loaded (and inserted) per chunk. */
    private const CHUNK = 500;

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly PlanCatalogItemImport $planner,
        private readonly CreateImportPlanReviewTasks $reviewTasks,
    ) {
        parent::__construct($audit, $db);
    }

    /**
     * Plan an import for a scope. `$instance`/`$library` must match `$scope`; the
     * controller resolves and validates both before calling.
     */
    public function execute(
        ImportPlanScope $scope,
        ?ConnectorInstance $instance = null,
        ?ConnectorLibrary $library = null,
        ?string $createdBy = null,
    ): MediaImportPlan {
        $now = Carbon::now();
        $planId = (string) Str::ulid();

        $sourceItemCount = $this->scopedItems($instance, $library)->count();
        $truncated = $sourceItemCount > self::MAX_ITEMS_PER_PLAN;

        [$rows, $counts, $reasonCounts, $examples] = $this->planItems($planId, $instance, $library, $now);

        $status = ImportPlanStatus::fromCounts(
            $sourceItemCount,
            $counts['blocked'],
            $counts['warning'],
            $counts['needs_review'],
            $truncated,
        );

        $plan = new MediaImportPlan([
            'scope_type' => $scope->value,
            'connector_instance_id' => $instance?->id,
            'connector_library_id' => $library?->id,
            'status' => $status->value,
            'source_item_count' => $sourceItemCount,
            'planned_item_count' => count($rows),
            'ready_count' => $counts['ready'],
            'warning_count' => $counts['warning'],
            'blocked_count' => $counts['blocked'],
            'skipped_count' => $counts['skipped'],
            'review_count' => $counts['needs_review'],
            'duplicate_count' => $counts['duplicate'],
            'unsupported_count' => $counts['unsupported'],
            'summary' => $this->summary($scope, $instance, $sourceItemCount, count($rows), $truncated, $reasonCounts),
            'created_by' => $createdBy,
        ]);
        $plan->id = $planId;

        $this->transact(
            $plan,
            new AuditChange('media_import_plan.created', [
                'planned_items' => count($rows),
                'ready' => $counts['ready'],
                'needs_review' => $counts['needs_review'],
                'blocked' => $counts['blocked'],
            ], [
                'scope' => $scope->value,
                'connector' => $instance?->connector_key,
                'library_external_id' => $library?->external_id,
                'status' => $status->value,
                'truncated' => $truncated,
                'reason_codes' => array_keys($reasonCounts),
                'note' => 'Import dry run only. No media was imported and no file was copied, moved, deleted or renamed.',
            ]),
            function () use ($plan, $rows): void {
                $plan->save();

                foreach (array_chunk($rows, self::CHUNK) as $batch) {
                    MediaImportPlanItem::query()->insert($batch);
                }
            },
        );

        $this->reviewTasks->execute($plan, $reasonCounts, $examples, $truncated);

        return $plan;
    }

    /**
     * Walk the scoped catalog once and turn every captured item into a planned row.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, int>, 2: array<string, int>, 3: array<string, list<string>>}
     */
    private function planItems(string $planId, ?ConnectorInstance $instance, ?ConnectorLibrary $library, Carbon $now): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        $counts = array_merge(
            array_fill_keys(ImportPlanItemStatus::values(), 0),
            ['duplicate' => 0, 'unsupported' => 0],
        );
        /** @var array<string, int> $reasonCounts */
        $reasonCounts = [];
        /** @var array<string, list<string>> $examples */
        $examples = [];

        // Which items are the EXTRA copies of something the plan already covers.
        // Computed once over the whole scope, because two captures of the same
        // episode can easily land in different chunks.
        $extraCopies = $this->extraCopies($instance, $library);

        $this->scopedItems($instance, $library)
            ->with('normalization')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($items) use ($planId, $now, $extraCopies, &$rows, &$counts, &$reasonCounts, &$examples): bool {
                foreach ($items as $item) {
                    if (count($rows) >= self::MAX_ITEMS_PER_PLAN) {
                        return false; // (K) bounded: the plan is reported as truncated
                    }

                    $normalization = $item->normalization;

                    $planned = $normalization === null
                        ? $this->planner->planUnnormalized($item->title)
                        : $this->planner->plan($normalization, isset($extraCopies[$item->id]));

                    $counts[$planned->status->value]++;

                    if ($planned->hasReason(ImportPlanReason::DuplicateSuspect)) {
                        $counts['duplicate']++;
                    }

                    if ($planned->action === ImportPlannedAction::SkipUnsupported) {
                        $counts['unsupported']++;
                    }

                    foreach ($planned->reasonCodes() as $code) {
                        $reasonCounts[$code] = ($reasonCounts[$code] ?? 0) + 1;
                        // A handful of titles makes a review task actionable; the
                        // titles are already normalized display strings.
                        $seen = $examples[$code] ?? [];

                        if (count($seen) < 3) {
                            $seen[] = $planned->targetTitle;
                            $examples[$code] = $seen;
                        }
                    }

                    $rows[] = $this->row($planId, $item, $normalization, $planned, $now);
                }

                return true;
            });

        return [$rows, $counts, $reasonCounts, $examples];
    }

    /**
     * The EXTRA copies: items that share a strong duplicate identity
     * (DuplicateIdentity) with another item the same plan covers, and that are not
     * the one copy elected to represent it.
     *
     * Why elect one at all: blocking every copy means the media never arrives, which
     * is the worst possible answer — two captures of one episode should produce one
     * episode, not zero. So the FIRST copy by ULID stays plannable and the rest are
     * flagged for a human. That is a presentation choice, not a merge: nothing is
     * combined, the extra copies keep their own rows, and a later decision can still
     * go the other way.
     *
     * The election is deterministic (lowest ULID wins), so the same catalog always
     * elects the same copy and a repeated dry run plans identically.
     *
     * @return array<string, true> connector_catalog_item id => is an extra copy
     */
    private function extraCopies(?ConnectorInstance $instance, ?ConnectorLibrary $library): array
    {
        /** @var array<string, string> $keyByItem */
        $keyByItem = [];
        /** @var array<string, int> $groupSize */
        $groupSize = [];
        /** @var array<string, string> $elected */
        $elected = [];

        $this->scopedItems($instance, $library)
            ->with('normalization')
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($items) use (&$keyByItem, &$groupSize, &$elected): void {
                foreach ($items as $item) {
                    $normalization = $item->normalization;

                    if ($normalization === null) {
                        continue;
                    }

                    $key = DuplicateIdentity::for($item, $normalization);

                    // null means "never blockable as a duplicate" — weak data, a
                    // generic title, or a kind with no reliable identity.
                    if ($key === null) {
                        continue;
                    }

                    $keyByItem[$item->id] = $key;
                    $groupSize[$key] = ($groupSize[$key] ?? 0) + 1;

                    if (!isset($elected[$key]) || strcmp($item->id, $elected[$key]) < 0) {
                        $elected[$key] = $item->id;
                    }
                }
            });

        /** @var array<string, true> $extra */
        $extra = [];

        foreach ($keyByItem as $itemId => $key) {
            if (($groupSize[$key] ?? 0) > 1 && $elected[$key] !== $itemId) {
                $extra[$itemId] = true;
            }
        }

        return $extra;
    }

    /**
     * One insert-ready plan row. `source_snapshot` is minimal and sanitized on
     * purpose — a few derived hints, never a raw API payload, a secret or a path.
     *
     * @return array<string, mixed>
     */
    private function row(
        string $planId,
        ConnectorCatalogItem $item,
        ?ConnectorCatalogItemNormalization $normalization,
        PlannedImportItem $planned,
        Carbon $now,
    ): array {
        return [
            'id' => (string) Str::ulid(),
            'media_import_plan_id' => $planId,
            'connector_catalog_item_id' => $item->id,
            'connector_catalog_item_normalization_id' => $normalization?->id,
            'connector_instance_id' => $item->connector_instance_id,
            'connector_library_id' => $item->connector_library_id,
            'planned_kind' => $planned->kind->value,
            'planned_action' => $planned->action->value,
            'status' => $planned->status->value,
            'target_key' => $planned->targetKey,
            'target_title' => $planned->targetTitle,
            'target_parent_key' => $planned->targetParentKey,
            'target_year' => $planned->targetYear,
            'target_season_number' => $planned->targetSeasonNumber,
            'target_episode_number' => $planned->targetEpisodeNumber,
            'confidence' => $planned->confidence,
            'reasons' => json_encode($planned->reasonCodes()),
            'source_snapshot' => json_encode([
                'source_kind' => $item->media_kind,
                'normalization_status' => $normalization?->status,
                'normalization_confidence' => $normalization?->confidence,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * The sanitized plan summary stored on the row and rendered in the UI.
     *
     * @param  array<string, int>  $reasonCounts
     * @return array<string, mixed>
     */
    private function summary(
        ImportPlanScope $scope,
        ?ConnectorInstance $instance,
        int $sourceItemCount,
        int $plannedItemCount,
        bool $truncated,
        array $reasonCounts,
    ): array {
        return [
            'scope' => $scope->value,
            'connector' => $instance?->connector_key,
            'source_item_count' => $sourceItemCount,
            'planned_item_count' => $plannedItemCount,
            'cap' => self::MAX_ITEMS_PER_PLAN,
            'truncated' => $truncated,
            'reasons' => $this->reasonViews($reasonCounts),
            'note' => 'Dry run only. No media is imported and no files are copied, moved, deleted or renamed.',
        ];
    }

    /**
     * Reason codes → {code, message, item_count}. Unknown codes are dropped rather
     * than echoed, so nothing unexpected can reach the UI or the review evidence.
     *
     * @param  array<string, int>  $reasonCounts
     * @return list<array{code: string, message: string, item_count: int}>
     */
    private function reasonViews(array $reasonCounts): array
    {
        $views = [];

        foreach ($reasonCounts as $code => $count) {
            $reason = ImportPlanReason::tryFrom($code);

            if ($reason !== null) {
                $views[] = ['code' => $reason->value, 'message' => $reason->message(), 'item_count' => $count];
            }
        }

        return $views;
    }

    /**
     * The captured items a plan may consider: currently present only. A vanished
     * item is never planned for import — it is not there to import.
     *
     * @return Builder<ConnectorCatalogItem>
     */
    private function scopedItems(?ConnectorInstance $instance, ?ConnectorLibrary $library): Builder
    {
        $instanceId = $instance?->id;
        $libraryId = $library?->id;

        return ConnectorCatalogItem::query()
            ->where('is_present', true)
            ->when($instanceId !== null, fn ($query) => $query->where('connector_instance_id', $instanceId))
            ->when($libraryId !== null, fn ($query) => $query->where('connector_library_id', $libraryId));
    }
}
