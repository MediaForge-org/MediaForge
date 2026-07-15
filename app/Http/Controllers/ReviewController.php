<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Connectors\Sdk\Actions\CreateSyncReviewTasks;
use App\Connectors\Sdk\Actions\DismissSyncReviewTask;
use App\Connectors\Sdk\Actions\ReopenSyncReviewTask;
use App\Connectors\Sdk\ConnectorCatalog;
use App\Connectors\Sdk\ReviewCenterCatalog;
use App\Core\Review\ReviewTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * V1 G: Review Center. Aggregates stored review tasks and connector health into
 * one view — no network calls, no secrets. Dismiss/reopen delegate to the
 * connector-sync-scoped actions; a task type this controller doesn't recognise
 * simply can't be managed here yet (its owning module would add that).
 */
final class ReviewController extends Controller
{
    public function index(ConnectorCatalog $catalog, ReviewCenterCatalog $reviewCenter): Response
    {
        $connectors = $catalog->overview();
        $openTasks = $reviewCenter->openTasks();
        $openTaskCount = count($openTasks);

        return Inertia::render('Review/Index', [
            'connectors' => $connectors,
            'openTasks' => $openTasks,
            'resolvedTasks' => $reviewCenter->recentResolvedTasks(),
            'summary' => [
                'status' => $reviewCenter->status($connectors, $openTaskCount),
                'open_task_count' => $openTaskCount,
            ],
        ]);
    }

    public function dismiss(Request $request, ReviewTask $task, DismissSyncReviewTask $action): RedirectResponse
    {
        if ($task->task_type !== CreateSyncReviewTasks::TASK_TYPE) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $action->execute($task, (string) $user->id);

        return back()->with('success', 'Review task dismissed.');
    }

    public function reopen(ReviewTask $task, ReopenSyncReviewTask $action): RedirectResponse
    {
        if ($task->task_type !== CreateSyncReviewTasks::TASK_TYPE) {
            abort(404);
        }

        $action->execute($task);

        return back()->with('success', 'Review task reopened.');
    }
}
