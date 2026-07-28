import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { formatCheckedAt } from '@/Components/Connectors/ConnectorStatus';
import {
    ImportExecutionStatusBadge,
    type ImportExecutionView,
    INTERNAL_IMPORT_SAFETY_NOTE,
    type InternalMediaSummary,
} from '@/Components/Imports/ImportExecutionStatus';
import {
    IMPORT_SAFETY_NOTE,
    ImportPlanStatusBadge,
    type ImportPlanStatus,
    type ImportPlanView,
    planStatusExplanation,
    scopeLabel,
} from '@/Components/Imports/ImportPlanStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button, { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { ImportIcon, ShieldIcon } from '@/Components/UI/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface ImportsSummary {
    plan_count: number;
    latest_status: ImportPlanStatus;
    planned_items: number;
    ready_items: number;
    warning_items: number;
    blocked_items: number;
    review_items: number;
    duplicate_items: number;
    unsupported_items: number;
    skipped_items: number;
}

interface ImportsPageProps {
    [key: string]: unknown;
    summary: ImportsSummary;
    latestPlan: ImportPlanView | null;
    plans: ImportPlanView[];
    connectors: { key: string; label: string; configured: boolean }[];
    /** V2 E: plan id => how many internal imports it already had. */
    executionCounts: Record<string, number>;
    executions: ImportExecutionView[];
    internalMedia: InternalMediaSummary;
    flash: { success: string | null; error: string | null };
}

