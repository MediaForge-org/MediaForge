import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    type ConnectorSummary,
    discoverySummary,
    formatCheckedAt,
    StatusBadge,
} from '@/Components/Connectors/ConnectorStatus';

interface ConnectorsPageProps {
    [key: string]: unknown;
    connectors: ConnectorSummary[];
    flash: { success: string | null; error: string | null };
}

export default function ConnectorsIndex() {
    const { connectors, flash } = usePage<ConnectorsPageProps>().props;
    const [testing, setTesting] = useState<string | null>(null);

    function runTest(key: string) {
        setTesting(key);
        router.post(`/connectors/${key}/test`, {}, {
            preserveScroll: true,
            onFinish: () => setTesting(null),
        });
    }

    return (
        <>
            <Head title="Connectors" />

            <AuthenticatedLayout>
                <section className="flex flex-col gap-8">
                    <div className="rounded-[--radius-md] border border-line bg-surface-raised p-6 shadow-sm sm:p-8">
                        <p className="text-sm font-medium text-accent">V1 foundation</p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">Connectors</h1>
                        <p className="mt-2 max-w-2xl text-fg-muted">
                            Configure your Jellyfin and Audiobookshelf servers and verify the connection. This package covers
                            connection tests only — no library sync or media import runs here.
                        </p>
                    </div>

                    {flash.success && (
                        <p className="rounded-[--radius-md] border border-success/40 bg-success/10 px-4 py-3 text-sm text-success">
                            {flash.success}
                        </p>
                    )}
                    {flash.error && (
                        <p className="rounded-[--radius-md] border border-error/40 bg-error/10 px-4 py-3 text-sm text-error">
                            {flash.error}
                        </p>
                    )}

                    <div className="grid gap-4 md:grid-cols-2">
                        {connectors.map((connector) => (
                            <article
                                className="flex flex-col gap-4 rounded-[--radius-md] border border-line bg-surface-raised p-5 shadow-sm"
                                key={connector.key}
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 className="text-lg font-semibold">{connector.label}</h2>
                                        <p className="mt-1 break-all text-sm text-fg-muted">
                                            {connector.base_url || 'No base URL configured'}
                                        </p>
                                    </div>
                                    <StatusBadge status={connector.status} />
                                </div>

                                <dl className="grid gap-1 text-sm text-fg-muted">
                                    <div className="flex justify-between gap-4">
                                        <dt>Credentials</dt>
                                        <dd>{connector.secret_configured ? 'Configured' : 'Not configured'}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt>Last checked</dt>
                                        <dd>{formatCheckedAt(connector.last_checked_at)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt>Libraries</dt>
                                        <dd>{discoverySummary(connector)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt>Last discovered</dt>
                                        <dd>{formatCheckedAt(connector.libraries_discovered_at)}</dd>
                                    </div>
                                </dl>

                                {connector.health_detail && (
                                    <p className="text-sm text-fg-muted">{connector.health_detail}</p>
                                )}

                                <div className="mt-auto flex flex-wrap items-center gap-2">
                                    <Link
                                        className="rounded-[--radius-sm] bg-accent px-3 py-2 text-sm font-medium text-on-accent"
                                        href={`/connectors/${connector.key}`}
                                    >
                                        Configure
                                    </Link>
                                    <button
                                        className="rounded-[--radius-sm] border border-line px-3 py-2 text-sm font-medium transition-colors hover:bg-surface-sunken disabled:cursor-not-allowed disabled:opacity-60"
                                        disabled={!connector.configured || testing === connector.key}
                                        onClick={() => runTest(connector.key)}
                                        type="button"
                                    >
                                        {testing === connector.key ? 'Testing…' : 'Test connection'}
                                    </button>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
