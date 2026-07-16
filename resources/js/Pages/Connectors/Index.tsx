import { Head, Link, router, usePage } from '@inertiajs/react';
import { type CSSProperties, useState } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    CatalogStatusBadge,
    type ConnectorSummary,
    discoverySummary,
    formatCheckedAt,
    runStatusLabel,
    snapshotStatusLabel,
    StatusBadge,
    SyncStatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button, { buttonClasses } from '@/Components/UI/Button';
import { ConnectorsIcon, ServerIcon } from '@/Components/UI/Icon';

interface ConnectorsPageProps {
    [key: string]: unknown;
    connectors: ConnectorSummary[];
    flash: { success: string | null; error: string | null };
}

const SUBTITLES: Record<string, string> = {
    jellyfin: 'Video engine connector',
    audiobookshelf: 'Audio and books engine connector',
};

export default function ConnectorsIndex() {
    const { connectors, flash } = usePage<ConnectorsPageProps>().props;
    const [busy, setBusy] = useState<string | null>(null);

    function post(url: string, tag: string) {
        setBusy(tag);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    }

    return (
        <>
            <Head title="Connectors" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <span className="mf-status-pill mb-3">V1 foundation</span>
                            <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Connectors</h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">
                                External connector mode for current V1. Future engine mode later.
                            </p>
                        </div>
                        <span className="mf-status-pill">{connectors.length} providers available</span>
                    </header>

                    {flash.success && <div className="mf-col-12"><Alert tone="success">{flash.success}</Alert></div>}
                    {flash.error && <div className="mf-col-12"><Alert tone="error">{flash.error}</Alert></div>}

                    {connectors.map((connector, i) => (
                        <section className="mf-col-6 mf-rise" key={connector.key} style={{ '--mf-i': i + 1 } as CSSProperties}>
                            <div className="mf-engine-card flex h-full flex-col gap-5 p-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-12 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                            <ServerIcon className="size-6" />
                                        </span>
                                        <div>
                                            <h2 className="text-lg font-semibold">{connector.label}</h2>
                                            <p className="text-sm text-fg-muted">{SUBTITLES[connector.key] ?? 'Engine connector'}</p>
                                        </div>
                                    </div>
                                    <StatusBadge status={connector.status} />
                                </div>

                                <div className="grid grid-cols-3 gap-3">
                                    {[
                                        ['Credentials', connector.secret_configured ? 'Configured' : 'Not set'],
                                        ['Health', connector.status === 'healthy' ? 'Healthy' : connector.status === 'unhealthy' ? 'Unhealthy' : 'Not checked'],
                                        ['Libraries', String(connector.library_count)],
                                    ].map(([k, v]) => (
                                        <div className="mf-panel px-3 py-2.5 text-center" key={k}>
                                            <p className="text-[0.7rem] uppercase tracking-wide text-fg-subtle">{k}</p>
                                            <p className="mt-1 text-sm font-semibold">{v}</p>
                                        </div>
                                    ))}
                                </div>

                                <p className="text-xs text-fg-subtle">
                                    {discoverySummary(connector)} · Last checked {formatCheckedAt(connector.last_checked_at)}
                                </p>

                                <div className="flex flex-wrap items-center justify-between gap-2 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5">
                                    <span className="flex items-center gap-2 text-sm">
                                        <span className="text-fg-muted">Sync foundation</span>
                                        <SyncStatusBadge status={connector.sync.status} />
                                    </span>
                                    <span className="text-xs text-fg-subtle">
                                        {connector.sync.selected_count} selected · {connector.sync.last_run ? runStatusLabel(connector.sync.last_run.status) : 'No dry run yet'}
                                    </span>
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-2 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5">
                                    <span className="flex items-center gap-2 text-sm">
                                        <span className="text-fg-muted">External catalog</span>
                                        <CatalogStatusBadge status={connector.catalog.status} />
                                    </span>
                                    <span className="text-xs text-fg-subtle">
                                        {connector.catalog.present_item_count} {connector.catalog.present_item_count === 1 ? 'item' : 'items'} · {connector.catalog.last_run ? snapshotStatusLabel(connector.catalog.last_run.status) : 'No snapshot yet'}
                                    </span>
                                </div>

                                <div className="mt-auto flex flex-wrap items-center gap-2">
                                    <Link className={buttonClasses('primary', 'sm')} href={`/connectors/${connector.key}`}>Configure</Link>
                                    <Link className={buttonClasses('secondary', 'sm')} href="/catalog">View catalog</Link>
                                    {connector.configured && (
                                        <>
                                            <Button loading={busy === `${connector.key}-test`} onClick={() => post(`/connectors/${connector.key}/test`, `${connector.key}-test`)} size="sm" variant="secondary">
                                                Test connection
                                            </Button>
                                            <Button loading={busy === `${connector.key}-disc`} onClick={() => post(`/connectors/${connector.key}/libraries/discover`, `${connector.key}-disc`)} size="sm" variant="secondary">
                                                Discover libraries
                                            </Button>
                                            <Button loading={busy === `${connector.key}-dry`} onClick={() => post(`/connectors/${connector.key}/sync/dry-run`, `${connector.key}-dry`)} size="sm" variant="secondary">
                                                Run dry run
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </section>
                    ))}

                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 3 } as CSSProperties}>
                        <div className="mf-panel flex items-start gap-4 p-5">
                            <span className="grid size-10 shrink-0 place-items-center rounded-[--radius-md] bg-accent-2/10 text-accent-2 ring-1 ring-inset ring-accent-2/20">
                                <ConnectorsIcon className="size-5" />
                            </span>
                            <div>
                                <div className="flex items-center gap-2">
                                    <p className="font-semibold">External Connector Mode</p>
                                    <Badge tone="neutral">Future engine</Badge>
                                </div>
                                <p className="mt-1 text-sm text-fg-muted">
                                    V1 uses External Connector Mode. Later MediaForge can evolve Jellyfin and Audiobookshelf into
                                    Integrated Engine Mode.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
