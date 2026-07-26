<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

/**
 * Why a planned import line got the status it got (V2 D). Stable machine codes
 * only — they are stored on the plan item, echoed to the UI and summarised into
 * review-task evidence, so they must never carry a secret, a raw API payload, a
 * stack trace or a local path.
 *
 * These explain a PLAN, not an action: nothing here is ever executed in V2 D.
 */
enum ImportPlanReason: string
{
    /* --- positive ------------------------------------------------------- */
    case ReadyToImport = 'ready_to_import';

    /* --- warnings ------------------------------------------------------- */
    case MissingYear = 'missing_year';
    case MissingSeasonNumber = 'missing_season_number';
    case MissingEpisodeNumber = 'missing_episode_number';

    /* --- review --------------------------------------------------------- */
    case DuplicateSuspect = 'duplicate_suspect';
    case WeakMetadata = 'weak_metadata';
    case UnknownKind = 'unknown_kind';
    case LowConfidence = 'low_confidence';

    /* --- blockers ------------------------------------------------------- */
    case MissingTitle = 'missing_title';
    case MissingParent = 'missing_parent';
    case NotNormalized = 'not_normalized';
    case UnsafeToImport = 'unsafe_to_import';

    /* --- skipped -------------------------------------------------------- */
    case UnsupportedKind = 'unsupported_kind';

    /* --- plan level ----------------------------------------------------- */
    case ImportPlanBlocked = 'import_plan_blocked';
    case TruncatedPlan = 'truncated_plan';

    /** Short, human-readable explanation. Safe to show and to store. */
    public function message(): string
    {
        return match ($this) {
            self::ReadyToImport => 'Enough information to be created by a later import.',
            self::MissingYear => 'No release year, so a later import cannot tell same-named works apart.',
            self::MissingSeasonNumber => 'This episode has no season number to attach to.',
            self::MissingEpisodeNumber => 'This episode has no episode number to order it by.',
            self::DuplicateSuspect => 'Another captured item shares this identity — a human decides, nothing is merged.',
            self::WeakMetadata => 'Too little metadata to plan this item confidently.',
            self::UnknownKind => 'The media kind could not be classified, so no target shape can be planned.',
            self::LowConfidence => 'The normalized reading of this item is not confident enough to plan.',
            self::MissingTitle => 'The connector reported no usable title.',
            self::MissingParent => 'No parent series or season could be identified for this episode.',
            self::NotNormalized => 'This item has not been normalized yet — rebuild normalization first.',
            self::UnsafeToImport => 'A later import would not be safe without a human decision.',
            self::UnsupportedKind => 'Not media, or a kind the import model does not cover yet.',
            self::ImportPlanBlocked => 'The plan contains items that cannot be imported at all.',
            self::TruncatedPlan => 'More captured items exist than one plan may hold; the plan is a bounded subset.',
        };
    }

    /**
     * Reasons that justify asking a human via the Review Center. The everyday
     * warnings (a missing year, a missing runtime) do not — they are visible on the
     * plan itself and would only add noise to the queue.
     *
     * @return list<self>
     */
    public static function reviewWorthy(): array
    {
        return [
            self::ImportPlanBlocked,
            self::DuplicateSuspect,
            self::WeakMetadata,
            self::MissingParent,
            self::UnknownKind,
            self::MissingTitle,
            self::NotNormalized,
            self::UnsafeToImport,
            self::TruncatedPlan,
        ];
    }

    /** Reasons that alone make the whole plan high priority. */
    public function isBlocking(): bool
    {
        return in_array($this, [self::ImportPlanBlocked, self::MissingTitle, self::MissingParent, self::NotNormalized, self::UnsafeToImport], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }
}
