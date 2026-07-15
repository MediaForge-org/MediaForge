<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Sync;

/**
 * Lifecycle status of a sync-foundation dry run. In V1 F a run is created already
 * finished: `completed` (clean, ready for future sync), `completed_with_warnings`
 * (ran fine but found attention items) or `failed` (the dry run itself errored).
 * The string values match the connector_sync_runs.status CHECK constraint.
 */
enum SyncRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** Whether this status should surface as "attention required" in the UI. */
    public function needsAttention(): bool
    {
        return $this === self::CompletedWithWarnings || $this === self::Failed;
    }
}
