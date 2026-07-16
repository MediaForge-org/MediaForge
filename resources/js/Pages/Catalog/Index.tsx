import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    CatalogStatusBadge,
    type ConnectorSummary,
    type ExternalMediaKind,
    formatCheckedAt,
    formatRuntime,
    mediaKindLabel,
    type SnapshotRunStatus,
    snapshotStatusLabel,
} from '@/Components/Connectors/ConnectorStatus';
import Badge from '@/Components/UI/Badge';
import { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { CatalogIcon, LibraryIcon, ServerIcon, ShieldIcon } from '@/Components/UI/Icon';

interface ConnectorRef {
    key: string;
    label: string;
}

interface LatestRun {
    id: string;
    status: SnapshotRunStatus;
    connector: ConnectorRef | null;
    library_name: string | null;
    items_stored_count: number;
    items_seen_count: number;
    warnings_count: number;
    errors_count: number;
    finished_at: string | null;
}

interface LatestItem {
    id: string;
    title: string;
    media_kind: ExternalMediaKind;
    year: number | null;
    index_number: number | null;
    runtime_seconds: number | null;
    connector: ConnectorRef | null;
    library_name: string | null;
    is_present: boolean;
    last_seen_at: string | null;
}

interface CatalogSummary {
    external_items: number;
    snapshot_runs: number;
    libraries_captured: number;
    attention_count: number;
}

interface CatalogPageProps {
    [key: string]: unknown;
    connectors: ConnectorSummary[];
    summary: CatalogSummary;
    latestRuns: LatestRun[];
    latestItems: LatestItem[];
}

const RUN_TONE: Record<SnapshotRunStatus, 'success' | 'error' | 'neutral'> = {
    pending: 'neutral',
    running: 'neutral',
    completed: 'success',
    completed_with_warnings: 'error',
    failed: 'error',
    cancelled: 'neutral',
};

export default function CatalogIndex() {
    const { connectors, summary, latestRuns, latestItems } = usePage<CatalogPageProps>().props;

    const hasSnapshots = summary.snapshot_runs > 0;

    const nextActions: { label: string; href: string }[] = [];
    for (const connector of connectors) {
        if (!connector.configured) {
            nextActions.push({ label: `Configure ${connector.label}`, href: `/connectors/${connector.key}` });
        } else if (connector.catalog.status === 'attention_required') {
            nextActions.push({ label: `Review ${connector.label} snapshot issues`, href: '/review' });
        } else if (connector.catalog.snapshot_run_count === 0) {
            nextActions.push({ label: `Take a first snapshot of ${connector.label}`, href: `/connectors/${connector.key}` });
        }
    }

    return (
        <>
            <Head title="External Catalog" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <span className="mf-status-pill mb-3">V2 A</span>
                            <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">External Catalog</h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">
                                Read-only connector snapshots. No media import or file operations.
                            </p>
                        </div>
                        <span className="mf-status-pill">
                            {summary.external_items} external {summary.external_items === 1 ? 'item' : 'items'}
                        </span>
                    </header>

                    {/* Summary cards */}
                    <section className="mf-col-12">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                { label: 'External items', value: String(summary.external_items), hint: 'Captured, currently present' },
                                { label: 'Snapshot runs', value: String(summary.snapshot_runs), hint: 'Explicitly triggered' },
                                { label: 'Libraries captured', value: String(summary.libraries_captured), hint: 'With at least one item' },
                                { label: 'Attention required', value: String(summary.attention_count), hint: 'Connectors needing review' },
                            ].map((card) => (
                                <div className="mf-card p-5" key={card.label}>
                                    <p className="text-sm font-medium text-fg-muted">{card.label}</p>
                                    <p className="mt-3 text-2xl font-semibold tracking-tight">{card.value}</p>
                                    <p className="mt-1.5 text-sm text-fg-subtle">{card.hint}</p>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Main column */}
                    <section className="mf-col-8">
                        <h2 className="mb-4 text-lg font-semibold tracking-tight">Connectors</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {connectors.map((connector, i) => (
                                <div className="mf-engine-card flex h-full flex-col gap-4 p-5 mf-rise" key={connector.key} style={{ '--mf-i': i + 1 } as CSSProperties}>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <span className="grid size-10 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                                <ServerIcon className="size-5" />
                                            </span>
                                            <div>
                                                <h3 className="font-semibold">{connector.label}</h3>
                                                <CatalogStatusBadge status={connector.catalog.status} />
                                            </div>
                                        </div>
                                    </div>

                                    <dl className="grid grid-cols-3 gap-2">
                                        {[
                                            ['Items', connector.catalog.present_item_count],
                                            ['Missing', connector.catalog.missing_item_count],
                                            ['Runs', connector.catalog.snapshot_run_count],
                                        ].map(([label, value]) => (
                                            <div className="mf-panel px-3 py-2 text-center" key={label}>
                                                <dt className="text-[0.7rem] uppercase tracking-wide text-fg-subtle">{label}</dt>
                                                <dd className="mt-1 text-sm font-semibold">{value}</dd>
                                            </div>
                                        ))}
                                    </dl>

                                    <p className="text-xs text-fg-subtle">
                                        {connector.catalog.last_run
                                            ? `${snapshotStatusLabel(connector.catalog.last_run.status)} · ${formatCheckedAt(connector.catalog.last_run.finished_at)}`
                                            : 'No snapshot yet'}
                                    </p>

                                    <div className="mt-auto flex flex-wrap gap-2">
                                        <Link className={buttonClasses('secondary', 'sm')} href={`/connectors/${connector.key}`}>
                                            {connector.configured ? 'Create snapshot' : 'Configure'}
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <h2 className="mb-4 mt-8 text-lg font-semibold tracking-tight">Latest snapshot runs</h2>
                        {latestRuns.length === 0 ? (
                            <EmptyState
                                description="Take a read-only snapshot from a connector page to capture external items. Nothing is imported, moved or deleted."
                                icon={<CatalogIcon className="size-5" />}
                                title="No snapshots yet"
                            />
                        ) : (
                            <div className="mf-panel divide-y divide-[var(--panel-border)]">
                                {latestRuns.map((run) => (
                                    <div className="flex flex-wrap items-center justify-between gap-3 p-4" key={run.id}>
                                        <div className="min-w-0">
                                            <p className="flex flex-wrap items-center gap-2 font-medium">
                                                {run.connector?.label ?? 'Connector'}
                                                {run.library_name && <span className="text-fg-muted">· {run.library_name}</span>}
                                                <Badge tone={RUN_TONE[run.status]}>{snapshotStatusLabel(run.status)}</Badge>
                                            </p>
                                            <p className="mt-1 text-xs text-fg-subtle">
                                                {run.items_stored_count} stored of {run.items_seen_count} seen
                                                {run.warnings_count > 0 && ` · ${run.warnings_count} warning${run.warnings_count === 1 ? '' : 's'}`}
                                                {run.errors_count > 0 && ` · ${run.errors_count} error${run.errors_count === 1 ? '' : 's'}`}
                                            </p>
                                        </div>
                                        <span className="text-xs text-fg-subtle">{formatCheckedAt(run.finished_at)}</span>
                                    </div>
                                ))}
                            </div>
                        )}

                        <h2 className="mb-4 mt-8 text-lg font-semibold tracking-tight">Latest external items</h2>
                        {latestItems.length === 0 ? (
                            <EmptyState
                                description="Captured external items appear here once you take a read-only snapshot."
                                icon={<LibraryIcon className="size-5" />}
                                title="No external items captured"
                            />
                        ) : (
                            <div className="mf-panel overflow-x-auto">
                                <table className="w-full min-w-[40rem] text-left text-sm">
                                    <thead className="border-b border-[var(--panel-border)] text-xs uppercase tracking-wide text-fg-subtle">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Title</th>
                                            <th className="px-4 py-3 font-medium">Kind</th>
                                            <th className="px-4 py-3 font-medium">Connector</th>
                                            <th className="px-4 py-3 font-medium">Library</th>
                                            <th className="px-4 py-3 font-medium">Last seen</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--panel-border)]">
                                        {latestItems.map((item) => {
                                            const runtime = formatRuntime(item.runtime_seconds);

                                            return (
                                                <tr key={item.id}>
                                                    <td className="px-4 py-3">
                                                        <span className="block font-medium">{item.title}</span>
                                                        <span className="text-xs text-fg-subtle">
                                                            {[item.year, runtime].filter(Boolean).join(' · ') || '—'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge tone="neutral">{mediaKindLabel(item.media_kind)}</Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-fg-muted">{item.connector?.label ?? '—'}</td>
                                                    <td className="px-4 py-3 text-fg-muted">{item.library_name ?? '—'}</td>
                                                    <td className="px-4 py-3 text-xs text-fg-subtle">{formatCheckedAt(item.last_seen_at)}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    {/* Side column */}
                    <section className="mf-col-4">
                        <div className="grid gap-4">
                            <div className="mf-panel flex items-start gap-3 p-5">
                                <span className="grid size-9 shrink-0 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                    <ShieldIcon className="size-4" />
                                </span>
                                <p className="text-xs text-fg-muted">
                                    Read-only snapshot. No media files are copied, moved, deleted or imported.
                                </p>
                            </div>

                            <div className="mf-panel p-5">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-fg-subtle">
                                    Next recommended steps
                                </h2>
                                {nextActions.length === 0 ? (
                                    <p className="text-sm text-fg-muted">
                                        {hasSnapshots
                                            ? 'Nothing pending — snapshots are up to date.'
                                            : 'Configure a connector to start capturing an external catalog.'}
                                    </p>
                                ) : (
                                    <div className="grid gap-1.5">
                                        {nextActions.slice(0, 4).map((action) => (
                                            <Link
                                                className="rounded-[--radius-md] px-3 py-2 text-sm text-fg-muted transition-colors hover:bg-[var(--nav-hover-bg)] hover:text-fg"
                                                href={action.href}
                                                key={action.label}
                                            >
                                                {action.label}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="mf-panel p-5">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-fg-subtle">Review</h2>
                                <p className="text-sm text-fg-muted">
                                    Snapshot warnings and failures raise review tasks.
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
