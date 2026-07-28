<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

use App\Connectors\Sdk\Catalog\ExternalMediaKind;
use App\Connectors\Sdk\Catalog\NormalizationIssue;
use App\Connectors\Sdk\Catalog\NormalizationStatus;
use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;

/**
 * Plans what a LATER import would do with one normalized external catalog item
 * (V2 D).
 *
 * Deliberately PURE: it takes the stored normalization row (plus one precomputed
 * "is this a duplicate suspect?" flag) and returns a PlannedImportItem. It touches
 * no database, no network and no file, which makes it trivially testable and makes
 * it structurally impossible for planning to import anything, accept a match or
 * move a byte.
 *
 * Deliberately CONSERVATIVE, in the same spirit as the normalizer: it never
 * invents a year, never guesses a parent, never merges two suspects. Anything it
 * cannot justify becomes `needs_review` or `blocked` with a stable reason code —
 * an honest gap beats a plausible-looking wrong plan.
 *
 * Deliberately DETERMINISTIC: the same stored input always yields the same action,
 * status, reasons and `target_key`, so two dry runs over unchanged data agree.
 */
final class PlanCatalogItemImport
{
    /** Kinds a later import would create as a playable media item. */
    private const MEDIA_KINDS = [
        ExternalMediaKind::Movie,
        ExternalMediaKind::Audiobook,
        ExternalMediaKind::Book,
    ];

    /** Kinds that are structure, not media — reported plainly, never an error. */
    private const STRUCTURAL_KINDS = [
        ExternalMediaKind::Folder,
        ExternalMediaKind::Playlist,
    ];

    /**
     * Kinds MediaForge captures but the first internal import (V2 E) will not
     * cover. Counted as skipped/unsupported rather than pretended to be plannable.
     */
    private const OUT_OF_SCOPE_KINDS = [
        ExternalMediaKind::Podcast,
        ExternalMediaKind::Music,
    ];

    /** Below this normalized confidence nothing is planned without a human. */
    private const MIN_CONFIDENCE = 60;

    /** A duplicate suspect costs this much confidence — it never gains any. */
    private const DUPLICATE_PENALTY = 20;

    /**
     * An item captured before normalization (or whose normalization row was
     * removed) cannot be planned at all: there is nothing interpreted to plan from.
     */
    public function planUnnormalized(string $title): PlannedImportItem
    {
        return new PlannedImportItem(
            kind: ExternalMediaKind::Unknown,
            action: ImportPlannedAction::Blocked,
            status: ImportPlanItemStatus::Blocked,
            targetTitle: $this->displayTitle($title),
            targetKey: null,
            targetParentKey: null,
            targetYear: null,
            targetSeasonNumber: null,
            targetEpisodeNumber: null,
            confidence: 0,
            reasons: [ImportPlanReason::NotNormalized, ImportPlanReason::UnsafeToImport],
        );
    }

    public function plan(ConnectorCatalogItemNormalization $normalization, bool $isDuplicateSuspect = false): PlannedImportItem
    {
        $kind = ExternalMediaKind::fromProvider($normalization->normalized_kind);
        $issues = $this->issues($normalization);

        $planned = match (true) {
            // (F) Structure and out-of-scope kinds are skipped, never failed.
            in_array($kind, self::STRUCTURAL_KINDS, true),
            in_array($kind, self::OUT_OF_SCOPE_KINDS, true),
            $normalization->status === NormalizationStatus::Unsupported->value => $this->planUnsupported($normalization, $kind),

            // (G) A missing title blocks: there is nothing to create.
            in_array(NormalizationIssue::MissingTitle, $issues, true) => $this->planBlocked($normalization, $kind, ImportPlanReason::MissingTitle),

            // (G) An unclassifiable item cannot be given a target shape.
            $kind === ExternalMediaKind::Unknown => $this->planReview($normalization, $kind, ImportPlanReason::UnknownKind),

            // (E/G) Nothing but a title — no way to match or tell apart.
            in_array(NormalizationIssue::WeakMetadata, $issues, true) => $this->planReview($normalization, $kind, ImportPlanReason::WeakMetadata),

            // (A/E) Movie, audiobook, book.
            in_array($kind, self::MEDIA_KINDS, true) => $this->planMedia($normalization, $kind),

            // (B) Series container.
            $kind === ExternalMediaKind::Series => $this->planSeries($normalization),

            // (C) Season container.
            $kind === ExternalMediaKind::Season => $this->planSeason($normalization),

            // (D) Every remaining kind is an episode — attached to its parent.
            default => $this->planEpisode($normalization, $issues),
        };

        // (H) A duplicate suspect is never automatically ready and is never merged.
        return $isDuplicateSuspect ? $this->withDuplicateSuspicion($planned) : $planned;
    }

