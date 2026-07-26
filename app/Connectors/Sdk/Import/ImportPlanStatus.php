<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * The overall verdict of an import dry run (V2 D). Derived purely from the planned
 * items — never from the network. The string values match the
 * media_import_plans.status CHECK constraint.
 *
 * `ready` never means "import started"; it means a later import would have nothing
 * left to ask a human about. V2 D performs no import at all.
 */
enum ImportPlanStatus: string
{
    /** Nothing to plan — no captured item fell inside the scope. */
    case Empty = 'empty';

    /** Every planned item is unambiguous. */
    case Ready = 'ready';

    /** Usable, but something needs a human before a later import. */
    case Warnings = 'warnings';

    /** At least one item cannot be planned at all. */
    case Blocked = 'blocked';

    /**
     * Fold the per-item counts into one verdict. Blocked wins over warnings, and
     * warnings win over ready, so the worst finding is always the one reported.
     */
    public static function fromCounts(
        int $sourceItemCount,
        int $blockedCount,
        int $warningCount,
        int $reviewCount,
        bool $truncated,
    ): self {
        if ($sourceItemCount === 0) {
            return self::Empty;
        }

        if ($blockedCount > 0) {
            return self::Blocked;
        }

        if ($warningCount > 0 || $reviewCount > 0 || $truncated) {
            return self::Warnings;
        }

        return self::Ready;
    }

    /** Whether this verdict should surface as an attention item in the UI. */
    public function needsAttention(): bool
    {
        return $this === self::Blocked;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
