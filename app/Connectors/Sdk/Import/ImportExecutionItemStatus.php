<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * The status of one internal-import line (V2 E). The string values match the
 * media_import_execution_items.status CHECK constraint.
 */
enum ImportExecutionItemStatus: string
{
    /** Created or linked — the external item now has an internal record. */
    case Completed = 'completed';

    /** Deliberately not imported; the reason codes say why. */
    case Skipped = 'skipped';

    /** Could not be imported. Recorded, never silently dropped. */
    case Failed = 'failed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
