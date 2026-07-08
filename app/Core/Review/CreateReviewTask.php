<?php

declare(strict_types=1);

namespace App\Core\Review;

use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;

/**
 * Creates a review task, deduplicated against the partial unique index: an open
 * task already existing for the same (task_type, subject) is returned as-is.
 */
final class CreateReviewTask extends AuditableAction
{
    public function execute(CreateReviewTaskInput $input): ReviewTask
    {
        $existing = ReviewTask::query()
            ->where('task_type', $input->taskType)
            ->where('subject_type', $input->subjectType)
            ->where('subject_id', $input->subjectId)
            ->whereIn('status', ['open', 'in_review'])
            ->first();

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
    }
}
