import Badge, { type BadgeTone } from '@/Components/UI/Badge';
import type { ExternalMediaKind } from '@/Components/Connectors/ConnectorStatus';
import type { ImportPlanReasonView, ImportPlanScope } from '@/Components/Imports/ImportPlanStatus';

/**
 * V2 E — internal import execution view types.
 *
 * An execution creates MediaForge DATABASE records and nothing else. There is no
 * accept, merge, move, rename, delete or push-to-remote anywhere in this feature.
 */

export type ImportExecutionStatus = 'completed' | 'completed_with_warnings' | 'failed' | 'empty';

export type ImportExecutionAction =
    | 'created'
    | 'linked_existing'
    | 'skipped_not_ready'
    | 'skipped_unsupported'
    | 'skipped_duplicate'
    | 'failed';

export type ImportExecutionItemStatus = 'completed' | 'skipped' | 'failed';

/** One internal import run (MediaImportReadModel::executionView). */
export interface ImportExecutionView {
    id: string;
    plan_id: string;
    status: ImportExecutionStatus;
    scope: ImportPlanScope | null;
    imported_count: number;
    skipped_count: number;
    already_existing_count: number;
    failed_count: number;
    candidate_count: number;
    reasons: ImportPlanReasonView[];
    note: string;
    created_at: string | null;
}

/** One line of a run (MediaImportReadModel::itemView). */
export interface ImportExecutionItemView {
    id: string;
    title: string;
    media_kind: ExternalMediaKind;
    action: ImportExecutionAction;
    action_label: string;
    status: ImportExecutionItemStatus;
    /**
     * The internal record's id. Deliberately NOT rendered as a link: there is no
     * media item detail route yet, and a dead link is worse than none.
     */
    media_item_id: string | null;
    reasons: { code: string; message: string }[];
    connector: string | null;
    library_name: string | null;
}

export interface ImportExecutionSection {
    data: ImportExecutionItemView[];
    total: number;
    shown: number;
}

/** What actually lives in the internal catalog (MediaImportReadModel). */
export interface InternalMediaSummary {
    media_items: number;
    imported_items: number;
    movies: number;
    series: number;
    seasons: number;
    episodes: number;
    books: number;
    execution_count: number;
    latest_execution: ImportExecutionView | null;
    plans_needing_review: number;
}

/** The sentence that must be visible wherever an internal import is shown. */
export const INTERNAL_IMPORT_SAFETY_NOTE =
    'Internal import only. No files are copied, moved, deleted or renamed.';

const EXECUTION_STATUS_META: Record<ImportExecutionStatus, { label: string; tone: BadgeTone }> = {
    completed: { label: 'Completed', tone: 'success' },
    completed_with_warnings: { label: 'Completed with warnings', tone: 'warning' },
    failed: { label: 'Failed', tone: 'error' },
    empty: { label: 'Nothing to import', tone: 'neutral' },
};

export function ImportExecutionStatusBadge({ status }: { status: ImportExecutionStatus }) {
    const meta = EXECUTION_STATUS_META[status] ?? EXECUTION_STATUS_META.empty;

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

export function executionStatusLabel(status: ImportExecutionStatus): string {
    return (EXECUTION_STATUS_META[status] ?? EXECUTION_STATUS_META.empty).label;
}

/** Why the run came out the way it did — plain language, no jargon. */
export function executionExplanation(execution: ImportExecutionView): string {
    switch (execution.status) {
        case 'completed':
            return 'Every ready line became a MediaForge database record. No files were touched.';
        case 'completed_with_warnings':
            return 'Ready lines were imported. Some were already imported, or were skipped because a human has to decide first.';
        case 'failed':
            return 'The run was rolled back. Nothing was created and no file was touched.';
        default:
            return 'This plan had no ready lines, so nothing was imported.';
    }
}

const ITEM_STATUS_META: Record<ImportExecutionItemStatus, { label: string; tone: BadgeTone }> = {
    completed: { label: 'Done', tone: 'success' },
    skipped: { label: 'Skipped', tone: 'neutral' },
    failed: { label: 'Failed', tone: 'error' },
};

export function ImportExecutionItemStatusBadge({ status }: { status: ImportExecutionItemStatus }) {
    const meta = ITEM_STATUS_META[status] ?? ITEM_STATUS_META.skipped;

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

/** V2 E: whether a captured catalog item already has an internal record. */
export type CatalogImportStatus = 'imported' | 'not_imported' | 'needs_review';

const CATALOG_IMPORT_META: Record<CatalogImportStatus, { label: string; tone: BadgeTone }> = {
    imported: { label: 'Imported', tone: 'success' },
    not_imported: { label: 'Not imported', tone: 'neutral' },
    needs_review: { label: 'Needs review', tone: 'warning' },
};

export function CatalogImportStatusBadge({ status }: { status: CatalogImportStatus }) {
    const meta = CATALOG_IMPORT_META[status] ?? CATALOG_IMPORT_META.not_imported;

    return <Badge tone={meta.tone}>{meta.label}</Badge>;
}
