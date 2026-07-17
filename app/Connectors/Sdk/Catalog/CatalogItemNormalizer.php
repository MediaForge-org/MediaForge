<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Catalog;

use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use Illuminate\Support\Carbon;

/**
 * Interprets one captured external catalog item into a consistent shape (V2 C).
 *
 * Deliberately PURE: it takes the stored item, touches no database, no network and
 * no file, and returns a NormalizedItem. That keeps the rules trivially testable and
 * makes it impossible for normalization to import anything or reach a remote.
 *
 * It is also deliberately CONSERVATIVE. It only re-reads what the connector already
 * reported — it never invents a year from a title, never regex-guesses season/episode
 * numbers out of free text, and never fills a gap with a plausible-looking value.
 * A missing field stays missing and becomes a visible issue instead.
 */
final class CatalogItemNormalizer
{
    /** Titles shorter than this are suspicious (e.g. a placeholder like "1"). */
    private const MIN_TITLE_LENGTH = 2;

    /** The earliest plausible release year for recorded media. */
    private const MIN_YEAR = 1870;

    /** Runtimes beyond this (24h) are not plausible for a catalog item. */
    private const MAX_RUNTIME_SECONDS = 86_400;

    /** Leading articles stripped when deriving a sort title. */
    private const ARTICLES = ['the ', 'a ', 'an ', 'der ', 'die ', 'das ', 'ein ', 'eine ', 'le ', 'la ', 'les ', 'el ', 'los ', 'las '];

    /**
     * @param  string|null  $parentTitle  The already-resolved title of the item's
     *                                    external parent. The caller looks it up in
     *                                    bulk (external_parent_id → external_id
     *                                    within the same instance); the normalizer
     *                                    stays free of queries and of the network.
     */
    public function normalize(ConnectorCatalogItem $item, ?string $parentTitle = null): NormalizedItem
    {
        /** @var list<NormalizationIssue> $issues */
        $issues = [];

        $kind = ExternalMediaKind::fromProvider($item->media_kind);
        $title = $this->cleanText($item->title);

        if ($title === null) {
            $issues[] = NormalizationIssue::MissingTitle;
            $title = '(untitled)';
        } elseif (mb_strlen($title) < self::MIN_TITLE_LENGTH) {
            $issues[] = NormalizationIssue::ShortTitle;
        }

        if ($kind === ExternalMediaKind::Unknown) {
            $issues[] = NormalizationIssue::UnknownKind;
        }

        // Structural containers are not media: normalization does not apply, so we
        // report them explicitly rather than drowning them in media-shaped warnings.
        if ($this->isStructural($kind)) {
            return new NormalizedItem(
                kind: $kind,
                title: $title,
                sortTitle: $this->sortTitle($item->sort_title, $title),
                originalTitle: $this->cleanText($item->original_title),
                releaseYear: null,
                seasonNumber: null,
                episodeNumber: null,
                parentTitle: null,
                runtimeSeconds: null,
                confidence: 100,
                status: NormalizationStatus::Unsupported,
                issues: [],
            );
        }

        $releaseYear = $this->year($item->year, $issues);
        [$seasonNumber, $episodeNumber] = $this->seasonAndEpisode($item, $kind, $issues);
        $runtimeSeconds = $this->runtime($item->runtime_seconds, $kind, $issues);

        // A title alone, with nothing else to go on, is not enough to interpret.
        if ($this->isWeak($kind, $releaseYear, $runtimeSeconds, $seasonNumber, $episodeNumber)) {
            $issues[] = NormalizationIssue::WeakMetadata;
        }

        $confidence = $this->confidence($issues);

        return new NormalizedItem(
            kind: $kind,
            title: $title,
            sortTitle: $this->sortTitle($item->sort_title, $title),
            originalTitle: $this->cleanText($item->original_title),
            releaseYear: $releaseYear,
            seasonNumber: $seasonNumber,
            episodeNumber: $episodeNumber,
            parentTitle: $this->cleanText($parentTitle),
            runtimeSeconds: $runtimeSeconds,
            confidence: $confidence,
            status: NormalizationStatus::fromConfidence($confidence),
            issues: $issues,
        );
    }

    private function isStructural(ExternalMediaKind $kind): bool
    {
        return $kind === ExternalMediaKind::Folder || $kind === ExternalMediaKind::Playlist;
    }

