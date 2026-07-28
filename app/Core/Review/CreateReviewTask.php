<?php

declare(strict_types=1);

namespace App\Core\Review;

use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Creates a review task, deduplicated against the partial unique index: an open
 * task already existing for the same (task_type, subject) is returned as-is.
 *
 * The check-then-insert has a race: two requests (a double-submitted button, a
 * retried request, two tabs) can both pass the "no existing open task" check
 * before either commits. The second insert then hits
 * `review_tasks_no_duplicate_open`. Rather than letting that 500, the insert is
 * caught and the task the other request just created is looked up and reused —
 * the same "loser re-fetches the winner" pattern used for import idempotency in
 * ExecuteMediaImportPlan.
 */
final class CreateReviewTask extends AuditableAction
{
    public function execute(CreateReviewTaskInput $input): ReviewTask
    {
        $existing = $this->findOpen($input);

        if ($existing !== null) {
            return $existing;
        }

        $task = new ReviewTask([
            'task_type' => $input->taskType,
            'subject_type' => $input->subjectType,
            'subject_id' => $input->subjectId,
            'priority' => $input->priority,
            'evidence' => $input->evidence,
            'created_by' => $input->createdBy,
        ]);
        $task->status = 'open';

        try {
            return $this->transact(
                $task,
                new AuditChange('review.created', [
                    'task_type' => $input->taskType,
                    'subject_type' => $input->subjectType,
                    'subject_id' => $input->subjectId,
                ]),
                function () use ($task): ReviewTask {
                    $task->save();

                    return $task;
                },
            );
        } catch (UniqueConstraintViolationException $e) {
            // Lost the race: another request opened the same (task_type, subject)
            // task between our check and our insert. Reuse its task instead of
            // surfacing a 500 — nothing failed from the caller's point of view.
            // (The re-fetch failing too would mean the winner was resolved in the
            // instant between our catch and this query — vanishingly unlikely; the
            // original exception is the honest thing to surface in that case.)
            return $this->findOpen($input) ?? throw $e;
        }
    }

    private function findOpen(CreateReviewTaskInput $input): ?ReviewTask
    {
        return ReviewTask::query()
            ->where('task_type', $input->taskType)
            ->where('subject_type', $input->subjectType)
            ->where('subject_id', $input->subjectId)
            ->whereIn('status', ['open', 'in_review'])
            ->first();
    }
}
