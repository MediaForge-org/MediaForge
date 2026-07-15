<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Sync;

/**
 * Per-library outcome inside a dry run. Matches the
 * connector_sync_run_libraries.status CHECK constraint.
 */
enum SyncLibraryStatus: string
{
    case Planned = 'planned';
    case Skipped = 'skipped';
    case Warning = 'warning';
    case Failed = 'failed';
    case Ready = 'ready';
}
