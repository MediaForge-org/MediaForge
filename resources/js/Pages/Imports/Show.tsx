import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { episodeLabel, formatCheckedAt, mediaKindLabel } from '@/Components/Connectors/ConnectorStatus';
import {
    IMPORT_SAFETY_NOTE,
    type ImportPlanItemView,
    ImportPlanItemStatusBadge,
    type ImportPlanSection,
    ImportPlanStatusBadge,
    type ImportPlanTarget,
    type ImportPlanView,
    planStatusExplanation,
    scopeLabel,
} from '@/Components/Imports/ImportPlanStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { ImportIcon, ShieldIcon } from '@/Components/UI/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ImportPlanShowProps {
    [key: string]: unknown;
    plan: ImportPlanView;
    sections: {
        ready: ImportPlanSection;
        warning: ImportPlanSection;
        needs_review: ImportPlanSection;
        blocked: ImportPlanSection;
        skipped: ImportPlanSection;
    };
    duplicates: ImportPlanSection;
    targets: ImportPlanTarget[];
    flash: { success: string | null; error: string | null };
}

/** One planned line, rendered as a table row. Purely informational. */
function ItemRow({ item }: { item: ImportPlanItemView }) {
    const episode = episodeLabel(item.target_season_number, item.target_episode_number);
    const facts = [episode, item.target_year, item.connector?.label, item.library_name].filter(Boolean).join(' · ');

    return (
        <tr className="border-t border-[var(--panel-border)] align-top">
            <td className="px-4 py-3">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{item.target_title}</span>
                    <Badge tone="neutral">{mediaKindLabel(item.planned_kind)}</Badge>
                </span>
                <span className="mt-0.5 block text-xs text-fg-subtle">{facts || '—'}</span>
            </td>
            <td className="px-4 py-3 text-sm text-fg-muted">{item.planned_action_label}</td>
            <td className="px-4 py-3">
                <ImportPlanItemStatusBadge status={item.status} />
            </td>
            <td className="px-4 py-3 text-sm text-fg-subtle">{item.confidence}%</td>
            <td className="px-4 py-3 text-xs text-fg-muted">
                {item.reasons.length === 0 ? '—' : item.reasons.map((reason) => reason.message).join(' ')}
            </td>
        </tr>
    );
}

