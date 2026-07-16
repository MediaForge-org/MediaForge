<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

/**
 * Normalized kind of an external connector item. This describes what the REMOTE
 * server exposes (a read-only classification) — it is never a MediaForge media
 * type. The string values match the connector_catalog_items.media_kind CHECK.
 */
enum ExternalMediaKind: string
{
    case Movie = 'movie';
    case Series = 'series';
    case Season = 'season';
    case Episode = 'episode';
    case Audiobook = 'audiobook';
    case Book = 'book';
    case Podcast = 'podcast';
    case Music = 'music';
    case Playlist = 'playlist';
    case Folder = 'folder';
    case Unknown = 'unknown';

    /** Map an arbitrary provider string to a known kind, defaulting to Unknown. */
    public static function fromProvider(?string $raw): self
    {
        return self::tryFrom(strtolower(trim((string) $raw))) ?? self::Unknown;
    }

    /** @return list<string> The string values, for filter allowlists. */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
