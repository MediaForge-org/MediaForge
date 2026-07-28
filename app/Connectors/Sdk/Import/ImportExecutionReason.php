<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * Why the internal import did what it did with one plan line (V2 E). Stable machine
 * codes only — they are stored on the execution item, summarised into the execution
 * summary and into review-task evidence, so they must never carry a secret, a raw
 * API payload, a stack trace or a local path.
 */
enum ImportExecutionReason: string
{
    /* --- imported ------------------------------------------------------- */
    case ImportedFromPlan = 'imported_from_plan';
    case AlreadyImported = 'already_imported';

    /* --- deliberately not imported -------------------------------------- */
    case PlanItemNotReady = 'plan_item_not_ready';
    case NeedsReviewFirst = 'needs_review_first';
    case DuplicateNotImported = 'duplicate_not_imported';
    case UnsupportedKind = 'unsupported_kind';

    /* --- structural refusals -------------------------------------------- */
    case MissingParent = 'missing_parent';
    case AmbiguousParent = 'ambiguous_parent';
    case MissingSourceItem = 'missing_source_item';
    case ConflictExistingMapping = 'conflict_existing_mapping';

    /* --- execution-level ------------------------------------------------- */
    case ImportFailedItems = 'import_failed_items';

    /** Short, human-readable explanation. Safe to show and to store. */
    public function message(): string
    {
        return match ($this) {
            self::ImportedFromPlan => 'Created as a MediaForge database record from a ready plan line.',
            self::AlreadyImported => 'This external item was already imported; the existing record was linked instead of duplicated.',
            self::PlanItemNotReady => 'The plan did not mark this line ready, so it was not imported.',
            self::NeedsReviewFirst => 'A human has to review this line before it may be imported.',
            self::DuplicateNotImported => 'A duplicate suspect is never imported or merged automatically.',
            self::UnsupportedKind => 'Not media, or a kind the internal import does not cover.',
            self::MissingParent => 'No parent record could be identified, so nothing was attached.',
            self::AmbiguousParent => 'More than one possible parent was found; the import never guesses.',
            self::MissingSourceItem => 'The captured external item behind this plan line no longer exists.',
            self::ConflictExistingMapping => 'An existing mapping points elsewhere; the existing record was left untouched.',
            self::ImportFailedItems => 'Some lines could not be imported and were recorded as failed.',
        };
    }

    /**
     * Reasons worth asking a human about via the Review Center. Everyday outcomes
     * (imported, already imported) are not — they would only add noise.
     *
     * @return list<self>
     */
    public static function reviewWorthy(): array
    {
        return [
            self::MissingParent,
            self::AmbiguousParent,
            self::ConflictExistingMapping,
            self::NeedsReviewFirst,
            self::DuplicateNotImported,
            self::MissingSourceItem,
            self::ImportFailedItems,
        ];
    }

    /** Reasons that alone make the review task high priority. */
    public function isBlocking(): bool
    {
        return in_array($this, [
            self::AmbiguousParent,
            self::ConflictExistingMapping,
            self::MissingSourceItem,
            self::ImportFailedItems,
        ], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }
}
