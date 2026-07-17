<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * A data-quality problem found while normalizing an external catalog item (V2 C).
 * Stable machine codes only — they are stored in the normalization row, surfaced in
 * the UI and summarised into review-task evidence, so they must never carry a
 * secret, a raw API payload or a local path. Each code has a confidence penalty;
 * the verdict falls out of the sum (see NormalizationStatus::fromConfidence).
 */
enum NormalizationIssue: string
{
    case MissingTitle = 'missing_title';
    case ShortTitle = 'short_title';
    case UnknownKind = 'unknown_kind';
    case MissingYear = 'missing_year';
    case InvalidYear = 'invalid_year';
    case MissingSeasonNumber = 'missing_season_number';
    case MissingEpisodeNumber = 'missing_episode_number';
    case RuntimeMissing = 'runtime_missing';
    case InvalidRuntime = 'invalid_runtime';
    case WeakMetadata = 'weak_metadata';

    /**
     * How much this issue costs against a perfect confidence of 100.
     *
     * MissingTitle and WeakMetadata are calibrated to be decisive on their own: an
     * item with no title, or with nothing at all beyond its title, cannot be
     * interpreted or matched, so it must land in `needs_review` (<60) rather than
     * merely `warning`. WeakMetadata always travels with MissingYear (8) +
     * RuntimeMissing (5), so 40 puts the total at 53 → a confidence of 47.
     */
    public function penalty(): int
    {
        return match ($this) {
            self::MissingTitle => 60,
            self::WeakMetadata => 40,
            self::UnknownKind => 30,
            self::MissingSeasonNumber, self::MissingEpisodeNumber => 15,
            self::InvalidYear, self::InvalidRuntime, self::ShortTitle => 10,
            self::MissingYear => 8,
            self::RuntimeMissing => 5,
        };
    }

    /** Short, human-readable explanation. Safe to show and to store. */
    public function message(): string
    {
        return match ($this) {
            self::MissingTitle => 'The connector reported no usable title.',
            self::ShortTitle => 'The title is very short and may be a placeholder.',
            self::UnknownKind => 'The media kind could not be classified.',
            self::MissingYear => 'No release year was reported.',
            self::InvalidYear => 'The reported release year is out of a plausible range.',
            self::MissingSeasonNumber => 'This episode has no season number.',
            self::MissingEpisodeNumber => 'This episode has no episode number.',
            self::RuntimeMissing => 'No runtime was reported.',
            self::InvalidRuntime => 'The reported runtime is not plausible.',
            self::WeakMetadata => 'Too little metadata to interpret this item confidently.',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $issue): string => $issue->value, self::cases());
    }
}