    /**
     * Collapse whitespace and unify typographic variants so two spellings of the
     * same title compare equal. Returns null when nothing usable remains.
     */
    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Unify curly quotes/dashes/NBSP, then collapse runs of whitespace.
        $text = strtr($value, [
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201A}" => "'", "\u{2032}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{201E}" => '"', "\u{2033}" => '"',
            "\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-',
            "\u{00A0}" => ' ', "\u{202F}" => ' ', "\u{2009}" => ' ',
            "\u{2026}" => '...',
        ]);

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * Prefer the connector's own sort title; otherwise derive one by moving a
     * leading article out of the way. Case-folded for stable ordering. Always
     * yields a value, because the title it falls back to is already defaulted.
     */
    private function sortTitle(?string $reported, string $title): string
    {
        $sort = $this->cleanText($reported);

        if ($sort !== null) {
            return mb_strtolower($sort);
        }

        $lower = mb_strtolower($title);

        foreach (self::ARTICLES as $article) {
            if (str_starts_with($lower, $article)) {
                return trim(mb_substr($lower, mb_strlen($article)));
            }
        }

        return $lower;
    }

    /**
     * Take the reported year only. An implausible value is flagged and dropped
     * rather than "corrected" — we never guess a year from the title.
     *
     * @param  list<NormalizationIssue>  $issues
     */
    private function year(?int $year, array &$issues): ?int
    {
        if ($year === null) {
            $issues[] = NormalizationIssue::MissingYear;

            return null;
        }

        if ($year < self::MIN_YEAR || $year > (int) Carbon::now()->year + 5) {
            $issues[] = NormalizationIssue::InvalidYear;

            return null;
        }

        return $year;
    }

    /**
     * For an episode, Jellyfin-style fields map directly: parent_index_number is the
     * season, index_number the episode. Nothing is parsed out of the title.
     *
     * @param  list<NormalizationIssue>  $issues
     * @return array{0: int|null, 1: int|null}
     */
    private function seasonAndEpisode(ConnectorCatalogItem $item, ExternalMediaKind $kind, array &$issues): array
    {
        if ($kind === ExternalMediaKind::Season) {
            return [$this->positive($item->index_number), null];
        }

        if ($kind !== ExternalMediaKind::Episode) {
            return [null, null];
        }

        $season = $this->positive($item->parent_index_number);
        $episode = $this->positive($item->index_number);

        if ($season === null) {
            $issues[] = NormalizationIssue::MissingSeasonNumber;
        }

        if ($episode === null) {
            $issues[] = NormalizationIssue::MissingEpisodeNumber;
        }

        return [$season, $episode];
    }

    /** Season/episode numbers are 0-or-greater; anything negative is meaningless. */
    private function positive(?int $value): ?int
    {
        return $value !== null && $value >= 0 ? $value : null;
    }

    /**
     * Runtime is taken as reported. Zero/negative/absurd values are flagged and
     * dropped — no file is ever opened to find the real duration.
     *
     * @param  list<NormalizationIssue>  $issues
     */
    private function runtime(?int $runtime, ExternalMediaKind $kind, array &$issues): ?int
    {
        if ($runtime === null) {
            // A series/season is a container; it legitimately has no runtime.
            if (!$this->isContainerKind($kind)) {
                $issues[] = NormalizationIssue::RuntimeMissing;
            }

            return null;
        }

        if ($runtime <= 0 || $runtime > self::MAX_RUNTIME_SECONDS) {
            $issues[] = NormalizationIssue::InvalidRuntime;

            return null;
        }

        return $runtime;
    }

    private function isContainerKind(ExternalMediaKind $kind): bool
    {
        return $kind === ExternalMediaKind::Series || $kind === ExternalMediaKind::Season;
    }

    /**
     * "Weak" = a playable kind that carries nothing beyond its title, so there is
     * no way to tell two same-named items apart or match them to anything.
     */
    private function isWeak(ExternalMediaKind $kind, ?int $year, ?int $runtime, ?int $season, ?int $episode): bool
    {
        if ($this->isContainerKind($kind) || $kind === ExternalMediaKind::Unknown) {
            return false; // already flagged on its own merits
        }

        return $year === null && $runtime === null && $season === null && $episode === null;
    }

    /** @param list<NormalizationIssue> $issues */
    private function confidence(array $issues): int
    {
        $confidence = 100;

        foreach ($issues as $issue) {
            $confidence -= $issue->penalty();
        }

        return max(0, min(100, $confidence));
    }
}
