<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\Actions\CreateSyncReviewTasks;
use App\Connectors\Sdk\ConnectorCatalog;
use App\Core\Review\ReviewTask;
use Inertia\Inertia;
use Inertia\Response;

/**
 * V1 F: read-only Sync Foundation overview. Aggregates each connector's stored
 * sync state (selected libraries, last dry run, attention) plus the open sync
 * review tasks. No network calls, no secrets — this only renders saved state.
 */
final class SyncController extends Controller
{
    public function index(ConnectorCatalog $catalog): Response
    {
        $reviewTasks = ReviewTask::query()
            ->where('task_type', CreateSyncReviewTasks::TASK_TYPE)
            ->whereIn('status', ['open', 'in_review'])
            ->latest('created_at')
            ->get()
            ->map(static fn (ReviewTask $task): array => [
                'id' => $task->id,
                'priority' => $task->priority,
                'connector' => is_string($task->evidence['connector'] ?? null) ? $task->evidence['connector'] : null,
                'issues' => is_array($task->evidence['issues'] ?? null) ? $task->evidence['issues'] : [],
            ])
            ->all();

        return Inertia::render('Sync/Index', [
            'connectors' => $catalog->overview(),
            'reviewTasks' => $reviewTasks,
        ]);
    }
}
