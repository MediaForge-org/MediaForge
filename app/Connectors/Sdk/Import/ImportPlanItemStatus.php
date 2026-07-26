<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * The verdict of one planned line of an import dry run (V2 D). The string values
 * match the media_import_plan_items.status CHECK constraint.
 */
enum ImportPlanItemStatus: string
{
    /** A later import could create this without asking anyone. */
    case Ready = 'ready';

    /** Importable later, but something is missing or odd. */
    case Warning = 'warning';

    /** A human has to decide before a later import may touch this. */
    case NeedsReview = 'needs_review';

    /** Cannot be planned at all — a later import must not attempt it. */
    case Blocked = 'blocked';

    /** Deliberately left out (a structural container or an unsupported kind). */
    case Skipped = 'skipped';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