export default function ImportsIndex() {
    const { summary, latestPlan, plans, connectors, executionCounts, executions, internalMedia, flash } =
        usePage<ImportsPageProps>().props;
    const [running, setRunning] = useState(false);

    /** POST-only: creates a plan row. It imports nothing and touches no file. */
    function createDryRun(scope: 'all' | 'connector', connector?: string) {
        setRunning(true);
        router.post(
            '/imports/dry-run',
            connector ? { scope, connector } : { scope },
            { onFinish: () => setRunning(false) },
        );
    }

    const cards = [
        { label: 'Planned items', value: String(summary.planned_items), hint: 'In the latest dry run' },
        { label: 'Ready to import later', value: String(summary.ready_items), hint: 'Unambiguous' },
        { label: 'Needs review', value: String(summary.review_items), hint: 'A human decides first' },
        { label: 'Blocked', value: String(summary.blocked_items), hint: 'Cannot be planned' },
        { label: 'Duplicate suspects', value: String(summary.duplicate_items), hint: 'Never merged automatically' },
        { label: 'Unsupported', value: String(summary.unsupported_items), hint: 'Not media, or out of scope' },
        { label: 'Warnings', value: String(summary.warning_items), hint: 'Usable but incomplete' },
        { label: 'Dry runs', value: String(summary.plan_count), hint: 'Stored plans' },
    ];

    return (
        <>
            <Head title="Import Plans" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <span className="mf-status-pill mb-3">Import Plans</span>
                            <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Import Plans</h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">
                                What a later import would create from the captured, normalized external catalog — and what has
                                to be decided first.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Link className={buttonClasses('secondary', 'sm')} href="/catalog">
                                Open catalog
                            </Link>
                            <Button loading={running} onClick={() => createDryRun('all')} size="sm">
                                Create full import dry run
                            </Button>
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

                    {/* The promise, stated before anything else on the page. */}
                    <section className="mf-col-12">
                        <Alert tone="info" title="Dry run, then a database-only import">
                            {IMPORT_SAFETY_NOTE} {INTERNAL_IMPORT_SAFETY_NOTE} Importing a plan's ready lines creates MediaForge
                            database records only — nothing is accepted, merged, or written to Jellyfin or Audiobookshelf.
                        </Alert>
                    </section>

                    {/* V2 E — what actually made it into the internal catalog. */}
                    <section className="mf-col-12">
                        <div className="mf-panel p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-lg font-semibold tracking-tight">Internal media</h2>
                                    <p className="mt-1 text-sm text-fg-muted">
                                        MediaForge database records created by an internal import. No file was touched to
                                        produce any of them.
                                    </p>
                                </div>
                                {internalMedia.latest_execution && (
                                    <Link
                                        className={buttonClasses('secondary', 'sm')}
                                        href={`/imports/runs/${internalMedia.latest_execution.id}`}
                                    >
                                        Open latest run
                                    </Link>
                                )}
                            </div>

                            <dl className="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                                {[
                                    ['Media items', internalMedia.media_items],
                                    ['Movies', internalMedia.movies],
                                    ['Series', internalMedia.series],
                                    ['Seasons', internalMedia.seasons],
                                    ['Episodes', internalMedia.episodes],
                                    ['Books', internalMedia.books],
                                ].map(([label, value]) => (
                                    <div className="mf-card px-4 py-3 text-center" key={label}>
                                        <dt className="text-xs uppercase tracking-wide text-fg-subtle">{label}</dt>
                                        <dd className="mt-1 text-xl font-semibold">{value}</dd>
                                    </div>
                                ))}
                            </dl>

                            <p className="mt-3 text-xs text-fg-subtle">
                                {internalMedia.execution_count} internal{' '}
                                {internalMedia.execution_count === 1 ? 'import run' : 'import runs'} ·{' '}
                                {internalMedia.plans_needing_review}{' '}
                                {internalMedia.plans_needing_review === 1 ? 'plan needs' : 'plans need'} review
                            </p>
                        </div>
                    </section>

                    {/* Summary cards */}
                    <section className="mf-col-12">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {cards.map((card) => (
                                <div className="mf-card p-5" key={card.label}>
                                    <p className="text-sm font-medium text-fg-muted">{card.label}</p>
                                    <p className="mt-3 text-2xl font-semibold tracking-tight">{card.value}</p>
                                    <p className="mt-1.5 text-sm text-fg-subtle">{card.hint}</p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Main column: latest dry run + history */}
                    <section className="mf-col-8 flex flex-col gap-6">
                        <div>
                            <h2 className="mb-3 text-lg font-semibold tracking-tight">Latest import dry run</h2>
                            {!latestPlan ? (
                                <EmptyState
                                    action={
                                        <Button loading={running} onClick={() => createDryRun('all')}>
                                            Create full import dry run
                                        </Button>
                                    }
                                    description="No dry run has been created yet. A dry run reads the captured catalog and writes a plan — it imports nothing and touches no file."
                                    icon={<ImportIcon className="size-5" />}
                                    title="No import plans yet"
                                />
                            ) : (
                                <div className="mf-panel p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="flex flex-wrap items-center gap-2 font-semibold">
                                                {scopeLabel(latestPlan)}
                                                <ImportPlanStatusBadge status={latestPlan.status} />
                                            </p>
                                            <p className="mt-1 text-xs text-fg-subtle">
                                                {formatCheckedAt(latestPlan.created_at)} · {latestPlan.planned_item_count} planned
                                                of {latestPlan.source_item_count} captured
                                            </p>
                                        </div>
                                        <Link className={buttonClasses('secondary', 'sm')} href={`/imports/${latestPlan.id}`}>
                                            Open plan
                                        </Link>
                                    </div>

                                    <p className="mt-3 text-sm text-fg-muted">{planStatusExplanation(latestPlan)}</p>

                                    <dl className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {[
                                            ['Ready', latestPlan.ready_count],
                                            ['Warnings', latestPlan.warning_count],
                                            ['Needs review', latestPlan.review_count],
                                            ['Blocked', latestPlan.blocked_count],
                                        ].map(([label, value]) => (
                                            <div className="mf-panel px-3 py-2 text-center" key={label}>
                                                <dt className="text-[0.7rem] uppercase tracking-wide text-fg-subtle">{label}</dt>
                                                <dd className="mt-1 text-sm font-semibold">{value}</dd>
                                            </div>
                                        ))}
                                    </dl>

                                    {latestPlan.truncated && (
                                        <Alert className="mt-4" tone="warning">
                                            More captured items exist than one plan may hold (cap {latestPlan.cap}). This plan is a
                                            bounded subset.
                                        </Alert>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* V2 E — the internal import runs. */}
                        <div>
                            <h2 className="mb-3 text-lg font-semibold tracking-tight">Latest internal imports</h2>
                            {executions.length === 0 ? (
                                <EmptyState
                                    description="No plan has been imported yet. Open a plan with ready items and use “Import ready items into MediaForge”. It writes database records only."
                                    icon={<ImportIcon className="size-5" />}
                                    title="No internal imports yet"
                                />
                            ) : (
                                <div className="mf-panel divide-y divide-[var(--panel-border)]">
                                    {executions.map((execution) => (
                                        <Link
                                            className="flex flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:text-accent"
                                            href={`/imports/runs/${execution.id}`}
                                            key={execution.id}
                                        >
                                            <span className="min-w-0">
                                                <span className="flex flex-wrap items-center gap-2 font-medium">
                                                    {execution.imported_count} imported
                                                    <ImportExecutionStatusBadge status={execution.status} />
                                                </span>
                                                <span className="mt-1 block text-xs text-fg-subtle">
                                                    {execution.already_existing_count} already imported ·{' '}
                                                    {execution.skipped_count} skipped · {execution.failed_count} failed
                                                </span>
                                            </span>
                                            <span className="text-xs text-fg-subtle">{formatCheckedAt(execution.created_at)}</span>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div>
                            <h2 className="mb-3 text-lg font-semibold tracking-tight">Recent dry runs</h2>
                            {plans.length === 0 ? (
                                <EmptyState
                                    description="Dry runs you create will be listed here with their outcome."
                                    icon={<ImportIcon className="size-5" />}
                                    title="No dry runs recorded"
                                />
                            ) : (
                                <div className="mf-panel divide-y divide-[var(--panel-border)]">
                                    {plans.map((plan) => (
                                        <Link
                                            className="flex flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:text-accent"
                                            href={`/imports/${plan.id}`}
                                            key={plan.id}
                                        >
                                            <span className="min-w-0">
                                                <span className="flex flex-wrap items-center gap-2 font-medium">
                                                    {scopeLabel(plan)}
                                                    <ImportPlanStatusBadge status={plan.status} />
                                                    {(executionCounts[plan.id] ?? 0) > 0 && (
                                                        <Badge tone="success">Imported</Badge>
                                                    )}
                                                </span>
                                                <span className="mt-1 block text-xs text-fg-subtle">
                                                    {plan.ready_count} ready · {plan.warning_count} warnings ·{' '}
                                                    {plan.review_count} need review · {plan.blocked_count} blocked ·{' '}
                                                    {plan.skipped_count} skipped
                                                </span>
                                            </span>
                                            <span className="text-xs text-fg-subtle">{formatCheckedAt(plan.created_at)}</span>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    {/* Side column */}
                    <section className="mf-col-4">
                        <div className="grid gap-4">
                            <div className="mf-panel p-5">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-fg-subtle">
                                    Scoped dry runs
                                </h2>
                                <p className="text-sm text-fg-muted">
                                    Plan one connector at a time, or open a library in the catalog to plan just that library.
                                </p>
                                <div className="mt-3 grid gap-2">
                                    {connectors.map((connector) => (
                                        <div
                                            className="flex items-center justify-between gap-3 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5 text-sm"
                                            key={connector.key}
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate font-medium">{connector.label}</span>
                                                {!connector.configured && <Badge tone="neutral">Not configured</Badge>}
                                            </span>
                                            <Button
                                                disabled={!connector.configured}
                                                loading={running}
                                                onClick={() => createDryRun('connector', connector.key)}
                                                size="sm"
                                                variant="secondary"
                                            >
                                                Dry run
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="mf-panel flex items-start gap-3 p-5">
                                <span className="grid size-9 shrink-0 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                    <ShieldIcon className="size-4" />
                                </span>
                                <p className="text-xs text-fg-muted">{IMPORT_SAFETY_NOTE}</p>
                            </div>

                            <div className="mf-panel p-5">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-fg-subtle">Review</h2>
                                <p className="text-sm text-fg-muted">
                                    A plan that is blocked or needs decisions raises one review task per dry run.
                                </p>
                                <Link className={`${buttonClasses('secondary', 'sm')} mt-3`} href="/review">
                                    Open review center
                                </Link>
                            </div>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
