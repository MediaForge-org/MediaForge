<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Review\ReviewTask;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * V1 G: lets the operator manually acknowledge a `connector_sync` review task from
 * the Review Center, without waiting for a clean dry run to self-heal it. Mirrors
 * the same "dismissed" semantics {@see CreateSyncReviewTasks::dismissOpenTask()}
 * already uses. Scoped to connector_sync — review_tasks.php documents that a task
 * is resolved by its owning module's action, never a generic resolver.
 */
final class DismissSyncReviewTask extends AuditableAction
{
    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(ReviewTask $task, string $resolvedByUserId): ReviewTask
    {
        if ($task->task_type !== CreateSyncReviewTasks::TASK_TYPE) {
            throw new InvalidArgumentException('DismissSyncReviewTask only accepts connector_sync review tasks.');
        }

        $task->status = 'dismissed';
        $task->resolution = ['reason' => 'dismissed_by_user'];
        $task->resolved_by = $resolvedByUserId;
        $task->resolved_at = Carbon::now();

        return $this->transact(
            $task,
            new AuditChange(
                'review.dismissed',
                ['status' => 'dismissed'],
                ['task_type' => $task->task_type, 'subject_id' => $task->subject_id],
            ),
            function () use ($task): ReviewTask {
                $task->save();

                return $task;
            },
        );
    }
}
