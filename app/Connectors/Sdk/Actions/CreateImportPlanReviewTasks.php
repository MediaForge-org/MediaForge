<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Import\ImportPlanReason;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Review\CreateReviewTask;
use App\Core\Review\CreateReviewTaskInput;
use App\Core\Review\ReviewTask;
use Illuminate\Database\DatabaseManager;

/**
 * Reconciles the single "media_import_plan" review task for one dry-run SCOPE
 * (V2 D).
 *
 * One task per plan, not one per broken item: a library with 400 unplannable
 * episodes produces one actionable task carrying the reason codes, their counts
 * and a few example titles. Re-running the dry run for the same scope dismisses
 * the previous task before opening a new one, so repeated runs never flood the
 * queue, and a plan with nothing to review dismisses the lingering task instead of
 * opening another — the queue heals itself.
 *
 * Evidence carries only reason codes, counts, normalized display titles and the
 * plan id: never a secret, a token, a raw API payload, a stack trace or a local
 * path. Creating a task imports nothing and touches no file.
 */
final class CreateImportPlanReviewTasks extends AuditableAction
{
    public const TASK_TYPE = 'media_import_plan';

    public const SUBJECT_TYPE = 'media_import_plan';

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly CreateReviewTask $createReviewTask,
    ) {
        parent::__construct($audit, $db);
    }

    /**
     * @param  array<string, int>  $reasonCounts  reason code => number of plan items
     * @param  array<string, list<string>>  $examples  reason code => a few titles
     */
    public function execute(MediaImportPlan $plan, array $reasonCounts, array $examples, bool $truncated): ?ReviewTask
    {
        $scopeKey = $this->scopeKey($plan);

        // A fresh dry run supersedes the previous verdict for the same scope.
        $this->dismissOpenTasks($scopeKey, $plan->id);

        $reasons = $this->reviewWorthyReasons($plan, $reasonCounts, $examples, $truncated);

        if ($reasons === []) {
            return null;
        }

        $blocking = false;

        foreach ($reasons as $reason) {
            $case = ImportPlanReason::from($reason['code']);
            $blocking = $blocking || $case->isBlocking();
        }

        return $this->createReviewTask->execute(new CreateReviewTaskInput(
            taskType: self::TASK_TYPE,
            subjectType: self::SUBJECT_TYPE,
            subjectId: $plan->id,
            createdBy: 'import-plan:'.$plan->scope_type,
            priority: $blocking ? 'high' : 'normal',
            evidence: [
                'plan_id' => $plan->id,
                'scope' => $plan->scope_type,
                'scope_key' => $scopeKey,
                'connector' => is_string($plan->summary['connector'] ?? null) ? $plan->summary['connector'] : null,
                'plan_status' => $plan->status,
                'counts' => [
                    'planned' => $plan->planned_item_count,
                    'ready' => $plan->ready_count,
                    'warning' => $plan->warning_count,
                    'needs_review' => $plan->review_count,
                    'blocked' => $plan->blocked_count,
                    'skipped' => $plan->skipped_count,
                    'duplicate' => $plan->duplicate_count,
                ],
                'issues' => $reasons,
                'note' => 'Import dry run only. No media was imported and no file was copied, moved, deleted or renamed.',
            ],
        ));
    }

    /**
     * The reasons worth asking a human about, plus the two plan-level verdicts
     * (blocked / truncated) that no single item carries on its own.
     *
     * @param  array<string, int>  $reasonCounts
     * @param  array<string, list<string>>  $examples
     * @return list<array{code: string, message: string, item_count: int, examples: list<string>}>
     */
    private function reviewWorthyReasons(MediaImportPlan $plan, array $reasonCounts, array $examples, bool $truncated): array
    {
        /** @var array<string, int> $counts */
        $counts = $reasonCounts;

        if ($plan->blocked_count > 0) {
            $counts[ImportPlanReason::ImportPlanBlocked->value] = $plan->blocked_count;
        }

        if ($truncated) {
            $counts[ImportPlanReason::TruncatedPlan->value] = $plan->source_item_count;
        }

        $views = [];

        foreach (ImportPlanReason::reviewWorthy() as $reason) {
            $count = $counts[$reason->value] ?? 0;

            if ($count <= 0) {
                continue;
            }

            $views[] = [
                'code' => $reason->value,
                'message' => $reason->message(),
                'item_count' => $count,
                'examples' => $examples[$reason->value] ?? [],
            ];
        }

        return $views;
    }

    /** A stable identity for "this scope", so a re-run supersedes its predecessor. */
    private function scopeKey(MediaImportPlan $plan): string
    {
        return implode(':', [
            $plan->scope_type,
            $plan->connector_instance_id ?? '-',
            $plan->connector_library_id ?? '-',
        ]);
    }

    private function dismissOpenTasks(string $scopeKey, string $exceptPlanId): void
    {
        $tasks = ReviewTask::query()
            ->where('task_type', self::TASK_TYPE)
            ->where('subject_type', self::SUBJECT_TYPE)
            ->where('evidence->scope_key', $scopeKey)
            ->where('subject_id', '!=', $exceptPlanId)
            ->whereIn('status', ['open', 'in_review'])
            ->get();

        foreach ($tasks as $task) {
            $task->status = 'dismissed';
            $task->resolution = ['reason' => 'superseded_by_newer_import_plan'];
            $task->resolved_at = now();

            $this->transact(
                $task,
                new AuditChange('media_import_plan.review_superseded', [], ['scope_key' => $scopeKey]),
                static fn (): bool => $task->save(),
            );
        }
    }
}
