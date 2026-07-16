<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Catalog\CatalogIssue;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Review\CreateReviewTask;
use App\Core\Review\CreateReviewTaskInput;
use App\Core\Review\ReviewTask;
use Illuminate\Database\DatabaseManager;

/**
 * Reconciles the single "connector_catalog" review task for a connector instance
 * after a read-only snapshot. When the snapshot found attention items (failed,
 * truncated, unsupported, unhealthy, no selected libraries) it opens (or reuses)
 * one task per instance — the partial unique index dedupes it, so repeated
 * snapshots never flood the queue. A clean snapshot dismisses any lingering open
 * task, so the queue is self-healing. Evidence carries only issue codes, messages
 * and recommended actions — never a secret.
 */
final class CreateCatalogReviewTasks extends AuditableAction
{
    public const TASK_TYPE = 'connector_catalog';

    public const SUBJECT_TYPE = 'connector_instance';

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly CreateReviewTask $createReviewTask,
    ) {
        parent::__construct($audit, $db);
    }

    /**
     * @param  list<CatalogIssue>  $issues
     */
    public function execute(ConnectorInstance $instance, string $connectorKey, array $issues): ?ReviewTask
    {
        if ($issues === []) {
            $this->dismissOpenTask($instance);

            return null;
        }

        $blocking = array_filter($issues, static fn (CatalogIssue $issue): bool => $issue->blocking) !== [];

        return $this->createReviewTask->execute(new CreateReviewTaskInput(
            taskType: self::TASK_TYPE,
            subjectType: self::SUBJECT_TYPE,
            subjectId: $instance->id,
            createdBy: "connector:{$connectorKey}",
            priority: $blocking ? 'high' : 'normal',
            evidence: [
                'connector' => $connectorKey,
                'issues' => array_map(static fn (CatalogIssue $issue): array => $issue->toArray(), $issues),
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
        $task->resolution = ['reason' => 'snapshot_clean'];
        $task->resolved_at = now();

        $this->transact(
            $task,
            new AuditChange('connector.catalog_review_cleared', [], ['subject_id' => $instance->id]),
            fn (): bool => $task->save(),
        );
    }
}
