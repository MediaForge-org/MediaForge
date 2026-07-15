<?php

declare(strict_types=1);

namespace App\Connectors\Sdk;

use App\Connectors\Sdk\Actions\CreateSyncReviewTasks;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Core\Review\ReviewTask;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read model for the Review Center (V1 G). Decorates stored review tasks with
 * connector context (key/label) for display — reads DB state only, never the
 * network, never a secret. Bounded queries by design; V1 G stays small.
 */
final class ReviewCenterCatalog
{
    private const OPEN_STATUSES = ['open', 'in_review'];

    private const RESOLVED_STATUSES = ['resolved', 'dismissed', 'expired'];

    private const MAX_OPEN_TASKS = 100;

    private const MAX_RESOLVED_TASKS = 20;

    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /** @return list<array<string, mixed>> */
    public function openTasks(): array
    {
        return $this->decorate(
            ReviewTask::query()
                ->whereIn('status', self::OPEN_STATUSES)
                ->latest('created_at')
                ->limit(self::MAX_OPEN_TASKS)
                ->get(),
        );
    }

    /** @return list<array<string, mixed>> */
    public function recentResolvedTasks(): array
    {
        return $this->decorate(
            ReviewTask::query()
                ->whereIn('status', self::RESOLVED_STATUSES)
                ->latest('resolved_at')
                ->limit(self::MAX_RESOLVED_TASKS)
                ->get(),
        );
    }

    public function openTaskCount(): int
    {
        return ReviewTask::query()->whereIn('status', self::OPEN_STATUSES)->count();
    }

    /**
     * Overall Review Center status. `attention_required` means future sync isn't
     * safe to prepare yet (an unhealthy connector, or a blocking/high-priority
     * open task); `warnings` has open items but nothing blocking; otherwise
     * `all_clear`.
     *
     * @param  list<array<string, mixed>>  $connectors
     */
    public function status(array $connectors, int $openTaskCount): string
    {
        foreach ($connectors as $connector) {
            if (($connector['status'] ?? null) === 'unhealthy') {
                return 'attention_required';
            }
        }

        if ($openTaskCount === 0) {
            return 'all_clear';
        }

        $hasHighPriorityOpenTask = ReviewTask::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('priority', 'high')
            ->exists();

        return $hasHighPriorityOpenTask ? 'attention_required' : 'warnings';
    }

    /**
     * @param  Collection<int, ReviewTask>  $tasks
     * @return list<array<string, mixed>>
     */
    private function decorate(Collection $tasks): array
    {
        return array_values($tasks->map(function (ReviewTask $task): array {
            $connectorKey = is_string($task->evidence['connector'] ?? null) ? $task->evidence['connector'] : null;
            $connector = $connectorKey !== null && $this->registry->has($connectorKey)
                ? ['key' => $connectorKey, 'label' => $this->registry->get($connectorKey)->label()]
                : null;

            return [
                'id' => $task->id,
                'task_type' => $task->task_type,
                'subject_type' => $task->subject_type,
                'subject_id' => $task->subject_id,
                'status' => $task->status,
                'priority' => $task->priority,
                'connector' => $connector,
                'issues' => is_array($task->evidence['issues'] ?? null) ? $task->evidence['issues'] : [],
                'created_at' => $task->created_at?->toIso8601String(),
                'resolved_at' => $task->resolved_at?->toIso8601String(),
                'resolution' => $task->resolution,
                'can_manage' => $task->task_type === CreateSyncReviewTasks::TASK_TYPE,
            ];
        })->all());
    }
}
