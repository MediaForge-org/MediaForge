<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * Lifecycle status of a read-only catalog snapshot run. In V2 A a run is created
 * already finished: `completed` (clean), `completed_with_warnings` (ran but found
 * attention items, e.g. truncated) or `failed` (the snapshot itself errored). The
 * string values match the connector_catalog_snapshot_runs.status CHECK constraint.
 */
enum CatalogSnapshotStatus: string
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
