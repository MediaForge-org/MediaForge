<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Sync;

/**
 * What a FUTURE sync would do with a library, as decided by a dry run. Nothing is
 * ever acted on in V1 F — these are labels on a plan. Matches the
 * connector_sync_run_libraries.planned_action CHECK constraint.
 */
enum PlannedSyncAction: string
{
    case InspectOnly = 'inspect_only';
    case FutureSyncCandidate = 'future_sync_candidate';
    case SkippedNotSelected = 'skipped_not_selected';
    case SkippedMissing = 'skipped_missing';
}
