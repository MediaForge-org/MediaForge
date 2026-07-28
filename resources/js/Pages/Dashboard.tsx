import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    CatalogStatusBadge,
    type ConnectorSummary,
    formatCheckedAt,
    StatusBadge,
    SyncStatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import {
    ImportExecutionStatusBadge,
    type InternalMediaSummary,
} from '@/Components/Imports/ImportExecutionStatus';
import {
    ImportPlanStatusBadge,
    type ImportPlanStatus,
} from '@/Components/Imports/ImportPlanStatus';
import Badge, { type BadgeTone } from '@/Components/UI/Badge';
import { buttonClasses } from '@/Components/UI/Button';
import { CatalogIcon, ImportIcon, LibraryIcon, ReviewIcon, ServerIcon, SettingsIcon, ShieldIcon, SyncIcon } from '@/Components/UI/Icon';
import StatCard, { type StatTone } from '@/Components/UI/StatCard';

interface SyncSummary {
    selected_libraries: number;
    attention_count: number;
    ready_count: number;
    last_dry_run_at: string | null;
}

type ReviewStatus = 'all_clear' | 'warnings' | 'attention_required';

interface ReviewSummary {
    status: ReviewStatus;
    open_task_count: number;
}

interface CatalogSummary {
    external_items: number;
    attention_count: number;
    last_snapshot_at: string | null;
}

/** V2 D: the latest import dry run. A plan, never an import. */
interface ImportSummary {
    plan_count: number;
    status: ImportPlanStatus;
    planned_items: number;
    ready_items: number;
    warning_items: number;
    blocked_items: number;
    created_at: string | null;
    plan_id: string | null;
}

interface DashboardPageProps {
    [key: string]: unknown;
    status: string;
    connectors: ConnectorSummary[];
    syncSummary: SyncSummary;
    reviewSummary: ReviewSummary;
    catalogSummary: CatalogSummary;
    importSummary: ImportSummary;
    /** V2 E: what the internal import has actually created. */
    internalMedia: InternalMediaSummary;
}

const REVIEW_STATUS_META: Record<ReviewStatus, { label: string; tone: BadgeTone }> = {
    all_clear: { label: 'All clear', tone: 'success' },
    warnings: { label: 'Warnings', tone: 'warning' },
    attention_required: { label: 'Attention required', tone: 'error' },
};

const NEXT_ACTIONS: { label: string; tone: BadgeTone; badge: string }[] = [
    { label: 'Configure connectors', tone: 'accent', badge: 'Current' },
    { label: 'Discover libraries', tone: 'accent', badge: 'Current' },
    { label: 'Run a sync dry run', tone: 'accent', badge: 'Current' },
    { label: 'Review metadata localization', tone: 'neutral', badge: 'Later V1' },
];

const ROADMAP: { id: string; label: string; done: boolean }[] = [
    { id: 'V1 A', label: 'Auth', done: true },
    { id: 'V1 B', label: 'App Shell', done: true },
    { id: 'V1 C', label: 'Connectors', done: true },
    { id: 'V1 D', label: 'Library Discovery', done: true },
    { id: 'V1 E', label: 'UI / UX', done: true },
    { id: 'V1 F', label: 'Sync Foundation', done: true },
];

