import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type CSSProperties, type FormEvent, useState } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    type ConnectorDetail,
    type DiscoveredLibrary,
    formatCheckedAt,
    StatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { CheckIcon, LibraryIcon } from '@/Components/UI/Icon';
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

    function toggleLibrary(library: DiscoveredLibrary) {
        setSavingId(library.id);
        router.post(
            `/connectors/${connector.key}/libraries/${library.id}/selection`,
            { enabled: !library.is_enabled },
            { preserveScroll: true, onFinish: () => setSavingId(null) },
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
                                                    <p className="mt-0.5 text-xs text-fg-subtle">Last seen {formatCheckedAt(library.last_seen_at)}</p>
                                                </div>
                                                <label className="flex shrink-0 items-center gap-2 text-sm text-fg-muted">
                                                    <input checked={library.is_enabled} disabled={savingId === library.id} onChange={() => toggleLibrary(library)} type="checkbox" />
                                                    <span>Enable for later sync</span>
                                                </label>
                                            </li>
                                        ))}
                                    </ul>
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
