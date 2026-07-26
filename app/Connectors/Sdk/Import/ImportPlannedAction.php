<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * What a LATER import would do with one external item (V2 D). Every case is a
 * description, never an instruction that anything executes: V2 D writes no
 * media_items/editions/files and performs no file operation. The string values
 * match the media_import_plan_items.planned_action CHECK constraint.
 *
 * Note what is deliberately absent: there is no copy/move/delete/rename action and
 * no "accept match"/"merge" action, because MediaForge plans no file operation and
 * accepts no match in V2 D.
 */
enum ImportPlannedAction: string
{
    /** Would become a playable MediaForge item (movie, audiobook, book). */
    case CreateMedia = 'create_media';

    /** Would become a container (series, season). */
    case CreateContainer = 'create_container';

    /** Would be attached to an existing container (episode → season/series). */
    case AttachToParent = 'attach_to_parent';

    /** Not media (folder/playlist) or a kind the import model does not cover yet. */
    case SkipUnsupported = 'skip_unsupported';

    /** Looks like something already planned — a human decides, nothing is merged. */
    case SkipDuplicate = 'skip_duplicate';

    /** Interpretable, but a human must confirm before a later import. */
    case NeedsReview = 'needs_review';

    /** Not plannable — a later import must not attempt it. */
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::CreateMedia => 'Would create a media item',
            self::CreateContainer => 'Would create a container',
            self::AttachToParent => 'Would attach to its parent',
            self::SkipUnsupported => 'Would be skipped — not supported',
            self::SkipDuplicate => 'Would be skipped — duplicate suspect',
            self::NeedsReview => 'Needs a human decision first',
            self::Blocked => 'Cannot be planned',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
