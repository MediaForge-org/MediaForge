<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Catalog\NormalizationIssue;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use App\Core\Review\CreateReviewTask;
use App\Core\Review\CreateReviewTaskInput;
use App\Core\Review\ReviewTask;
use Illuminate\Database\DatabaseManager;

/**
 * Reconciles the single "catalog_normalization" review task for a connector
 * instance after a normalization rebuild (V2 C).
 *
 * One task per instance, not one per problem: the issue codes and their counts live
 * in the evidence, so a library with 400 unknown-kind items produces one actionable
 * task instead of 400. The partial unique index dedupes it, so repeated rebuilds
 * never flood the queue, and a clean rebuild dismisses any lingering open task so
 * the queue is self-healing. Evidence carries only codes, counts and short messages
 * — never a secret, a raw payload or a local path.
 */
final class CreateNormalizationReviewTasks extends AuditableAction
{
    public const TASK_TYPE = 'catalog_normalization';

    public const SUBJECT_TYPE = 'connector_instance';

    /** Issues that alone justify asking a human to look. */
    private const BLOCKING = [
        NormalizationIssue::MissingTitle,
        NormalizationIssue::WeakMetadata,
    ];

    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly CreateReviewTask $createReviewTask,
    ) {
        parent::__construct($audit, $db);
    }

    /**
     * @param  array<string, int>  $issueCounts  issue code => number of items affected
     */
    public function execute(ConnectorInstance $instance, string $connectorKey, array $issueCounts, int $needsReviewCount): ?ReviewTask
    {
        if ($issueCounts === []) {
            $this->dismissOpenTask($instance);

            return null;
        }

        $blocking = false;
        $issues = [];

        foreach ($issueCounts as $code => $count) {
            $issue = NormalizationIssue::tryFrom($code);

            if ($issue === null) {
                continue;
            }

            $blocking = $blocking || in_array($issue, self::BLOCKING, true);
            $issues[] = [
                'code' => $issue->value,
                'message' => $issue->message(),
                'item_count' => $count,
            ];
        }

        if ($issues === []) {
            $this->dismissOpenTask($instance);

            return null;
        }

        return $this->createReviewTask->execute(new CreateReviewTaskInput(
            taskType: self::TASK_TYPE,
            subjectType: self::SUBJECT_TYPE,
            subjectId: $instance->id,
            createdBy: "connector:{$connectorKey}",
            priority: $blocking ? 'high' : 'normal',
            evidence: [
                'connector' => $connectorKey,
                'needs_review_count' => $needsReviewCount,
                'issues' => $issues,
                'note' => 'Catalog normalization preview only. No media was imported and no match was accepted.',
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
        $task->resolution = ['reason' => 'normalization_clean'];
        $task->resolved_at = now();

        $this->transact(
            $task,
            new AuditChange('catalog.normalization_review_cleared', [], ['subject_id' => $instance->id]),
            fn (): bool => $task->save(),
        );
    }
}
