import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    type ConnectorSummary,
    formatCheckedAt,
    StatusBadge,
} from '@/Components/Connectors/ConnectorStatus';

interface ConnectorShowProps {
    [key: string]: unknown;
    connector: ConnectorSummary;
    flash: { success: string | null; error: string | null };
}

export default function ConnectorShow() {
    const { connector, flash } = usePage<ConnectorShowProps>().props;
    const [testing, setTesting] = useState(false);

    const form = useForm<{ base_url: string; secret: string; clear_secret: boolean }>({
        base_url: connector.base_url,
        secret: '',
        clear_secret: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/connectors/${connector.key}`, {
            preserveScroll: true,
            onSuccess: () => form.setData((data) => ({ ...data, secret: '', clear_secret: false })),
        });
    }

    function runTest() {
        setTesting(true);
        router.post(`/connectors/${connector.key}/test`, {}, {
            preserveScroll: true,
            onFinish: () => setTesting(false),
        });
    }

    return (
        <>
            <Head title={`${connector.label} connector`} />

            <AuthenticatedLayout>
                <section className="flex max-w-2xl flex-col gap-6">
                    <div>
                        <Link className="text-sm font-medium text-accent hover:text-accent-hover" href="/connectors">
                            ← Connectors
                        </Link>
                        <div className="mt-2 flex items-center justify-between gap-4">
                            <h1 className="text-3xl font-semibold tracking-tight">{connector.label}</h1>
                            <StatusBadge status={connector.status} />
                        </div>
                        <p className="mt-2 text-fg-muted">
                            Store the server address and API key, then run a connection test. Connection test only — no library
                            sync in V1 C.
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

                    <form
                        className="flex flex-col gap-5 rounded-[--radius-md] border border-line bg-surface-raised p-6 shadow-sm"
                        onSubmit={submit}
                    >
                        <label className="block space-y-1.5 text-sm font-medium">
                            <span>Base URL</span>
                            <input
                                autoComplete="off"
                                className="w-full rounded-[--radius-sm] border border-line bg-surface px-3 py-2 text-fg outline-none focus:border-accent"
                                name="base_url"
                                onChange={(event) => form.setData('base_url', event.target.value)}
                                placeholder="http://localhost:8096"
                                required
                                type="url"
                                value={form.data.base_url}
                            />
                            {form.errors.base_url && <p className="text-sm text-error">{form.errors.base_url}</p>}
                        </label>

                        <label className="block space-y-1.5 text-sm font-medium">
                            <span>
                                API key / token
                                <span className="ml-2 font-normal text-fg-muted">
                                    {connector.secret_configured ? '(Configured)' : '(Not configured)'}
                                </span>
                            </span>
                            <input
                                autoComplete="new-password"
                                className="w-full rounded-[--radius-sm] border border-line bg-surface px-3 py-2 text-fg outline-none focus:border-accent disabled:opacity-60"
                                disabled={form.data.clear_secret}
                                name="secret"
                                onChange={(event) => form.setData('secret', event.target.value)}
                                placeholder={connector.secret_configured ? 'Leave blank to keep the stored key' : 'Enter the API key'}
                                type="password"
                                value={form.data.secret}
                            />
                            {form.errors.secret && <p className="text-sm text-error">{form.errors.secret}</p>}
                            <span className="block font-normal text-fg-muted">
                                The stored key is never displayed. Leave this blank to keep it unchanged.
                            </span>
                        </label>

                        {connector.secret_configured && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    checked={form.data.clear_secret}
                                    onChange={(event) => form.setData('clear_secret', event.target.checked)}
                                    type="checkbox"
                                />
                                <span>Remove the stored API key</span>
                            </label>
                        )}

                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                className="rounded-[--radius-sm] bg-accent px-4 py-2 text-sm font-medium text-on-accent disabled:cursor-not-allowed disabled:opacity-60"
                                disabled={form.processing}
                                type="submit"
                            >
                                Save
                            </button>
                            <button
                                className="rounded-[--radius-sm] border border-line px-4 py-2 text-sm font-medium transition-colors hover:bg-surface-sunken disabled:cursor-not-allowed disabled:opacity-60"
                                disabled={!connector.configured || testing}
                                onClick={runTest}
                                type="button"
                            >
                                {testing ? 'Testing…' : 'Test connection'}
                            </button>
                        </div>
                    </form>

                    <div className="rounded-[--radius-md] border border-line bg-surface-sunken p-5 text-sm">
                        <h2 className="font-semibold">Connection status</h2>
                        <dl className="mt-3 grid gap-2 text-fg-muted">
                            <div className="flex justify-between gap-4">
                                <dt>Health</dt>
                                <dd><StatusBadge status={connector.status} /></dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt>Last checked</dt>
                                <dd>{formatCheckedAt(connector.last_checked_at)}</dd>
                            </div>
                            {connector.health_detail && (
                                <div className="flex justify-between gap-4">
                                    <dt>Detail</dt>
                                    <dd className="text-right text-fg">{connector.health_detail}</dd>
                                </div>
                            )}
                        </dl>
                        <p className="mt-4 text-fg-muted">Connection test only — no library sync, scan, or media import runs in V1 C.</p>
                    </div>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
