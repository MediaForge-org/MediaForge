<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Sync\SyncIssue;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Review\CreateReviewTask;
use App\Core\Review\CreateReviewTaskInput;
use App\Core\Review\ReviewTask;
use Illuminate\Database\DatabaseManager;

/**
 * Reconciles the single "connector_sync" review task for a connector instance
 * after a dry run. When the dry run found attention items it opens (or reuses) one
 * task per instance — the partial unique index dedupes it, so repeated runs never
 * flood the queue. When the dry run came back clean it dismisses any lingering
 * open task, so the queue is self-healing. The evidence carries only issue codes,
 * messages and recommended actions — never a secret.
 */
final class CreateSyncReviewTasks extends AuditableAction
{
    public const TASK_TYPE = 'connector_sync';

    public const SUBJECT_TYPE = 'connector_instance';

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly CreateReviewTask $createReviewTask,
    ) {
        parent::__construct($audit, $db);
    }

    /**
     * @param  list<SyncIssue>  $issues
     */
    public function execute(ConnectorInstance $instance, string $connectorKey, array $issues): ?ReviewTask
    {
        if ($issues === []) {
            $this->dismissOpenTask($instance);

            return null;
        }

        $blocking = array_filter($issues, static fn (SyncIssue $issue): bool => $issue->blocking) !== [];

        return $this->createReviewTask->execute(new CreateReviewTaskInput(
            taskType: self::TASK_TYPE,
            subjectType: self::SUBJECT_TYPE,
            subjectId: $instance->id,
            createdBy: "connector:{$connectorKey}",
            priority: $blocking ? 'high' : 'normal',
            evidence: [
                'connector' => $connectorKey,
                'issues' => array_map(static fn (SyncIssue $issue): array => $issue->toArray(), $issues),
            ],
        ));
    }

    private function dismissOpenTask(ConnectorInstance $instance): void
    {
        $task = ReviewTask::query()
            ->where('task_type', self::TASK_TYPE)
            ->where('subject_type', self::SUBJECT_TYPE)
            ->where('subject_id', $instance->id)
            ->whereIn('status', ['open', 'in_review'])
            ->first();

        if ($task === null) {
            return;
        }

        $task->status = 'dismissed';
        $task->resolution = ['reason' => 'dry_run_clean'];
        $task->resolved_at = now();

        $this->transact(
            $task,
            new AuditChange('connector.sync_review_cleared', [], ['subject_id' => $instance->id]),
            fn (): bool => $task->save(),
        );
    }
}