    /* -----------------------------------------------------------------------
     | Per-kind rules
     * -------------------------------------------------------------------- */

    /**
     * (A/E) A clean movie/audiobook/book is ready. A missing year is a warning —
     * importable later, but a human should know two same-named works cannot be told
     * apart. Anything the normalizer could not read confidently needs a human.
     */
    private function planMedia(ConnectorCatalogItemNormalization $normalization, ExternalMediaKind $kind): PlannedImportItem
    {
        $reasons = [];
        $status = ImportPlanItemStatus::Ready;

        if ($normalization->release_year === null) {
            $reasons[] = ImportPlanReason::MissingYear;
            $status = ImportPlanItemStatus::Warning;
        }

        if ($normalization->confidence < self::MIN_CONFIDENCE || $normalization->status === NormalizationStatus::NeedsReview->value) {
            $reasons[] = ImportPlanReason::LowConfidence;
            $status = ImportPlanItemStatus::NeedsReview;
        }

        return new PlannedImportItem(
            kind: $kind,
            action: $status === ImportPlanItemStatus::NeedsReview
                ? ImportPlannedAction::NeedsReview
                : ImportPlannedAction::CreateMedia,
            status: $status,
            targetTitle: $this->displayTitle($normalization->normalized_title),
            targetKey: $this->mediaKey($kind, $normalization),
            targetParentKey: null,
            targetYear: $normalization->release_year,
            targetSeasonNumber: null,
            targetEpisodeNumber: null,
            confidence: $normalization->confidence,
            reasons: $reasons === [] ? [ImportPlanReason::ReadyToImport] : $reasons,
        );
    }

    private function planSeries(ConnectorCatalogItemNormalization $normalization): PlannedImportItem
    {
        $missingYear = $normalization->release_year === null;

        return new PlannedImportItem(
            kind: ExternalMediaKind::Series,
            action: ImportPlannedAction::CreateContainer,
            status: $missingYear ? ImportPlanItemStatus::Warning : ImportPlanItemStatus::Ready,
            targetTitle: $this->displayTitle($normalization->normalized_title),
            targetKey: $this->seriesKey($normalization->normalized_title, $normalization->release_year),
            targetParentKey: null,
            targetYear: $normalization->release_year,
            targetSeasonNumber: null,
            targetEpisodeNumber: null,
            confidence: $normalization->confidence,
            reasons: $missingYear ? [ImportPlanReason::MissingYear] : [ImportPlanReason::ReadyToImport],
        );
    }

    private function planSeason(ConnectorCatalogItemNormalization $normalization): PlannedImportItem
    {
        // A season is only a container if we know which series and which number.
        $parentTitle = $normalization->parent_title;
        $season = $normalization->season_number;

        $reasons = [];

        if ($parentTitle === null) {
            $reasons[] = ImportPlanReason::MissingParent;
        }

        if ($season === null) {
            $reasons[] = ImportPlanReason::MissingSeasonNumber;
        }

        $status = $reasons === [] ? ImportPlanItemStatus::Ready : ImportPlanItemStatus::NeedsReview;

        return new PlannedImportItem(
            kind: ExternalMediaKind::Season,
            action: $status === ImportPlanItemStatus::Ready
                ? ImportPlannedAction::CreateContainer
                : ImportPlannedAction::NeedsReview,
            status: $status,
            targetTitle: $this->displayTitle($normalization->normalized_title),
            targetKey: $parentTitle !== null ? $this->seasonKey($parentTitle, $season) : null,
            targetParentKey: $parentTitle !== null ? $this->seriesKey($parentTitle, null) : null,
            targetYear: $normalization->release_year,
            targetSeasonNumber: $season,
            targetEpisodeNumber: null,
            confidence: $normalization->confidence,
            reasons: $reasons === [] ? [ImportPlanReason::ReadyToImport] : $reasons,
        );
    }

