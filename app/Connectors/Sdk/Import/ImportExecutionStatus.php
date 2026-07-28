<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * The outcome of one internal import execution (V2 E). Derived purely from what the
 * run did — never from the network. The string values match the
 * media_import_executions.status CHECK constraint.
 */
enum ImportExecutionStatus: string
{
    /** The plan held nothing importable, so nothing was created. */
    case Empty = 'empty';

    /** Everything importable was created or linked; nothing was skipped or failed. */
    case Completed = 'completed';

    /** Importable lines were handled, but some were skipped, linked or failed. */
    case CompletedWithWarnings = 'completed_with_warnings';

    /** An unexpected error aborted the run; the transaction was rolled back. */
    case Failed = 'failed';

    /**
     * Fold the per-item counts into one verdict.
     *
     * `completed` is deliberately strict: it means a later reader can trust that
     * every line the plan marked ready really became an internal record on this
     * run. Anything less honest — a skip, a link to an already-imported item, a
     * failed line — reports as `completed_with_warnings`.
     */
    public static function fromCounts(int $candidateCount, int $imported, int $alreadyExisting, int $skipped, int $failed): self
    {
        if ($candidateCount === 0) {
            return self::Empty;
        }

        if ($skipped > 0 || $failed > 0 || $alreadyExisting > 0) {
            return self::CompletedWithWarnings;
        }

        return self::Completed;
    }

    /** Whether this outcome should surface as an attention item in the UI. */
    public function needsAttention(): bool
    {
        return $this === self::Failed;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