/** A bounded outcome section: what it means, then the lines it holds. */
function PlanSection({
    title,
    description,
    section,
    emptyTitle,
    emptyDescription,
}: {
    title: string;
    description: ReactNode;
    section: ImportPlanSection;
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
                    <table className="w-full min-w-[52rem] text-left text-sm">
                        <thead className="text-xs uppercase tracking-wide text-fg-subtle">
                            <tr>
                                <th className="px-4 py-3 font-medium">Title</th>
                                <th className="px-4 py-3 font-medium">Planned action</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Confidence</th>
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

export default function ImportPlanShow() {
    const { plan, sections, duplicates, targets, flash } = usePage<ImportPlanShowProps>().props;

    return (
        <>
            <Head title="Import Dry Run" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 text-sm">
                                <Link className="text-fg-muted transition-colors hover:text-fg" href="/imports">
                                    Import Plans
                                </Link>
                                <span className="text-fg-subtle">/</span>
                                <span className="text-fg-muted">Dry run</span>
                            </div>
                            <h1 className="mt-2 flex flex-wrap items-center gap-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                                Import dry run
                                <ImportPlanStatusBadge status={plan.status} />
                            </h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">{planStatusExplanation(plan)}</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Link className={buttonClasses('secondary', 'sm')} href="/imports">
                                Back to import plans
                            </Link>
                            <Link className={buttonClasses('ghost', 'sm')} href="/catalog/matches">
                                View match preview
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
                        <Alert tone="info" title="Import dry run">
                            {IMPORT_SAFETY_NOTE} This page is a preview: there is no execute, import, accept or merge action in
                            it, and no media item, edition or file exists as a result of this plan.
                        </Alert>
                    </section>

                    {/* Plan header facts */}
                    <section className="mf-col-12">
                        <div className="mf-panel p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-semibold">{scopeLabel(plan)}</p>
                                    <p className="mt-1 text-xs text-fg-subtle">
                                        Created {formatCheckedAt(plan.created_at)} · {plan.planned_item_count} planned of{' '}
                                        {plan.source_item_count} captured
                                    </p>
                                </div>
                                <ImportPlanStatusBadge status={plan.status} />
                            </div>

                            <dl className="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                                {[
                                    ['Ready', plan.ready_count],
                                    ['Warnings', plan.warning_count],
                                    ['Needs review', plan.review_count],
                                    ['Blocked', plan.blocked_count],
                                    ['Skipped', plan.skipped_count],
                                    ['Duplicates', plan.duplicate_count],
                                ].map(([label, value]) => (
                                    <div className="mf-card px-4 py-3 text-center" key={label}>
                                        <dt className="text-xs uppercase tracking-wide text-fg-subtle">{label}</dt>
                                        <dd className="mt-1 text-xl font-semibold">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </section>

                    {plan.truncated && (
                        <section className="mf-col-12">
                            <Alert tone="warning">
                                More captured items exist than one plan may hold (cap {plan.cap}). This plan covers a bounded
                                subset of {plan.planned_item_count} of {plan.source_item_count} items.
                            </Alert>
                        </section>
                    )}

                    {/* Planned target structure */}
                    <section className="mf-col-12">
                        <h2 className="mb-3 text-lg font-semibold tracking-tight">Planned target structure</h2>
                        <p className="mb-3 text-sm text-fg-muted">
                            The logical shapes a later import would build. These are identities — titles, years, season and
                            episode numbers — not folders: MediaForge plans no file location, so there is nothing here to move
                            or rename.
                        </p>
                        {targets.length === 0 ? (
                            <EmptyState
                                description="This plan has no items, so there is no target structure to show."
                                icon={<ImportIcon className="size-5" />}
                                title="Nothing planned"
                            />
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {targets.map((target) => (
                                    <div className="mf-panel p-5" key={`${target.planned_kind}-${target.planned_action}`}>
                                        <p className="flex flex-wrap items-center gap-2 font-semibold">
                                            {mediaKindLabel(target.planned_kind)}
                                            <Badge tone="neutral">{target.item_count}</Badge>
                                        </p>
                                        <p className="mt-1 text-sm text-fg-muted">{target.planned_action_label}</p>
                                        <p className="mt-2 text-xs text-fg-subtle">
                                            {target.target_count} distinct {target.target_count === 1 ? 'target' : 'targets'} ·{' '}
                                            {target.ready_count} ready
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    {/* Why the plan reads the way it does */}
                    {plan.reasons.length > 0 && (
                        <section className="mf-col-12">
                            <h2 className="mb-3 text-lg font-semibold tracking-tight">Why</h2>
                            <div className="mf-panel p-5">
                                <ul className="divide-y divide-[var(--panel-border)]">
                                    {plan.reasons.map((reason) => (
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

                    <PlanSection
                        description="A later import could create these without asking anyone. Nothing has been created."
                        emptyDescription="No item in this plan is unambiguous yet."
                        emptyTitle="Nothing ready"
                        section={sections.ready}
                        title="Ready to import later"
                    />

                    <PlanSection
                        description="Importable later, but something is missing — most often a release year."
                        emptyDescription="No item in this plan carries a warning."
                        emptyTitle="No warnings"
                        section={sections.warning}
                        title="Warnings"
                    />

                    <PlanSection
                        description="A human has to decide before a later import may touch these. Nothing is decided here."
                        emptyDescription="No item in this plan needs a decision."
                        emptyTitle="Nothing to review"
                        section={sections.needs_review}
                        title="Needs review"
                    />

                    <PlanSection
                        description="These cannot be planned at all — a later import must not attempt them."
                        emptyDescription="No item in this plan is blocked."
                        emptyTitle="Nothing blocked"
                        section={sections.blocked}
                        title="Blocked"
                    />

                    <PlanSection
                        description="Structural containers and kinds the import model does not cover yet. Counted, never treated as errors."
                        emptyDescription="No item in this plan was skipped."
                        emptyTitle="Nothing skipped"
                        section={sections.skipped}
                        title="Skipped — unsupported"
                    />

                    <PlanSection
                        description="Two or more captured items claim the same identity. They are listed, never merged — deciding is a human's job."
                        emptyDescription="No two planned items share an identity."
                        emptyTitle="No duplicate suspects"
                        section={duplicates}
                        title="Duplicate suspects"
                    />

                    <section className="mf-col-12">
                        <div className="mf-panel flex items-start gap-3 p-5">
                            <span className="grid size-9 shrink-0 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                <ShieldIcon className="size-4" />
                            </span>
                            <p className="text-xs text-fg-muted">
                                {IMPORT_SAFETY_NOTE} {plan.note}
                            </p>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
