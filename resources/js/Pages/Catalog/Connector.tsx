import { Head, Link, usePage } from '@inertiajs/react';

import CatalogFilterBar from '@/Components/Catalog/CatalogFilterBar';
import CatalogItemsTable from '@/Components/Catalog/CatalogItemsTable';
import { filtersToQuery } from '@/Components/Catalog/catalogQuery';
import {
    type CatalogFilters,
    type CatalogFoundation,
    type CatalogItemsPage,
    type CatalogLibraryOption,
    CatalogStatusBadge,
    type ConnectorStatus,
    type ExternalMediaKind,
    formatCheckedAt,
    type LatestSnapshotRun,
    snapshotStatusLabel,
    StatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import Badge from '@/Components/UI/Badge';
import { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { LibraryIcon, ShieldIcon } from '@/Components/UI/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface CatalogConnector {
    key: string;
    label: string;
    configured: boolean;
    status: ConnectorStatus;
    catalog: CatalogFoundation;
}

interface CatalogConnectorProps {
    [key: string]: unknown;
    connector: CatalogConnector;
    libraries: CatalogLibraryOption[];
    latestRuns: LatestSnapshotRun[];
    items: CatalogItemsPage;
    kinds: ExternalMediaKind[];
    filters: CatalogFilters;
}

const RUN_TONE: Record<LatestSnapshotRun['status'], 'success' | 'error' | 'neutral'> = {
    pending: 'neutral',
    running: 'neutral',
    completed: 'success',
    completed_with_warnings: 'error',
    failed: 'error',
    cancelled: 'neutral',
};

export default function CatalogConnectorPage() {
    const { connector, libraries, latestRuns, items, kinds, filters } = usePage<CatalogConnectorProps>().props;
    const { catalog } = connector;
    const basePath = `/catalog/${connector.key}`;
    const libraryOptions = libraries.map((library) => ({ id: library.id, name: library.name }));

    return (
        <>
            <Head title={`${connector.label} catalog`} />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 text-sm">
                                <Link className="text-fg-muted transition-colors hover:text-fg" href="/catalog">
                                    External Catalog
                                </Link>
                                <span className="text-fg-subtle">/</span>
                                <span className="text-fg-muted">{connector.label}</span>
                            </div>
                            <h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{connector.label} catalog</h1>
                            <p className="mt-2 text-fg-muted">
                                Read-only external items captured for {connector.label}. No media import, no file operations.
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <StatusBadge status={connector.status} />
                            <CatalogStatusBadge status={catalog.status} />
                        </div>
                    </header>

                    {/* Summary */}
                    <section className="mf-col-12">
                        <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                ['External items', catalog.present_item_count],
                                ['Missing', catalog.missing_item_count],
                                ['Snapshot runs', catalog.snapshot_run_count],
                                ['Open reviews', catalog.open_review_count],
                            ].map(([label, value]) => (
                                <div className="mf-card px-4 py-3 text-center" key={label}>
                                    <dt className="text-xs uppercase tracking-wide text-fg-subtle">{label}</dt>
                                    <dd className="mt-1 text-xl font-semibold">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    {/* Main column */}
                    <section className="mf-col-8 flex flex-col gap-6">
                        <div>
                            <h2 className="mb-3 text-lg font-semibold tracking-tight">Browse items</h2>
                            <CatalogFilterBar basePath={basePath} filters={filters} kinds={kinds} libraryOptions={libraryOptions} />
                            <div className="mt-4">
                                <CatalogItemsTable
                                    basePath={basePath}
                                    page={items}
                                    query={filtersToQuery(filters, false)}
                                    showConnector={false}
                                />
                            </div>
                        </div>

                        <div>
                            <h2 className="mb-4 text-lg font-semibold tracking-tight">Latest snapshot runs</h2>
                            {latestRuns.length === 0 ? (
                                <EmptyState
                                    description="Take a read-only snapshot of a library to capture external items. Nothing is imported, moved or deleted."
                                    icon={<LibraryIcon className="size-5" />}
                                    title="No snapshots yet"
                                />
                            ) : (
                                <div className="mf-panel divide-y divide-[var(--panel-border)]">
                                    {latestRuns.map((run) => (
                                        <div className="flex flex-wrap items-center justify-between gap-3 p-4" key={run.id}>
                                            <div className="min-w-0">
                                                <p className="flex flex-wrap items-center gap-2 font-medium">
                                                    {run.library_name ?? 'Library'}
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
                        </div>
                    </section>

                    {/* Side column: libraries + snapshot CTA */}
                    <section className="mf-col-4">
                        <div className="grid gap-4">
                            <div className="mf-panel p-5">
                                <div className="mb-3 flex items-center justify-between gap-2">
                                    <h2 className="text-sm font-semibold uppercase tracking-wide text-fg-subtle">Libraries</h2>
                                    <Link className={buttonClasses('ghost', 'sm')} href={`/connectors/${connector.key}`}>
                                        Snapshot
                                    </Link>
                                </div>
                                {libraries.length === 0 ? (
                                    <p className="text-sm text-fg-muted">
                                        No libraries discovered yet. Discover libraries on the connector page.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-[var(--panel-border)]">
                                        {libraries.map((library) => (
                                            <li key={library.id}>
                                                <Link
                                                    className="flex items-center justify-between gap-3 py-3 transition-colors hover:text-accent first:pt-0"
                                                    href={`/catalog/${connector.key}/libraries/${library.id}`}
                                                >
                                                    <span className="min-w-0">
                                                        <span className="flex items-center gap-2 truncate font-medium">
                                                            {library.name}
                                                            {library.type && <Badge tone="neutral">{library.type}</Badge>}
                                                        </span>
                                                        <span className="text-xs text-fg-subtle">
                                                            {library.present_item_count} {library.present_item_count === 1 ? 'item' : 'items'}
                                                            {library.missing_item_count > 0 && ` · ${library.missing_item_count} missing`}
                                                        </span>
                                                    </span>
                                                    <span aria-hidden className="text-fg-subtle">
                                                        →
                                                    </span>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>

                            <div className="mf-panel flex items-start gap-3 p-5">
                                <span className="grid size-9 shrink-0 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                    <ShieldIcon className="size-4" />
                                </span>
                                <p className="text-xs text-fg-muted">
                                    Read-only catalog. No media import. No files are copied, moved, deleted or renamed.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
