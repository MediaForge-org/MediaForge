import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type CSSProperties, type FormEvent, useState } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    type CatalogLibraryCapture,
    CatalogStatusBadge,
    type ConnectorDetail,
    type DiscoveredLibrary,
    formatCheckedAt,
    plannedActionLabel,
    runStatusLabel,
    snapshotStatusLabel,
    StatusBadge,
    SyncStatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button, { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { CatalogIcon, CheckIcon, LibraryIcon } from '@/Components/UI/Icon';
import TextField from '@/Components/UI/TextField';

interface ConnectorShowProps {
    [key: string]: unknown;
    connector: ConnectorDetail;
    flash: { success: string | null; error: string | null };
}

const SECRET_FACTS = [
    'Token encrypted at rest',
    'Token never rendered',
    'Token never audited',
    'Token sent only as auth header',
    'Short timeouts enabled',
];

export default function ConnectorShow() {
    const { connector, flash } = usePage<ConnectorShowProps>().props;
    const [testing, setTesting] = useState(false);
    const [discovering, setDiscovering] = useState(false);
    const [savingId, setSavingId] = useState<string | null>(null);
    const [dryRunning, setDryRunning] = useState(false);
    const [showRun, setShowRun] = useState(false);
    const [snapshottingId, setSnapshottingId] = useState<string | null>(null);

    const sync = connector.sync;
    const lastRun = sync.last_run;
    const catalog = connector.catalog;
    const lastSnapshot = catalog.last_run;

    /** Per-library capture counts, defaulted so an un-snapshotted library renders cleanly. */
    function catalogCapture(libraryId: string): CatalogLibraryCapture {
        return catalog.libraries[libraryId] ?? { external_item_count: 0, last_seen_at: null };
    }

    const form = useForm<{ base_url: string; secret: string; clear_secret: boolean }>({
        base_url: connector.base_url,
        secret: '',
        clear_secret: false,
    });

    const baseHint = connector.key === 'jellyfin' ? 'Example: http://jellyfin:8096' : 'Example: http://audiobookshelf:80';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/connectors/${connector.key}`, {
            preserveScroll: true,
            onSuccess: () => form.setData((data) => ({ ...data, secret: '', clear_secret: false })),
        });
    }

    function runTest() {
        setTesting(true);
        router.post(`/connectors/${connector.key}/test`, {}, { preserveScroll: true, onFinish: () => setTesting(false) });
    }

    function runDiscover() {
        setDiscovering(true);
        router.post(`/connectors/${connector.key}/libraries/discover`, {}, { preserveScroll: true, onFinish: () => setDiscovering(false) });
    }

    function runDryRun() {
        setDryRunning(true);
        router.post(`/connectors/${connector.key}/sync/dry-run`, {}, { preserveScroll: true, onFinish: () => setDryRunning(false) });
    }

    function toggleLibrary(library: DiscoveredLibrary) {
        setSavingId(library.id);
        router.post(
            `/connectors/${connector.key}/libraries/${library.id}/selection`,
            { enabled: !library.is_enabled },
            { preserveScroll: true, onFinish: () => setSavingId(null) },
        );
    }

    function snapshotLibrary(library: DiscoveredLibrary) {
        setSnapshottingId(library.id);
        router.post(
            `/connectors/${connector.key}/libraries/${library.id}/snapshot`,
            {},
            { preserveScroll: true, onFinish: () => setSnapshottingId(null) },
        );
    }

    return (
        <>
            <Head title={`${connector.label} connector`} />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 text-sm">
                                <Link className="text-fg-muted transition-colors hover:text-fg" href="/connectors">Connectors</Link>
                                <span className="text-fg-subtle">/</span>
                                <span className="text-fg-muted">{connector.label}</span>
                            </div>
                            <h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{connector.label} Connector</h1>
                            <p className="mt-2 text-fg-muted">Configure, test and discover libraries. No media sync in V1.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <StatusBadge status={connector.status} />
                            <Badge tone={connector.secret_configured ? 'success' : 'neutral'}>
                                {connector.secret_configured ? 'Configured' : 'Not configured'}
                            </Badge>
                        </div>
                    </header>

                    {flash.success && <div className="mf-col-12"><Alert tone="success">{flash.success}</Alert></div>}
                    {flash.error && <div className="mf-col-12"><Alert tone="error">{flash.error}</Alert></div>}

                    {/* Left column */}
                    <div className="mf-col-7 mf-rise flex flex-col gap-6" style={{ '--mf-i': 1 } as CSSProperties}>
                        <div className="mf-panel p-6">
                            <h2 className="text-lg font-semibold tracking-tight">Connection</h2>
                            <p className="mt-1 text-sm text-fg-muted">
                                Base URL and API token are used only for connection tests and library discovery.
                            </p>
                            <form className="mt-5 flex flex-col gap-5" onSubmit={submit}>
                                <TextField
                                    autoComplete="off"
                                    error={form.errors.base_url}
                                    hint={baseHint}
                                    label="Base URL"
                                    name="base_url"
                                    onChange={(event) => form.setData('base_url', event.target.value)}
                                    placeholder={baseHint.replace('Example: ', '')}
                                    required
                                    type="url"
                                    value={form.data.base_url}
                                />
                                <TextField
                                    autoComplete="new-password"
                                    disabled={form.data.clear_secret}
                                    error={form.errors.secret}
                                    hint="Stored encrypted. Existing token is never shown."
                                    label="API Token"
                                    name="secret"
                                    onChange={(event) => form.setData('secret', event.target.value)}
                                    placeholder={connector.secret_configured ? 'Leave blank to keep the stored token' : 'Enter the API token'}
                                    type="password"
                                    value={form.data.secret}
                                />
                                <div className="flex items-center justify-between gap-3 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5 text-sm">
                                    <span className="text-fg-muted">Secret status</span>
                                    <Badge dot tone={connector.secret_configured ? 'success' : 'neutral'}>
                                        {connector.secret_configured ? 'Secret configured' : 'No secret configured'}
                                    </Badge>
                                </div>
                                {connector.secret_configured && (
                                    <label className="flex w-fit items-center gap-2 text-sm text-fg-muted">
                                        <input checked={form.data.clear_secret} onChange={(event) => form.setData('clear_secret', event.target.checked)} type="checkbox" />
                                        <span>Clear stored secret on save</span>
                                    </label>
                                )}
                                <div className="flex flex-wrap items-center gap-2 border-t border-[var(--panel-border)] pt-5">
                                    <Button loading={form.processing} type="submit">Save configuration</Button>
                                </div>
                            </form>
                        </div>

                        <div className="mf-panel p-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-lg font-semibold tracking-tight">Discovered Libraries</h2>
                                    <p className="mt-1 text-sm text-fg-muted">Discovery only. No media sync in V1 D.</p>
                                </div>
                                <Button disabled={!connector.configured} loading={discovering} onClick={runDiscover} variant="secondary">
                                    Discover libraries
                                </Button>
                            </div>

                            {connector.last_discovery_error && (
                                <Alert className="mt-4" tone="error">Last discovery failed: {connector.last_discovery_error}</Alert>
                            )}

                            <div className="mt-5">
                                {!connector.configured ? (
                                    <EmptyState description="Configure and connect this connector to discover its libraries." icon={<LibraryIcon className="size-5" />} title="Not configured yet" />
                                ) : connector.libraries.length === 0 ? (
                                    <EmptyState
                                        action={<Button loading={discovering} onClick={runDiscover} size="sm" variant="secondary">Discover libraries</Button>}
                                        description="Run discovery to list the libraries this server exposes. Nothing is synced or imported."
                                        icon={<LibraryIcon className="size-5" />}
                                        title="No libraries discovered yet"
                                    />
                                ) : (
                                    <ul className="divide-y divide-[var(--panel-border)]">
                                        {connector.libraries.map((library) => (
                                            <li className="flex flex-col gap-3 py-4 first:pt-0 sm:flex-row sm:items-center sm:justify-between" key={library.id}>
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="truncate font-medium">{library.name}</p>
                                                        {library.type && <Badge tone="neutral">{library.type}</Badge>}
                                                        {library.discovery_status === 'missing' && <Badge tone="error">Missing</Badge>}
                                                    </div>
                                                    <p className="mt-1 truncate font-mono text-xs text-fg-subtle">{library.external_id}</p>
                                                    <p className="mt-0.5 text-xs text-fg-subtle">
                                                        Last seen {formatCheckedAt(library.last_seen_at)}
                                                        {' · '}
                                                        {catalogCapture(library.id).external_item_count} external{' '}
                                                        {catalogCapture(library.id).external_item_count === 1 ? 'item' : 'items'}
                                                        {' · '}
                                                        Snapshot {formatCheckedAt(catalogCapture(library.id).last_seen_at)}
                                                    </p>
                                                </div>
                                                <div className="flex shrink-0 flex-wrap items-center gap-3">
                                                    <label className="flex items-center gap-2 text-sm text-fg-muted">
                                                        <input checked={library.is_enabled} disabled={savingId === library.id} onChange={() => toggleLibrary(library)} type="checkbox" />
                                                        <span>Enable for later sync</span>
                                                    </label>
                                                    <Button
                                                        disabled={!connector.configured || library.discovery_status === 'missing'}
                                                        loading={snapshottingId === library.id}
                                                        onClick={() => snapshotLibrary(library)}
                                                        size="sm"
                                                        variant="secondary"
                                                    >
                                                        Create read-only snapshot
                                                    </Button>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>

                        {/* Sync Foundation (V1 F) */}
                        <div className="mf-panel p-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="text-lg font-semibold tracking-tight">Sync Foundation</h2>
                                        <SyncStatusBadge status={sync.status} />
                                    </div>
                                    <p className="mt-1 text-sm text-fg-muted">
                                        Dry run only. No media import in V1 F. No files are copied, moved or deleted.
                                    </p>
                                </div>
                                <Button disabled={!connector.configured} loading={dryRunning} onClick={runDryRun} variant="secondary">
                                    Run dry run
                                </Button>
                            </div>

                            <dl className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {[
                                    ['Discovered', sync.discovered_count],
                                    ['Selected', sync.selected_count],
                                    ['Ready', sync.selected_present_count],
                                    ['Missing', sync.selected_missing_count],
                                ].map(([label, value]) => (
                                    <div className="mf-panel px-3 py-2.5 text-center" key={label}>
                                        <dt className="text-[0.7rem] uppercase tracking-wide text-fg-subtle">{label}</dt>
                                        <dd className="mt-1 text-lg font-semibold">{value}</dd>
                                    </div>
                                ))}
                            </dl>

                            <div className="mt-5">
                                {!lastRun ? (
                                    <EmptyState
                                        action={<Button disabled={!connector.configured} loading={dryRunning} onClick={runDryRun} size="sm" variant="secondary">Run dry run</Button>}
                                        description="Run a dry run to inspect the selected libraries. Nothing is synced, imported, moved or deleted — it only prepares future sync safely."
                                        icon={<LibraryIcon className="size-5" />}
                                        title="No dry run yet"
                                    />
                                ) : (
                                    <div className="rounded-[--radius-md] border border-[var(--panel-border)] p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">{runStatusLabel(lastRun.status)}</p>
                                            <button
                                                className="text-xs text-accent underline-offset-2 hover:underline"
                                                onClick={() => setShowRun((v) => !v)}
                                                type="button"
                                            >
                                                {showRun ? 'Hide latest run' : 'View latest run'}
                                            </button>
                                        </div>
                                        <p className="mt-1 text-xs text-fg-subtle">
                                            Started {formatCheckedAt(lastRun.started_at)} · Finished {formatCheckedAt(lastRun.finished_at)}
                                        </p>

                                        {lastRun.summary.issues.length > 0 && (
                                            <ul className="mt-3 grid gap-1.5">
                                                {lastRun.summary.issues.map((issue) => (
                                                    <li className="flex items-start gap-2 text-sm" key={issue.code}>
                                                        <Badge tone={issue.blocking ? 'error' : 'neutral'}>{issue.action}</Badge>
                                                        <span className="text-fg-muted">{issue.message}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                        {showRun && (
                                            <ul className="mt-4 divide-y divide-[var(--panel-border)]">
                                                {(lastRun.libraries ?? []).map((library) => (
                                                    <li className="flex items-center justify-between gap-3 py-2.5 text-sm first:pt-0" key={library.external_id}>
                                                        <span className="min-w-0">
                                                            <span className="block truncate font-medium">{library.name}</span>
                                                            {library.type && <span className="text-xs text-fg-subtle">{library.type}</span>}
                                                        </span>
                                                        <Badge tone={library.status === 'ready' ? 'success' : library.status === 'warning' ? 'error' : 'neutral'}>
                                                            {plannedActionLabel(library.planned_action)}
                                                        </Badge>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* External Catalog Snapshot (V2 A) */}
                        <div className="mf-panel p-6">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="grid size-9 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                            <CatalogIcon className="size-5" />
                                        </span>
                                        <h2 className="text-lg font-semibold tracking-tight">External Catalog Snapshot</h2>
                                        <CatalogStatusBadge status={catalog.status} />
                                    </div>
                                    <p className="mt-2 text-sm text-fg-muted">
                                        Read-only snapshot. No media import in V2 A. No files are copied, moved or deleted.
                                    </p>
                                </div>
                                <Link className={buttonClasses('secondary', 'sm')} href="/catalog">View catalog</Link>
                            </div>

                            <dl className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {[
                                    ['External items', catalog.present_item_count],
                                    ['Missing', catalog.missing_item_count],
                                    ['Snapshot runs', catalog.snapshot_run_count],
                                    ['Warnings', lastSnapshot?.warnings_count ?? 0],
                                ].map(([label, value]) => (
                                    <div className="mf-panel px-3 py-2.5 text-center" key={label}>
                                        <dt className="text-[0.7rem] uppercase tracking-wide text-fg-subtle">{label}</dt>
                                        <dd className="mt-1 text-lg font-semibold">{value}</dd>
                                    </div>
                                ))}
                            </dl>

                            <div className="mt-5">
                                {!lastSnapshot ? (
                                    <EmptyState
                                        description="Take a read-only snapshot of a discovered library above. External items are captured for display only — nothing is imported, moved or deleted."
                                        icon={<CatalogIcon className="size-5" />}
                                        title="No snapshot yet"
                                    />
                                ) : (
                                    <div className="rounded-[--radius-md] border border-[var(--panel-border)] p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">{snapshotStatusLabel(lastSnapshot.status)}</p>
                                            <span className="text-xs text-fg-subtle">
                                                {lastSnapshot.summary.library_name}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs text-fg-subtle">
                                            Started {formatCheckedAt(lastSnapshot.started_at)} · Finished {formatCheckedAt(lastSnapshot.finished_at)}
                                        </p>
                                        <p className="mt-1 text-xs text-fg-subtle">
                                            {lastSnapshot.items_stored_count} stored of {lastSnapshot.items_seen_count} seen
                                            {lastSnapshot.errors_count > 0 && ` · ${lastSnapshot.errors_count} error${lastSnapshot.errors_count === 1 ? '' : 's'}`}
                                        </p>

                                        {lastSnapshot.summary.issues.length > 0 && (
                                            <ul className="mt-3 grid gap-1.5">
                                                {lastSnapshot.summary.issues.map((issue) => (
                                                    <li className="flex items-start gap-2 text-sm" key={issue.code}>
                                                        <Badge tone={issue.blocking ? 'error' : 'neutral'}>{issue.action}</Badge>
                                                        <span className="text-fg-muted">{issue.message}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right column */}
                    <div className="mf-col-5 mf-rise flex flex-col gap-6" style={{ '--mf-i': 2 } as CSSProperties}>
                        <div className="mf-panel p-6">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-fg-subtle">Connection Health</h2>
                            <div className="mt-3"><StatusBadge status={connector.status} /></div>
                            <dl className="mt-4 grid gap-2 text-sm">
                                <div className="flex justify-between gap-4"><dt className="text-fg-muted">Last checked</dt><dd className="font-medium">{formatCheckedAt(connector.last_checked_at)}</dd></div>
                                {connector.health_detail && <div className="flex justify-between gap-4"><dt className="text-fg-muted">Detail</dt><dd className="text-right font-medium">{connector.health_detail}</dd></div>}
                            </dl>
                            <div className="mt-4">
                                <Button className="w-full" disabled={!connector.configured} loading={testing} onClick={runTest} variant="secondary">Test connection</Button>
                            </div>
                            <p className="mt-3 text-xs text-fg-subtle">No network calls happen during page render. Tests run only when triggered.</p>
                        </div>

                        <div className="mf-panel p-6">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-fg-subtle">Secret Protection</h2>
                            <ul className="mt-3 grid gap-2 text-sm">
                                {SECRET_FACTS.map((fact) => (
                                    <li className="flex items-center gap-2" key={fact}>
                                        <CheckIcon className="size-4 text-success" />
                                        <span className="text-fg-muted">{fact}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="mf-panel p-6">
                            <div className="flex items-center gap-2">
                                <h2 className="text-sm font-semibold uppercase tracking-wide text-fg-subtle">Future integrated engine</h2>
                                <Badge tone="neutral">Later</Badge>
                            </div>
                            <p className="mt-3 text-sm text-fg-muted">
                                Later, Jellyfin/Audiobookshelf may become first-class MediaForge engine areas. This connector
                                remains compatibility mode.
                            </p>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
