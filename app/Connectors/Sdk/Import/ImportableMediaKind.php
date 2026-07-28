<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

use App\Connectors\Sdk\Catalog\ExternalMediaKind;

/**
 * The bridge between a plan line's `planned_kind` and the canonical
 * `media_items.media_type` (V2 E).
 *
 * These are two different vocabularies and the difference is real, not cosmetic:
 * the connector read-model says `series` and `book`, while the foundation catalog
 * (docs/MediaForge/database/core-schema.md) has always said `show` and `ebook`. The
 * mapping is stated once, here, so no other file has to know about it — and a kind
 * that has no honest counterpart simply is not importable rather than being forced
 * into an approximate one.
 */
enum ImportableMediaKind: string
{
    case Movie = 'movie';
    case Series = 'series';
    case Season = 'season';
    case Episode = 'episode';
    case Audiobook = 'audiobook';
    case Book = 'book';

    /** The planned kind, if the internal import covers it at all. */
    public static function fromPlannedKind(string $plannedKind): ?self
    {
        return self::tryFrom($plannedKind);
    }

    /** The value written to `media_items.media_type`. */
    public function mediaType(): string
    {
        return match ($this) {
            self::Movie => 'movie',
            // The foundation catalog calls a series a "show".
            self::Series => 'show',
            self::Season => 'season',
            self::Episode => 'episode',
            self::Audiobook => 'audiobook',
            // ...and a book an "ebook".
            self::Book => 'ebook',
        };
    }

    /**
     * The kind this one must hang under, or null when it is a root.
     *
     * A season belongs to a series and an episode to a season — nothing else is
     * accepted, so a wrongly-shaped hierarchy is refused instead of built.
     */
    public function requiredParent(): ?self
    {
        return match ($this) {
            self::Season => self::Series,
            self::Episode => self::Season,
            default => null,
        };
    }

    public function requiresParent(): bool
    {
        return $this->requiredParent() !== null;
    }

    /**
     * Import order. Containers must exist before the things that hang under them,
     * so one pass over a plan sorted by this rank can always find its parents.
     */
    public function importRank(): int
    {
        return match ($this) {
            self::Series => 1,
            self::Season => 2,
            self::Episode => 3,
            self::Movie => 4,
            self::Audiobook => 5,
            self::Book => 6,
        };
    }

    /** The plan actions that legitimately produce this kind. */
    public function expectedPlannedAction(): ImportPlannedAction
    {
        return match ($this) {
            self::Series, self::Season => ImportPlannedAction::CreateContainer,
            self::Episode => ImportPlannedAction::AttachToParent,
            self::Movie, self::Audiobook, self::Book => ImportPlannedAction::CreateMedia,
        };
    }

    /** The external catalog kind this corresponds to, for display. */
    public function externalKind(): ExternalMediaKind
    {
        return ExternalMediaKind::from($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }

    /** @return list<string> The `media_items.media_type` values V2 E may write. */
    public static function mediaTypes(): array
    {
        return array_map(static fn (self $kind): string => $kind->mediaType(), self::cases());
    }
}