    /** @param list<NormalizationIssue> $issues */
    private function planEpisode(ConnectorCatalogItemNormalization $normalization, array $issues): PlannedImportItem
    {
        $parentTitle = $normalization->parent_title;
        $season = $normalization->season_number;
        $episode = $normalization->episode_number;

        // Without a parent there is nothing to attach to — that is a blocker, not a
        // warning, because "attach to parent" is the only action an episode has.
        if ($parentTitle === null) {
            return new PlannedImportItem(
                kind: ExternalMediaKind::Episode,
                action: ImportPlannedAction::Blocked,
                status: ImportPlanItemStatus::Blocked,
                targetTitle: $this->displayTitle($normalization->normalized_title),
                targetKey: null,
                targetParentKey: null,
                targetYear: $normalization->release_year,
                targetSeasonNumber: $season,
                targetEpisodeNumber: $episode,
                confidence: $normalization->confidence,
                reasons: [ImportPlanReason::MissingParent, ImportPlanReason::UnsafeToImport],
            );
        }

        $reasons = [];

        if ($season === null || in_array(NormalizationIssue::MissingSeasonNumber, $issues, true)) {
            $reasons[] = ImportPlanReason::MissingSeasonNumber;
        }

        if ($episode === null || in_array(NormalizationIssue::MissingEpisodeNumber, $issues, true)) {
            $reasons[] = ImportPlanReason::MissingEpisodeNumber;
        }

        $ready = $reasons === [];

        return new PlannedImportItem(
            kind: ExternalMediaKind::Episode,
            action: $ready ? ImportPlannedAction::AttachToParent : ImportPlannedAction::NeedsReview,
            status: $ready ? ImportPlanItemStatus::Ready : ImportPlanItemStatus::NeedsReview,
            targetTitle: $this->displayTitle($normalization->normalized_title),
            targetKey: $ready ? $this->episodeKey($parentTitle, $season, $episode) : null,
            targetParentKey: $this->seasonKey($parentTitle, $season),
            targetYear: $normalization->release_year,
            targetSeasonNumber: $season,
            targetEpisodeNumber: $episode,
            confidence: $normalization->confidence,
            reasons: $ready ? [ImportPlanReason::ReadyToImport] : $reasons,
        );
    }

    private function planUnsupported(ConnectorCatalogItemNormalization $normalization, ExternalMediaKind $kind): PlannedImportItem
    {
        return new PlannedImportItem(
            kind: $kind,
            action: ImportPlannedAction::SkipUnsupported,
            status: ImportPlanItemStatus::Skipped,
            targetTitle: $this->displayTitle($normalization->normalized_title),
            targetKey: null,
            targetParentKey: null,
            targetYear: null,
            targetSeasonNumber: null,
            targetEpisodeNumber: null,
            confidence: $normalization->confidence,
            reasons: [ImportPlanReason::UnsupportedKind],
        );
    }

    private function planBlocked(ConnectorCatalogItemNormalization $normalization, ExternalMediaKind $kind, ImportPlanReason $reason): PlannedImportItem
    {
        return new PlannedImportItem(
            kind: $kind,
            action: ImportPlannedAction::Blocked,
            status: ImportPlanItemStatus::Blocked,
            targetTitle: $this->displayTitle($normalization->normalized_title),
            targetKey: null,
            targetParentKey: null,
            targetYear: $normalization->release_year,
            targetSeasonNumber: $normalization->season_number,
            targetEpisodeNumber: $normalization->episode_number,
            confidence: $normalization->confidence,
            reasons: [$reason, ImportPlanReason::UnsafeToImport],
        );
    }

