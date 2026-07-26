import Badge, { type BadgeTone } from '@/Components/UI/Badge';
import type { ConnectorRef, ExternalMediaKind } from '@/Components/Connectors/ConnectorStatus';

/**
 * V2 D — import plan / import dry run view types.
 *
 * A plan describes what a LATER import WOULD create. Nothing here executes: there
 * is no accept, merge, import or file operation anywhere in this feature.
 */

export type ImportPlanScope = 'all' | 'connector' | 'library';

export type ImportPlanStatus = 'ready' | 'warnings' | 'blocked' | 'empty';

export type ImportPlanItemStatus = 'ready' | 'warning' | 'needs_review' | 'blocked' | 'skipped';

export type ImportPlannedAction =
    | 'create_media'
    | 'create_container'
    | 'attach_to_parent'
    | 'skip_unsupported'
    | 'skip_duplicate'
    | 'needs_review'
    | 'blocked';

/** One sanitized plan reason ({ code, message }), optionally with a count. */
export interface ImportPlanReasonView {
    code: string;
    message: string;
    item_count?: number;
}

/** A plan header (ImportPlanReadModel::planView). */
export interface ImportPlanView {
    id: string;
    scope: ImportPlanScope;
    status: ImportPlanStatus;
    connector: ConnectorRef | null;
    library_name: string | null;
    source_item_count: number;
    planned_item_count: number;
    ready_count: number;
    warning_count: number;
    blocked_count: number;
    skipped_count: number;
    review_count: number;
    duplicate_count: number;
    unsupported_count: number;
    truncated: boolean;
    cap: number | null;
    reasons: ImportPlanReasonView[];
    note: string;
    created_at: string | null;
}

/** One planned line of a dry run (ImportPlanReadModel::itemView). */
export interface ImportPlanItemView {
    id: string;
    target_title: string;
    target_key: string | null;
    target_parent_key: string | null;
    planned_kind: ExternalMediaKind;
    planned_action: ImportPlannedAction;
    planned_action_label: string;
    status: ImportPlanItemStatus;
    confidence: number;
    target_year: number | null;
    target_season_number: number | null;
    target_episode_number: number | null;
    reasons: ImportPlanReasonView[];
    connector: ConnectorRef | null;
    library_name: string | null;
}

/** A bounded section of plan items, with the true total behind it. */
export interface ImportPlanSection {
    data: ImportPlanItemView[];
    total: number;
    shown: number;
}

/** The target shape a later import would build, aggregated by kind + action. */
export interface ImportPlanTarget {
    planned_kind: ExternalMediaKind;
    planned_action: ImportPlannedAction;
    planned_action_label: string;
    item_count: number;
    ready_count: number;
    target_count: number;
}

/** The single sentence that must be visible wherever a plan is shown. */
export const IMPORT_SAFETY_NOTE =
    'Dry run only. No media is imported and no files are copied, moved, deleted or renamed.';

const PLAN_STATUS_META: Record<ImportPlanStatus, { label: string; tone: BadgeTone }> = {
    ready: { label: 'Ready', tone: 'success' },
    warnings: { label: 'Warnings', tone: 'warning' },
    blocked: { label: 'Blocked', tone: 'error' },
    empty: { label: 'Empty', tone: 'neutral' },
};

export function ImportPlanStatusBadge({ status }: { status: ImportPlanStatus }) {
    const meta = PLAN_STATUS_META[status] ?? PLAN_STATUS_META.empty;

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

export function planStatusLabel(status: ImportPlanStatus): string {
    return (PLAN_STATUS_META[status] ?? PLAN_STATUS_META.empty).label;
}

/** Why the plan came out the way it did — plain language, no jargon. */
export function planStatusExplanation(plan: ImportPlanView): string {
    switch (plan.status) {
        case 'ready':
            return 'Every planned item is unambiguous. A later import would have nothing left to ask you about.';
        case 'warnings':
            return 'Some items are usable but incomplete, or need a decision (duplicates, weak metadata) before a later import.';
        case 'blocked':
            return 'At least one item cannot be planned at all. A later import must not attempt those.';
        default:
            return 'No captured item fell inside this scope. Take a read-only snapshot first, then rebuild normalization.';
    }
}

const ITEM_STATUS_META: Record<ImportPlanItemStatus, { label: string; tone: BadgeTone }> = {
    ready: { label: 'Ready', tone: 'success' },
    warning: { label: 'Warning', tone: 'warning' },
    needs_review: { label: 'Needs review', tone: 'error' },
    blocked: { label: 'Blocked', tone: 'error' },
    skipped: { label: 'Skipped', tone: 'neutral' },
};

export function ImportPlanItemStatusBadge({ status }: { status: ImportPlanItemStatus }) {
    const meta = ITEM_STATUS_META[status] ?? ITEM_STATUS_META.skipped;

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

const SCOPE_LABEL: Record<ImportPlanScope, string> = {
    all: 'All connectors',
    connector: 'One connector',
    library: 'One library',
};

export function scopeLabel(plan: ImportPlanView): string {
    const base = SCOPE_LABEL[plan.scope] ?? SCOPE_LABEL.all;
    const detail = [plan.connector?.label, plan.library_name].filter(Boolean).join(' · ');

    return detail ? `${base} — ${detail}` : base;
}
