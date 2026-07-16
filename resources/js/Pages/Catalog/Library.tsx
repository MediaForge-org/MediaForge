import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import CatalogFilterBar from '@/Components/Catalog/CatalogFilterBar';
import CatalogItemsTable from '@/Components/Catalog/CatalogItemsTable';
import { filtersToQuery } from '@/Components/Catalog/catalogQuery';
import {
    type CatalogFilters,
    type CatalogItemsPage,
    type CatalogLibraryScope,
    type ConnectorStatus,
    type ExternalMediaKind,
    formatCheckedAt,
    snapshotStatusLabel,
    StatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { LibraryIcon, ShieldIcon } from '@/Components/UI/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface CatalogConnector {
    key: string;
    label: string;
    configured: boolean;
    status: ConnectorStatus;
}

interface CatalogLibrary {
    id: string;
    name: string;
    type: string | null;
    external_id: string;
    is_enabled: boolean;
    discovery_status: 'present' | 'missing';
    last_seen_at: string | null;
}

interface CatalogLibraryProps {
    [key: string]: unknown;
    connector: CatalogConnector;
    library: CatalogLibrary;
    scope: CatalogLibraryScope;
    items: CatalogItemsPage;
    kinds: ExternalMediaKind[];
    filters: CatalogFilters;
    flash: { success: string | null; error: string | null };
}

export default function CatalogLibraryPage() {
    const { connector, library, scope, items, kinds, filters, flash } = usePage<CatalogLibraryProps>().props;
    const [snapshotting, setSnapshotting] = useState(false);
    const basePath = `/catalog/${connector.key}/libraries/${library.id}`;
    const lastRun = scope.last_run;
    const canSnapshot = connector.configured && library.discovery_status !== 'missing';

    function snapshot() {
        setSnapshotting(true);
        router.post(
            `/connectors/${connector.key}/libraries/${library.id}/snapshot`,
            {},
            { preserveScroll: true, onFinish: () => setSnapshotting(false) },
        );
    }

    return (
        <>
            <Head title={`${library.name} — ${connector.label} catalog`} />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Link className="text-fg-muted transition-colors hover:text-fg" href="/catalog">
                                    External Catalog
                                </Link>
                                <span className="text-fg-subtle">/</span>
                                <Link className="text-fg-muted transition-colors hover:text-fg" href={`/catalog/${connector.key}`}>
                                    {connector.label}
                                </Link>
                                <span className="text-fg-subtle">/</span>
                                <span className="text-fg-muted">{library.name}</span>
                            </div>
                            <h1 className="mt-2 flex flex-wrap items-center gap-2 text-3xl font-semibold tracking-tight sm:text-4xl">
                                {library.name}
                                {library.type && <Badge tone="neutral">{library.type}</Badge>}
                            </h1>
                            <p className="mt-2 flex flex-wrap items-center gap-2 text-sm text-fg-muted">
                                <StatusBadge status={connector.status} />
                                <Badge tone={library.is_enabled ? 'accent' : 'neutral'}>
                                    {library.is_enabled ? 'Selected for sync' : 'Not selected'}
                                </Badge>
                                {library.discovery_status === 'missing' && <Badge tone="error">Missing from discovery</Badge>}
                            </p>
                        </div>
                        <Button disabled={!canSnapshot} loading={snapshotting} onClick={snapshot} variant="secondary">
                            Create read-only snapshot
                        </Button>
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

                    {/* Summary */}
                    <section className="mf-col-12">
                        <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                ['External items', scope.present_item_count],
                                ['Missing', scope.missing_item_count],
                                ['Snapshot runs', scope.snapshot_run_count],
                                ['Total captured', scope.external_item_count],
                            ].map(([label, value]) => (
                                <div className="mf-card px-4 py-3 text-center" key={label}>
                                    <dt className="text-xs uppercase tracking-wide text-fg-subtle">{label}</dt>
                                    <dd className="mt-1 text-xl font-semibold">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    {lastRun?.summary.truncated && (
                        <section className="mf-col-12">
                            <Alert tone="warning">
                                The last snapshot captured a bounded subset ({lastRun.summary.captured_count} of{' '}
                                {lastRun.summary.remote_total} remote items, cap {lastRun.summary.cap}). Missing items are not
                                flagged after a truncated read.
                            </Alert>
                        </section>
                    )}

                    {/* Main column */}
                    <section className="mf-col-8 flex flex-col gap-6">
                        <div>
                            <h2 className="mb-3 text-lg font-semibold tracking-tight">Browse items</h2>
                            <CatalogFilterBar basePath={basePath} filters={filters} kinds={kinds} />
                            <div className="mt-4">
                                <CatalogItemsTable
                                    basePath={basePath}
                                    emptyDescription="Adjust the filters, or create a read-only snapshot to capture this library's external items. Nothing is imported, moved or deleted."
                                    page={items}
                                    query={filtersToQuery(filters, false)}
                                    showConnector={false}
                                    showLibrary={false}
                                />
                            </div>
                        </div>
                    </section>

                    {/* Side column */}
                    <section className="mf-col-4">
                        <div className="grid gap-4">
                            <div className="mf-panel p-5">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-fg-subtle">Last snapshot</h2>
                                {!lastRun ? (
                                    <p className="text-sm text-fg-muted">
                                        No snapshot yet. Use “Create read-only snapshot” above to capture external items.
                                    </p>
                                ) : (
                                    <div className="grid gap-1.5 text-sm">
                                        <p className="font-medium">{snapshotStatusLabel(lastRun.status)}</p>
                                        <p className="text-xs text-fg-subtle">
                                            Finished {formatCheckedAt(lastRun.finished_at)}
                                        </p>
                                        <p className="text-xs text-fg-subtle">
                                            {lastRun.items_stored_count} stored of {lastRun.items_seen_count} seen
                                        </p>
                                        {lastRun.summary.issues.length > 0 && (
                                            <ul className="mt-2 grid gap-1.5">
                                                {lastRun.summary.issues.map((issue) => (
                                                    <li className="flex items-start gap-2" key={issue.code}>
                                                        <Badge tone={issue.blocking ? 'error' : 'neutral'}>{issue.action}</Badge>
                                                        <span className="text-fg-muted">{issue.message}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
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

                            <Link className="mf-panel flex items-center gap-3 p-5 transition-colors hover:text-accent" href={`/connectors/${connector.key}`}>
                                <LibraryIcon className="size-5 text-fg-subtle" />
                                <span className="text-sm">Open the {connector.label} connector</span>
                            </Link>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
