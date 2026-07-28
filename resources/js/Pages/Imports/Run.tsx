import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { formatCheckedAt, mediaKindLabel } from '@/Components/Connectors/ConnectorStatus';
import {
    executionExplanation,
    type ImportExecutionItemView,
    ImportExecutionItemStatusBadge,
    type ImportExecutionSection,
    ImportExecutionStatusBadge,
    type ImportExecutionView,
    INTERNAL_IMPORT_SAFETY_NOTE,
} from '@/Components/Imports/ImportExecutionStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { ImportIcon, ShieldIcon } from '@/Components/UI/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ImportRunPageProps {
    [key: string]: unknown;
    execution: ImportExecutionView;
    sections: {
        created: ImportExecutionSection;
        linked_existing: ImportExecutionSection;
        skipped: ImportExecutionSection;
        failed: ImportExecutionSection;
    };
    flash: { success: string | null; error: string | null };
}

/** One line of the run. Informational — there is nothing here to click or undo. */
function ItemRow({ item }: { item: ImportExecutionItemView }) {
    const source = [item.connector, item.library_name].filter(Boolean).join(' · ');

    return (
        <tr className="border-t border-[var(--panel-border)] align-top">
            <td className="px-4 py-3">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{item.title}</span>
                    <Badge tone="neutral">{mediaKindLabel(item.media_kind)}</Badge>
                </span>
                <span className="mt-0.5 block text-xs text-fg-subtle">{source || '—'}</span>
            </td>
            <td className="px-4 py-3 text-sm text-fg-muted">{item.action_label}</td>
            <td className="px-4 py-3">
                <ImportExecutionItemStatusBadge status={item.status} />
            </td>
            <td className="px-4 py-3 text-xs text-fg-muted">
                {item.reasons.length === 0 ? '—' : item.reasons.map((reason) => reason.message).join(' ')}
            </td>
        </tr>
    );
}

function RunSection({
    title,
    description,
    section,
    emptyTitle,
    emptyDescription,
}: {
    title: string;
    description: ReactNode;
    section: ImportExecutionSection;
    emptyTitle: string;
    emptyDescription: string;
}) {
    return (
        <section className="mf-col-12">
            <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div className="min-w-0">
                    <h2 className="text-lg font-semibold tracking-tight">
                        {title} <span className="text-sm font-normal text-fg-subtle">({section.total})</span>
                    </h2>
                    <p className="mt-1 text-sm text-fg-muted">{description}</p>
                </div>
                {section.shown < section.total && (
                    <Badge tone="neutral">
                        Showing {section.shown} of {section.total}
                    </Badge>
                )}
            </div>

            {section.data.length === 0 ? (
                <EmptyState description={emptyDescription} icon={<ImportIcon className="size-5" />} title={emptyTitle} />
            ) : (
                <div className="mf-panel overflow-x-auto">
                    <table className="w-full min-w-[48rem] text-left text-sm">
                        <thead className="text-xs uppercase tracking-wide text-fg-subtle">
                            <tr>
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">What happened</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Why</th>
                            </tr>
                        </thead>
                        <tbody>
                            {section.data.map((item) => (
                                <ItemRow item={item} key={item.id} />
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export default function ImportRun() {
    const { execution, sections, flash } = usePage<ImportRunPageProps>().props;

    return (
        <>
            <Head title="Internal Import Execution" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 text-sm">
                                <Link className="text-fg-muted transition-colors hover:text-fg" href="/imports">
                                    Import Plans
                                </Link>
                                <span className="text-fg-subtle">/</span>
                                <span className="text-fg-muted">Internal import</span>
                            </div>
                            <h1 className="mt-2 flex flex-wrap items-center gap-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                                Internal Import Execution
                                <ImportExecutionStatusBadge status={execution.status} />
                            </h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">{executionExplanation(execution)}</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Link className={buttonClasses('secondary', 'sm')} href={`/imports/${execution.plan_id}`}>
                                Back to plan
                            </Link>
                            <Link className={buttonClasses('ghost', 'sm')} href="/imports">
                                All import plans
                            </Link>
                        </div>
                    </header>

                    {flash.success && (
                        <div className="mf-col-12">
                            <Alert tone="success">{flash.success}</Alert>
                        </div>
                    )}
                    {flash.error && (
                        <div className="mf-col-12">
                            <Alert tone="error">{flash.error}</Alert>
                        </div>
                    )}

                    <section className="mf-col-12">
                        <Alert tone="info" title="Database records only">
                            This run created only MediaForge database records. No files were touched — nothing was copied,
                            moved, deleted or renamed — and nothing was written to Jellyfin or Audiobookshelf.
                        </Alert>
                    </section>

                    {/* Summary */}
                    <section className="mf-col-12">
                        <div className="mf-panel p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold">Run summary</p>
                                    <p className="mt-1 text-xs text-fg-subtle">
                                        {formatCheckedAt(execution.created_at)} · {execution.candidate_count} ready{' '}
                                        {execution.candidate_count === 1 ? 'line' : 'lines'} considered
                                    </p>
                                </div>
                                <ImportExecutionStatusBadge status={execution.status} />
                            </div>

                            <dl className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {[
                                    ['Imported', execution.imported_count],
                                    ['Linked existing', execution.already_existing_count],
                                    ['Skipped', execution.skipped_count],
                                    ['Failed', execution.failed_count],
                                ].map(([label, value]) => (
                                    <div className="mf-card px-4 py-3 text-center" key={label}>
                                        <dt className="text-xs uppercase tracking-wide text-fg-subtle">{label}</dt>
                                        <dd className="mt-1 text-xl font-semibold">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </section>

                    {execution.reasons.length > 0 && (
                        <section className="mf-col-12">
                            <h2 className="mb-3 text-lg font-semibold tracking-tight">Why</h2>
                            <div className="mf-panel p-5">
                                <ul className="divide-y divide-[var(--panel-border)]">
                                    {execution.reasons.map((reason) => (
                                        <li className="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0" key={reason.code}>
                                            <span className="min-w-0 text-sm text-fg-muted">{reason.message}</span>
                                            <Badge tone="neutral">
                                                {reason.item_count ?? 0} {reason.item_count === 1 ? 'item' : 'items'}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </section>
                    )}

                    <RunSection
                        description="New MediaForge database records. No file was created, copied or moved to produce them."
                        emptyDescription="This run created no new records."
                        emptyTitle="Nothing created"
                        section={sections.created}
                        title="Created media items"
                    />

                    <RunSection
                        description="Already imported by an earlier run. The existing record was linked, never duplicated or overwritten."
                        emptyDescription="Nothing in this run had been imported before."
                        emptyTitle="Nothing linked"
                        section={sections.linked_existing}
                        title="Linked existing"
                    />

                    <RunSection
                        description="Deliberately not imported: still needing review, blocked, a duplicate suspect, or a kind the import does not cover."
                        emptyDescription="Every line in this plan was importable."
                        emptyTitle="Nothing skipped"
                        section={sections.skipped}
                        title="Skipped"
                    />

                    <RunSection
                        description="Lines that could not be imported. They were recorded rather than silently dropped."
                        emptyDescription="No line failed in this run."
                        emptyTitle="Nothing failed"
                        section={sections.failed}
                        title="Failed"
                    />

                    <section className="mf-col-12">
                        <div className="mf-panel flex items-start gap-3 p-5">
                            <span className="grid size-9 shrink-0 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                <ShieldIcon className="size-4" />
                            </span>
                            <p className="text-xs text-fg-muted">
                                {INTERNAL_IMPORT_SAFETY_NOTE} {execution.note}
                            </p>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
