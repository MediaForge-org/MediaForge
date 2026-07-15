<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Review\ReviewTask;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/**
 * V1 G: undoes an accidental manual dismiss from the Review Center. Scoped to
 * connector_sync — see {@see DismissSyncReviewTask} for why this lives here
 * rather than as a generic resolver.
 */
final class ReopenSyncReviewTask extends AuditableAction
{
    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(ReviewTask $task): ReviewTask
    {
        if ($task->task_type !== CreateSyncReviewTasks::TASK_TYPE) {
            throw new InvalidArgumentException('ReopenSyncReviewTask only accepts connector_sync review tasks.');
        }

        $task->status = 'open';
        $task->resolution = null;
        $task->resolved_by = null;
        $task->resolved_at = null;

        return $this->transact(
            $task,
            new AuditChange(
                'review.reopened',
                ['status' => 'open'],
                ['task_type' => $task->task_type, 'subject_id' => $task->subject_id],
            ),
            function () use ($task): ReviewTask {
                $task->save();

                return $task;
            },
        );
    }
}