    private function planReview(ConnectorCatalogItemNormalization $normalization, ExternalMediaKind $kind, ImportPlanReason $reason): PlannedImportItem
    {
        return new PlannedImportItem(
            kind: $kind,
            action: ImportPlannedAction::NeedsReview,
            status: ImportPlanItemStatus::NeedsReview,
            targetTitle: $this->displayTitle($normalization->normalized_title),
            targetKey: null,
            targetParentKey: null,
            targetYear: $normalization->release_year,
            targetSeasonNumber: $normalization->season_number,
            targetEpisodeNumber: $normalization->episode_number,
            confidence: $normalization->confidence,
            reasons: [$reason],
        );
    }

    /**
     * (H) Fold duplicate suspicion into an existing plan. A ready/warning item
     * drops to `skip_duplicate` + `needs_review`; an item that is already blocked or
     * skipped keeps its worse verdict and just gains the reason. Nothing is merged,
     * and no duplicate is ever resolved automatically.
     *
     * Since V2 E.1 the caller only sets this for the EXTRA copies of an identity —
     * one copy of each duplicated thing stays plannable (see
     * CreateMediaImportPlan::extraCopies), so a duplicated episode yields one
     * episode plus a review note, instead of nothing at all.
     */
    private function withDuplicateSuspicion(PlannedImportItem $planned): PlannedImportItem
    {
        if ($planned->hasReason(ImportPlanReason::DuplicateSuspect)) {
            return $planned;
        }

        $downgrade = in_array($planned->status, [ImportPlanItemStatus::Ready, ImportPlanItemStatus::Warning], true);

        return new PlannedImportItem(
            kind: $planned->kind,
            action: $downgrade ? ImportPlannedAction::SkipDuplicate : $planned->action,
            status: $downgrade ? ImportPlanItemStatus::NeedsReview : $planned->status,
            targetTitle: $planned->targetTitle,
            targetKey: $planned->targetKey,
            targetParentKey: $planned->targetParentKey,
            targetYear: $planned->targetYear,
            targetSeasonNumber: $planned->targetSeasonNumber,
            targetEpisodeNumber: $planned->targetEpisodeNumber,
            confidence: max(0, $planned->confidence - self::DUPLICATE_PENALTY),
            reasons: $this->withoutReadyMarker([...$planned->reasons, ImportPlanReason::DuplicateSuspect]),
        );
    }

    /**
     * @param  list<ImportPlanReason>  $reasons
     * @return list<ImportPlanReason>
     */
    private function withoutReadyMarker(array $reasons): array
    {
        return array_values(array_filter(
            $reasons,
            static fn (ImportPlanReason $reason): bool => $reason !== ImportPlanReason::ReadyToImport,
        ));
    }

    /* -----------------------------------------------------------------------
     | Stable target identities
     * -------------------------------------------------------------------- */

    private function mediaKey(ExternalMediaKind $kind, ConnectorCatalogItemNormalization $normalization): string
    {
        return $this->key([$kind->value, $normalization->normalized_title, (string) $normalization->release_year]);
    }

    private function seriesKey(string $title, ?int $year): string
    {
        return $this->key(['series', $title, (string) $year]);
    }

    private function seasonKey(string $parentTitle, ?int $season): string
    {
        return $this->key(['season', $parentTitle, (string) $season]);
    }

    private function episodeKey(string $parentTitle, ?int $season, ?int $episode): string
    {
        return $this->key(['episode', $parentTitle, (string) $season, (string) $episode]);
    }

    /**
     * A stable, opaque identity for a hypothetical target. Hashed and case-folded so
     * it is deterministic across runs, carries no title text into a key column, and
     * — importantly — is not a path: V2 D plans no file location whatsoever.
     *
     * @param  list<string>  $parts
     */
    private function key(array $parts): string
    {
        $normalized = array_map(static fn (string $part): string => mb_strtolower(trim($part)), $parts);

        return substr(hash('xxh128', implode('|', $normalized)), 0, 32);
    }

    private function displayTitle(string $title): string
    {
        $trimmed = trim($title);

        return $trimmed === '' ? '(untitled)' : $trimmed;
    }

    /**
     * The normalization issues as enum cases, dropping any code we no longer know.
     *
     * @return list<NormalizationIssue>
     */
    private function issues(ConnectorCatalogItemNormalization $normalization): array
    {
        $issues = [];

        foreach ($normalization->issues as $code) {
            $issue = NormalizationIssue::tryFrom($code);

            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }
}
