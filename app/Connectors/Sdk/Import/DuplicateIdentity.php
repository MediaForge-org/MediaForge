<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

use App\Connectors\Sdk\Catalog\ExternalMediaKind;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;

/**
 * The identity two captured items must share before either may be BLOCKED as a
 * duplicate (V2 E.1).
 *
 * This is deliberately much stricter than the display-level grouping in
 * CatalogMatchPreview, because the consequences differ: the preview only suggests,
 * while this decides that an item is not safe to import. A false positive here
 * silently withholds real media, so the rule is built to under-block rather than
 * over-block.
 *
 * WHAT COUNTS AS THE SAME THING:
 *
 *   episode  same connector instance + same parent container + same season number
 *            + same episode number. The TITLE is not part of the key at all, so
 *            two episodes of one season can never collide just because both are
 *            called "Episode 1" — and two shows can never collide, because their
 *            parents differ.
 *   season   same connector instance + same show + same season number. "Season 1"
 *            of Supernatural and "Season 1" of Chernobyl have different parents
 *            and are therefore different things.
 *   series   same connector instance + same normalized title + a REAL release
 *   movie    year. Without a year there is nothing solid to tell two same-named
 *   book     works apart, so they are never blocked — they are merely reported.
 *   audiobook
 *
 * Everything else (folder, playlist, podcast, music, unknown, or anything missing
 * the fields above) returns null: never eligible to be blocked as a duplicate.
 *
 * The parent is identified by the connector's own `external_parent_id` whenever it
 * is present, which is exact. It falls back to the parent TITLE only when that
 * title is specific enough to mean something — a parent called "Staffel 1" is not.
 *
 * Identity is scoped to one connector instance on purpose. The same film on two
 * different servers is two different external items, and deciding they are one work
 * is a metadata-matching question, not something to settle by blocking an import.
 */
final class DuplicateIdentity
{
    /** Kinds keyed on title + year, because they have no parent container. */
    private const TITLE_KINDS = [
        ExternalMediaKind::Series,
        ExternalMediaKind::Movie,
        ExternalMediaKind::Book,
        ExternalMediaKind::Audiobook,
    ];

    /**
     * Titles that identify nothing on their own. A connector that labels every
     * first episode "Episode 1" must never make those look like the same episode,
     * and a runtime-shaped title ("1:23:45") is a placeholder, not a name.
     */
    private const GENERIC_TITLE_PATTERNS = [
        '/^\d+$/',                                              // "1"
        '/^[\d\s:.\-]+$/',                                      // "1:23:45", "01 - 02"
        '/^(episode|folge|chapter|kapitel|teil|part|track|disc|disk|cd|dvd)\b/iu',
        '/^(staffel|season|series|serie)\s*\d*$/iu',
    ];

    /**
     * The strong identity of this item, or null when it may never be blocked as a
     * duplicate.
     */
    public static function for(ConnectorCatalogItem $item, ConnectorCatalogItemNormalization $normalization): ?string
    {
        $kind = ExternalMediaKind::fromProvider($normalization->normalized_kind);
        $instance = $item->connector_instance_id;

        if ($kind === ExternalMediaKind::Episode) {
            return self::episode($item, $normalization, $instance);
        }

        if ($kind === ExternalMediaKind::Season) {
            return self::season($item, $normalization, $instance);
        }

        if (in_array($kind, self::TITLE_KINDS, true)) {
            return self::titled($normalization, $kind, $instance);
        }

        return null;
    }

    /**
     * An episode is the same episode only inside the same container, at the same
     * season and episode number. Nothing about the title is consulted.
     */
    private static function episode(
        ConnectorCatalogItem $item,
        ConnectorCatalogItemNormalization $normalization,
        string $instance,
    ): ?string {
        $parent = self::parentKey($item, $normalization);
        $season = $normalization->season_number;
        $episode = $normalization->episode_number;

        if ($parent === null || $season === null || $episode === null) {
            return null;
        }

        return implode("\0", ['episode', $instance, $parent, (string) $season, (string) $episode]);
    }

    /** A season is the same season only inside the same show, at the same number. */
    private static function season(
        ConnectorCatalogItem $item,
        ConnectorCatalogItemNormalization $normalization,
        string $instance,
    ): ?string {
        $parent = self::parentKey($item, $normalization);
        $season = $normalization->season_number;

        if ($parent === null || $season === null) {
            return null;
        }

        return implode("\0", ['season', $instance, $parent, (string) $season]);
    }

    /** A standalone work needs a real year before two same-named ones may be blocked. */
    private static function titled(
        ConnectorCatalogItemNormalization $normalization,
        ExternalMediaKind $kind,
        string $instance,
    ): ?string {
        $year = $normalization->release_year;
        $title = self::normalize($normalization->normalized_title);

        if ($year === null || $title === '' || self::isGeneric($title)) {
            return null;
        }

        return implode("\0", [$kind->value, $instance, $title, (string) $year]);
    }

    /**
     * How we identify the container an item hangs under. The connector's own parent
     * id is exact and is preferred; a parent title is accepted only when it is
     * specific enough to distinguish one show from another.
     */
    private static function parentKey(ConnectorCatalogItem $item, ConnectorCatalogItemNormalization $normalization): ?string
    {
        if ($item->external_parent_id !== null && trim($item->external_parent_id) !== '') {
            return 'id:'.trim($item->external_parent_id);
        }

        $parentTitle = self::normalize($normalization->parent_title ?? '');

        if ($parentTitle === '' || self::isGeneric($parentTitle)) {
            return null;
        }

        return 'title:'.$parentTitle;
    }

    /** Case- and whitespace-insensitive comparison form. */
    private static function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value));

        return mb_strtolower($collapsed ?? '');
    }

    /** Whether this title identifies nothing on its own. */
    public static function isGeneric(string $normalizedTitle): bool
    {
        if (mb_strlen($normalizedTitle) < 2) {
            return true;
        }

        foreach (self::GENERIC_TITLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalizedTitle) === 1) {
                return true;
            }
        }

        return false;
    }
}
