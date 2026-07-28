<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Import\ImportExecutionReason;
use App\Connectors\Sdk\Models\MediaImportExecution;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Review\CreateReviewTask;
use App\Core\Review\CreateReviewTaskInput;
use App\Core\Review\ReviewTask;
use Illuminate\Database\DatabaseManager;

/**
 * Reconciles the single "media_import_execution" review task for one PLAN (V2 E).
 *
 * One task per plan, not one per refused line: an import that declined 400 episodes
 * for a missing parent produces one actionable task carrying the reason codes, the
 * counts and a few example titles. Re-importing the same plan supersedes the
 * previous task before opening a new one, and an execution with nothing to report
 * dismisses the lingering task instead of opening another — the queue heals itself.
 *
 * Evidence carries only reason codes, counts, normalized display titles and ids:
 * never a secret, a token, a raw API payload, a stack trace or a local path.
 * Creating a task imports nothing and touches no file.
 */
final class CreateMediaImportReviewTasks extends AuditableAction
{
    public const TASK_TYPE = 'media_import_execution';

    public const SUBJECT_TYPE = 'media_import_execution';

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly CreateReviewTask $createReviewTask,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(MediaImportExecution $execution, MediaImportPlan $plan): ?ReviewTask
    {
        // A fresh run supersedes the previous verdict for the same plan.
        $this->dismissOpenTasks($plan->id, $execution->id);

        $reasons = $this->reviewWorthyReasons($execution);

        if ($reasons === []) {
            return null;
        }

        $blocking = false;

        foreach ($reasons as $reason) {
            $blocking = $blocking || ImportExecutionReason::from($reason['code'])->isBlocking();
        }

        return $this->createReviewTask->execute(new CreateReviewTaskInput(
            taskType: self::TASK_TYPE,
            subjectType: self::SUBJECT_TYPE,
            subjectId: $execution->id,
            createdBy: 'import-execution:'.$plan->scope_type,
            priority: $blocking ? 'high' : 'normal',
            evidence: [
                'plan_id' => $plan->id,
                'execution_id' => $execution->id,
                'scope' => $plan->scope_type,
                'connector' => is_string($plan->summary['connector'] ?? null) ? $plan->summary['connector'] : null,
                'execution_status' => $execution->status,
                'counts' => [
                    'imported' => $execution->imported_count,
                    'already_existing' => $execution->already_existing_count,
                    'skipped' => $execution->skipped_count,
                    'failed' => $execution->failed_count,
                ],
                'issues' => $reasons,
                'note' => 'Internal import only. No files were copied, moved, deleted or renamed and nothing was written to the media servers.',
            ],
        ));
    }

    /**
     * The reasons worth asking a human about, read back from the execution's own
     * sanitized summary and re-validated against the enum, so nothing unexpected
     * can reach the review queue.
     *
     * @return list<array{code: string, message: string, item_count: int, examples: list<string>}>
     */
    private function reviewWorthyReasons(MediaImportExecution $execution): array
    {
        $stored = $execution->summary['reasons'] ?? [];

        if (!is_array($stored)) {
            return [];
        }

        $worthy = array_map(
            static fn (ImportExecutionReason $reason): string => $reason->value,
            ImportExecutionReason::reviewWorthy(),
        );

        $views = [];

        foreach ($stored as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $code = is_string($entry['code'] ?? null) ? $entry['code'] : '';
            $reason = ImportExecutionReason::tryFrom($code);

            if ($reason === null || !in_array($reason->value, $worthy, true)) {
                continue;
            }

            $examples = [];

            foreach (is_array($entry['examples'] ?? null) ? $entry['examples'] : [] as $example) {
                if (is_string($example)) {
                    $examples[] = $example;
                }
            }

            $views[] = [
                'code' => $reason->value,
                'message' => $reason->message(),
                'item_count' => is_numeric($entry['item_count'] ?? null) ? (int) $entry['item_count'] : 0,
                'examples' => $examples,
            ];
        }

        return $views;
    }

    private function dismissOpenTasks(string $planId, string $exceptExecutionId): void
    {
        $tasks = ReviewTask::query()
            ->where('task_type', self::TASK_TYPE)
            ->where('subject_type', self::SUBJECT_TYPE)
            ->where('evidence->plan_id', $planId)
            ->where('subject_id', '!=', $exceptExecutionId)
            ->whereIn('status', ['open', 'in_review'])
            ->get();

        foreach ($tasks as $task) {
            $task->status = 'dismissed';
            $task->resolution = ['reason' => 'superseded_by_newer_import_execution'];
            $task->resolved_at = now();

            $this->transact(
                $task,
                new AuditChange('media_import.review_superseded', [], ['plan_id' => $planId]),
                static fn (): bool => $task->save(),
            );
        }
    }
}