export default function Dashboard() {
    const { status, connectors, syncSummary, reviewSummary, catalogSummary, importSummary, internalMedia } =
        usePage<DashboardPageProps>().props;
    const find = (key: string) => connectors.find((c) => c.key === key);
    const libraryTotal = connectors.reduce((sum, c) => sum + c.library_count, 0);

    const statusCards: { label: string; value: string; hint: string; tone: StatTone; icon: ReactNode }[] = [
        { label: 'Auth System', value: 'Authenticated', hint: 'Local session active', tone: 'success', icon: <ShieldIcon className="size-5" /> },
        { label: 'Local Server', value: 'Online', hint: 'Application responding', tone: 'success', icon: <ServerIcon className="size-5" /> },
        { label: 'Settings', value: 'Read-only', hint: 'Foundation', tone: 'neutral', icon: <SettingsIcon className="size-5" /> },
        { label: 'Library Discovery', value: `${libraryTotal} found`, hint: 'Local', tone: libraryTotal > 0 ? 'accent' : 'neutral', icon: <LibraryIcon className="size-5" /> },
    ];

    return (
        <>
            <Head title="Dashboard" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    {/* Hero */}
                    <section className="mf-hero mf-dash-hero mf-col-12 mf-rise relative grid items-center gap-8 p-8 lg:grid-cols-[1.35fr_0.85fr] xl:gap-12 xl:p-12">
                        <div className="mf-glow -left-16 -top-24 size-80 bg-accent/25" />
                        <div className="mf-glow right-1/4 top-1/2 size-80 bg-accent-2/20" />
                        <div className="relative flex flex-col items-start gap-5">
                            <span className="mf-status-pill">{status} · MediaForge V1 Workspace</span>
                            <h1 className="text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl xl:text-6xl">
                                Command your local <span className="mf-gradient-text">media engines</span>
                            </h1>
                            <p className="max-w-xl text-base text-fg-muted xl:text-lg">
                                Configure connectors, verify health and discover libraries from one workspace. Sync and media
                                import arrive in a later V1 package.
                            </p>
                            <div className="mt-2 flex flex-wrap gap-3">
                                <Link className={buttonClasses('primary')} href="/connectors">Open connectors</Link>
                                <Link className={buttonClasses('secondary')} href="/settings">View settings</Link>
                            </div>
                        </div>

                        {/* Engine radar widget (visual, from aggregates) */}
                        <div className="mf-panel relative flex flex-col gap-3.5 p-6 xl:p-7">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold">Engine radar</p>
                                <span className="mf-pulse size-2.5 rounded-full bg-success shadow-[0_0_10px_rgb(var(--status-success))]" />
                            </div>
                            {[find('jellyfin'), find('audiobookshelf')].map((c, idx) => (
                                <div className="flex items-center justify-between gap-3 text-sm" key={idx}>
                                    <span className="text-fg-muted">{c?.label ?? (idx === 0 ? 'Jellyfin' : 'Audiobookshelf')}</span>
                                    <StatusBadge status={c?.status ?? 'not_configured'} />
                                </div>
                            ))}
                            <div className="flex items-center justify-between gap-3 border-t border-[var(--panel-border)] pt-3 text-sm">
                                <span className="text-fg-muted">Libraries</span>
                                <span className="font-semibold">{libraryTotal}</span>
                            </div>
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span className="text-fg-muted">Local runtime</span>
                                <span className="font-medium text-success">Online</span>
                            </div>
                        </div>
                    </section>

                    {/* Status cards */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 1 } as CSSProperties}>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {statusCards.map((card) => (
                                <StatCard hint={card.hint} icon={card.icon} key={card.label} label={card.label} tone={card.tone} value={card.value} />
                            ))}
                        </div>
                    </section>

                    {/* Review Center (V1 G) */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 1.2 } as CSSProperties}>
                        <div className="mf-panel flex flex-wrap items-center justify-between gap-4 p-6">
                            <div className="flex items-center gap-3">
                                <span className="grid size-11 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                    <ReviewIcon className="size-6" />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-lg font-semibold tracking-tight">Review Center</h2>
                                        <Badge dot tone={REVIEW_STATUS_META[reviewSummary.status].tone}>
                                            {REVIEW_STATUS_META[reviewSummary.status].label}
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-fg-subtle">
                                        {reviewSummary.open_task_count} open {reviewSummary.open_task_count === 1 ? 'task' : 'tasks'} · Connector and dry-run issues in one place
                                    </p>
                                </div>
                            </div>
                            <Link className={buttonClasses('secondary', 'sm')} href="/review">Open review center</Link>
                        </div>
                    </section>

                    {/* External Catalog (V2 A) */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 1.3 } as CSSProperties}>
                        <div className="mf-panel p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-11 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                        <CatalogIcon className="size-6" />
                                    </span>
                                    <div>
                                        <h2 className="text-lg font-semibold tracking-tight">External Catalog</h2>
                                        <p className="text-xs text-fg-subtle">Read-only snapshots. No media import in V2 A.</p>
                                    </div>
                                </div>
                                <Link className={buttonClasses('secondary', 'sm')} href="/catalog">Open catalog</Link>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {[
                                    { label: 'External items', value: String(catalogSummary.external_items), hint: 'Captured, read-only' },
                                    { label: 'Last snapshot', value: formatCheckedAt(catalogSummary.last_snapshot_at), hint: 'Most recent, any connector' },
                                    { label: 'Attention required', value: String(catalogSummary.attention_count), hint: 'Connectors needing review' },
                                ].map((item) => (
                                    <div className="mf-panel p-4" key={item.label}>
                                        <p className="text-xs uppercase tracking-wide text-fg-subtle">{item.label}</p>
                                        <p className="mt-1 text-lg font-semibold">{item.value}</p>
                                        <p className="mt-0.5 text-xs text-fg-muted">{item.hint}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-4 grid gap-2 sm:grid-cols-2">
                                {['jellyfin', 'audiobookshelf'].map((key) => {
                                    const c = find(key);
                                    const label = key === 'jellyfin' ? 'Jellyfin' : 'Audiobookshelf';
                                    return (
                                        <div className="flex items-center justify-between gap-3 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5 text-sm" key={key}>
                                            <span className="text-fg-muted">{label}</span>
                                            <CatalogStatusBadge status={c?.catalog.status ?? 'not_ready'} />
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    {/* Import Plans (V2 D) — dry run only, nothing is imported */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 1.4 } as CSSProperties}>
                        <div className="mf-panel p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-11 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                        <ImportIcon className="size-6" />
                                    </span>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="text-lg font-semibold tracking-tight">Import Plans</h2>
                                            <ImportPlanStatusBadge status={importSummary.status} />
                                        </div>
                                        <p className="text-xs text-fg-subtle">
                                            Dry run only. No media is imported and no files are copied, moved, deleted or renamed.
                                        </p>
                                    </div>
                                </div>
                                <Link className={buttonClasses('secondary', 'sm')} href="/imports">Open import plans</Link>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                {[
                                    { label: 'Planned items', value: String(importSummary.planned_items), hint: 'Latest dry run' },
                                    { label: 'Ready', value: String(importSummary.ready_items), hint: 'Unambiguous' },
                                    { label: 'Warnings / review', value: String(importSummary.warning_items), hint: 'Need a decision' },
                                    { label: 'Blocked', value: String(importSummary.blocked_items), hint: 'Cannot be planned' },
                                ].map((item) => (
                                    <div className="mf-panel p-4" key={item.label}>
                                        <p className="text-xs uppercase tracking-wide text-fg-subtle">{item.label}</p>
                                        <p className="mt-1 text-lg font-semibold">{item.value}</p>
                                        <p className="mt-0.5 text-xs text-fg-muted">{item.hint}</p>
                                    </div>
                                ))}
                            </div>

                            <p className="mt-4 text-xs text-fg-subtle">
                                {importSummary.plan_count === 0
                                    ? 'No import dry run has been created yet.'
                                    : `Last dry run ${formatCheckedAt(importSummary.created_at)} · ${importSummary.plan_count} stored ${importSummary.plan_count === 1 ? 'plan' : 'plans'}`}
                            </p>
                        </div>
                    </section>

                    {/* Internal Media (V2 E) — database records, no files touched */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 1.45 } as CSSProperties}>
                        <div className="mf-panel p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-11 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                        <LibraryIcon className="size-6" />
                                    </span>
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-lg font-semibold tracking-tight">Internal Media</h2>
                                            {internalMedia.latest_execution && (
                                                <ImportExecutionStatusBadge status={internalMedia.latest_execution.status} />
                                            )}
                                        </div>
                                        <p className="text-xs text-fg-subtle">
                                            Internal import only. No files are copied, moved, deleted or renamed.
                                        </p>
                                    </div>
                                </div>
                                <Link className={buttonClasses('secondary', 'sm')} href="/imports">Open import plans</Link>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                {[
                                    { label: 'Media items', value: String(internalMedia.media_items), hint: 'In the internal catalog' },
                                    { label: 'Last internal import', value: formatCheckedAt(internalMedia.latest_execution?.created_at ?? null), hint: `${internalMedia.execution_count} ${internalMedia.execution_count === 1 ? 'run' : 'runs'}` },
                                    { label: 'Plans needing review', value: String(internalMedia.plans_needing_review), hint: 'Before more can be imported' },
                                    { label: 'Imported records', value: String(internalMedia.imported_items), hint: 'Created by a connector import' },
                                ].map((item) => (
                                    <div className="mf-panel p-4" key={item.label}>
                                        <p className="text-xs uppercase tracking-wide text-fg-subtle">{item.label}</p>
                                        <p className="mt-1 text-lg font-semibold">{item.value}</p>
                                        <p className="mt-0.5 text-xs text-fg-muted">{item.hint}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-4 grid gap-2 sm:grid-cols-4">
                                {[
                                    ['Movies', internalMedia.movies],
                                    ['Series', internalMedia.series],
                                    ['Episodes', internalMedia.episodes],
                                    ['Books', internalMedia.books],
                                ].map(([label, value]) => (
                                    <div className="flex items-center justify-between gap-3 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5 text-sm" key={label}>
                                        <span className="text-fg-muted">{label}</span>
                                        <span className="font-semibold">{value}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* Sync Foundation (V1 F) */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 1.5 } as CSSProperties}>
                        <div className="mf-panel p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-11 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                        <SyncIcon className="size-6" />
                                    </span>
                                    <div>
                                        <h2 className="text-lg font-semibold tracking-tight">Sync Foundation</h2>
                                        <p className="text-xs text-fg-subtle">Dry run only. No media import in V1 F.</p>
                                    </div>
                                </div>
                                <Link className={buttonClasses('secondary', 'sm')} href="/sync">Open sync</Link>
                            </div>

                            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                {[
                                    { label: 'Selected libraries', value: String(syncSummary.selected_libraries), hint: 'Marked for later sync' },
                                    { label: 'Last dry run', value: formatCheckedAt(syncSummary.last_dry_run_at), hint: 'Most recent, any connector' },
                                    { label: 'Attention required', value: String(syncSummary.attention_count), hint: 'Connectors needing review' },
                                    { label: 'Ready connectors', value: String(syncSummary.ready_count), hint: 'Last dry run completed' },
                                ].map((item) => (
                                    <div className="mf-panel p-4" key={item.label}>
                                        <p className="text-xs uppercase tracking-wide text-fg-subtle">{item.label}</p>
                                        <p className="mt-1 text-lg font-semibold">{item.value}</p>
                                        <p className="mt-0.5 text-xs text-fg-muted">{item.hint}</p>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-4 grid gap-2 sm:grid-cols-2">
                                {['jellyfin', 'audiobookshelf'].map((key) => {
                                    const c = find(key);
                                    const label = key === 'jellyfin' ? 'Jellyfin' : 'Audiobookshelf';
                                    return (
                                        <div className="flex items-center justify-between gap-3 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5 text-sm" key={key}>
                                            <span className="text-fg-muted">{label}</span>
                                            <SyncStatusBadge status={c?.sync.status ?? 'not_ready'} />
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>

                    {/* Engine panels — three equal columns */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 2 } as CSSProperties}>
                        <h2 className="mb-4 text-xl font-semibold tracking-tight">Engine panels</h2>
                        <div className="mf-grid">
                            {['jellyfin', 'audiobookshelf'].map((key) => {
                                const c = find(key);
                                const label = key === 'jellyfin' ? 'Jellyfin' : 'Audiobookshelf';
                                return (
                                    <div className="mf-col-4" key={key}>
                                        <div className="mf-engine-card flex h-full flex-col gap-4 p-6">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex items-center gap-3">
                                                    <span className="grid size-11 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                                        <ServerIcon className="size-6" />
                                                    </span>
                                                    <div>
                                                        <h3 className="font-semibold">{label}</h3>
                                                        <p className="text-xs text-fg-subtle">Engine connector</p>
                                                    </div>
                                                </div>
                                                <StatusBadge status={c?.status ?? 'not_configured'} />
                                            </div>
                                            <p className="text-sm text-fg-muted">
                                                {c?.health_detail ?? (c?.configured ? 'Configured — not checked yet.' : 'Not configured yet.')}
                                            </p>
                                            <dl className="grid grid-cols-2 gap-2 border-y border-[var(--panel-border)] py-3 text-sm">
                                                <div><dt className="text-xs text-fg-subtle">Libraries</dt><dd className="font-semibold">{c?.library_count ?? 0}</dd></div>
                                                <div><dt className="text-xs text-fg-subtle">Last checked</dt><dd className="font-medium">{formatCheckedAt(c?.last_checked_at ?? null)}</dd></div>
                                            </dl>
                                            <div className="mt-auto flex flex-wrap gap-2">
                                                <Link className={buttonClasses('secondary', 'sm')} href={`/connectors/${key}`}>Configure</Link>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                            <div className="mf-col-4">
                                <div className="mf-panel flex h-full flex-col p-6">
                                    <h3 className="mb-3 font-semibold">Next actions</h3>
                                    <div className="flex flex-col divide-y divide-[var(--panel-border)]">
                                        {NEXT_ACTIONS.map((action) => (
                                            <div className="flex items-center justify-between gap-3 py-3 text-sm first:pt-0" key={action.label}>
                                                <span>{action.label}</span>
                                                <Badge tone={action.tone}>{action.badge}</Badge>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Roadmap strip */}
                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 4 } as CSSProperties}>
                        <h2 className="mb-4 text-lg font-semibold tracking-tight">Roadmap</h2>
                        <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            {ROADMAP.map((step) => (
                                <div className="mf-panel p-4" key={step.id}>
                                    <div className="flex items-center justify-between">
                                        <span className="font-mono text-xs text-fg-subtle">{step.id}</span>
                                        <span className={`size-2 rounded-full ${step.done ? 'bg-success' : 'bg-fg-subtle'}`} />
                                    </div>
                                    <p className="mt-2 text-sm font-medium">{step.label}</p>
                                    <p className="mt-0.5 text-xs text-fg-muted">{step.done ? 'Done' : 'Later'}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
