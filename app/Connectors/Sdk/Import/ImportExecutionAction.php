<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * What the internal import actually did with one plan line (V2 E). The string
 * values match the media_import_execution_items.action CHECK constraint.
 *
 * Note what is absent, and stays absent: there is no copy, move, delete, rename,
 * merge, accept or push-to-remote action, because the internal import performs
 * none of those.
 */
enum ImportExecutionAction: string
{
    /** A new MediaForge media item (and its external mapping) was created. */
    case Created = 'created';

    /** The external item was already imported; the existing item was linked, not duplicated. */
    case LinkedExisting = 'linked_existing';

    /** The plan line was not ready (needs review, blocked, warning, missing parent). */
    case SkippedNotReady = 'skipped_not_ready';

    /** Not media, or a kind the internal import does not cover. */
    case SkippedUnsupported = 'skipped_unsupported';

    /** A duplicate suspect. Listed, never merged — a human decides. */
    case SkippedDuplicate = 'skipped_duplicate';

    /** The line could not be imported for an unexpected, recorded reason. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created in MediaForge',
            self::LinkedExisting => 'Already imported — linked',
            self::SkippedNotReady => 'Skipped — not ready',
            self::SkippedUnsupported => 'Skipped — not supported',
            self::SkippedDuplicate => 'Skipped — duplicate suspect',
            self::Failed => 'Failed',
        };
    }

    public function status(): ImportExecutionItemStatus
    {
        return match ($this) {
            self::Created, self::LinkedExisting => ImportExecutionItemStatus::Completed,
            self::SkippedNotReady, self::SkippedUnsupported, self::SkippedDuplicate => ImportExecutionItemStatus::Skipped,
            self::Failed => ImportExecutionItemStatus::Failed,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
