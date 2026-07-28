<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Import;

use App\Connectors\Sdk\Models\MediaImportPlanItem;

/**
 * Decides whether one V2 D plan line may become an internal record (V2 E).
 *
 * This is the single place where "may we import this?" is answered, and it is
 * deliberately PURE: no database, no network, no file. Everything else in the
 * import trusts it, and a test can exercise every refusal without a fixture.
 *
 * The rule is allowlist-only and intentionally narrow. A line is importable when
 * ALL of the following hold:
 *
 *   - the plan marked it `ready` — never warning, needs_review, blocked or skipped;
 *   - its action is create_media / create_container / attach_to_parent;
 *   - its kind is one the internal import covers (movie, series, season, episode,
 *     audiobook, book) — never folder, playlist, podcast, music or unknown;
 *   - the action is the one that kind is supposed to have.
 *
 * Anything else is refused with an explicit reason. Duplicate suspects and weak
 * metadata are refused by construction, because V2 D already downgraded them out of
 * `ready`; this gate checks that independently rather than assuming it.
 */
final class ImportPlanItemGate
{
    /** Actions that can, in principle, produce an internal record. */
    private const IMPORTING_ACTIONS = [
        ImportPlannedAction::CreateMedia,
        ImportPlannedAction::CreateContainer,
        ImportPlannedAction::AttachToParent,
    ];

    public function evaluate(MediaImportPlanItem $item): ImportEligibility
    {
        $action = ImportPlannedAction::tryFrom($item->planned_action);
        $status = ImportPlanItemStatus::tryFrom($item->status);
        $kind = ImportableMediaKind::fromPlannedKind($item->planned_kind);

        // A duplicate suspect is checked FIRST, so it is reported as the duplicate
        // it is rather than as a generic "not ready" line.
        if ($action === ImportPlannedAction::SkipDuplicate || $this->claimsDuplicate($item)) {
            return ImportEligibility::skip(
                ImportExecutionAction::SkippedDuplicate,
                [ImportExecutionReason::DuplicateNotImported],
            );
        }

        // Structurally not media (a folder, a playlist) or a kind the import model
        // does not cover (podcast, music). V2 D already said so; no human decision
        // will change it, which is what separates this from "needs review".
        if ($action === ImportPlannedAction::SkipUnsupported) {
            return ImportEligibility::skip(
                ImportExecutionAction::SkippedUnsupported,
                [ImportExecutionReason::UnsupportedKind],
            );
        }

        // Checked BEFORE the kind, deliberately: an unclassifiable item is a line a
        // human can still fix, so it belongs in the review queue rather than being
        // written off as permanently unsupported.
        if ($status === ImportPlanItemStatus::NeedsReview || $action === ImportPlannedAction::NeedsReview) {
            return ImportEligibility::skip(
                ImportExecutionAction::SkippedNotReady,
                [ImportExecutionReason::NeedsReviewFirst],
            );
        }

        if ($kind === null) {
            return ImportEligibility::skip(
                ImportExecutionAction::SkippedUnsupported,
                [ImportExecutionReason::UnsupportedKind],
            );
        }

        if ($status !== ImportPlanItemStatus::Ready || $action === null || !in_array($action, self::IMPORTING_ACTIONS, true)) {
            return ImportEligibility::skip(
                ImportExecutionAction::SkippedNotReady,
                [ImportExecutionReason::PlanItemNotReady],
            );
        }

        // A ready line whose action does not match its kind (an "attach" movie, a
        // "create media" season) is inconsistent. We refuse rather than reinterpret.
        if ($action !== $kind->expectedPlannedAction()) {
            return ImportEligibility::skip(
                ImportExecutionAction::SkippedNotReady,
                [ImportExecutionReason::PlanItemNotReady],
            );
        }

        return ImportEligibility::importable($kind);
    }

    /** V2 D records duplicate suspicion as a reason code on the plan line. */
    private function claimsDuplicate(MediaImportPlanItem $item): bool
    {
        return in_array(ImportPlanReason::DuplicateSuspect->value, $item->reasons, true);
    }
}
