import { Head, Link, router, usePage } from '@inertiajs/react';
import { type CSSProperties, useState } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    type ConnectorSummary,
    formatCheckedAt,
    runStatusLabel,
    SyncStatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import Alert from '@/Components/UI/Alert';
import Badge from '@/Components/UI/Badge';
import Button, { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { LibraryIcon, ServerIcon } from '@/Components/UI/Icon';

interface SyncReviewTask {
    id: string;
    priority: string;
    connector: string | null;
    issues: { code: string; message: string; action: string; blocking: boolean }[];
}

interface SyncPageProps {
    [key: string]: unknown;
    connectors: ConnectorSummary[];
    reviewTasks: SyncReviewTask[];
    flash: { success: string | null; error: string | null };
}

export default function SyncIndex() {
    const { connectors, reviewTasks, flash } = usePage<SyncPageProps>().props;
    const [busy, setBusy] = useState<string | null>(null);

    function runDryRun(key: string) {
        setBusy(key);
        router.post(`/connectors/${key}/sync/dry-run`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    }

    return (
        <>
            <Head title="Sync Foundation" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <span className="mf-status-pill mb-3">V1 foundation</span>
                            <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Sync Foundation</h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">
                                Prepare future sync safely. Dry run only — no media import in V1 F. No files are copied, moved or deleted.
                            </p>
                        </div>
                        <span className="mf-status-pill">{connectors.length} connectors</span>
                    </header>

                    {flash.success && <div className="mf-col-12"><Alert tone="success">{flash.success}</Alert></div>}
                    {flash.error && <div className="mf-col-12"><Alert tone="error">{flash.error}</Alert></div>}

                    {connectors.map((connector, i) => {
                        const sync = connector.sync;
                        const lastRun = sync.last_run;

                        return (
                            <section className="mf-col-6 mf-rise" key={connector.key} style={{ '--mf-i': i + 1 } as CSSProperties}>
                                <div className="mf-engine-card flex h-full flex-col gap-5 p-6">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-center gap-3">
                                            <span className="grid size-12 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                                <ServerIcon className="size-6" />
                                            </span>
                                            <div>
                                                <h2 className="text-lg font-semibold">{connector.label}</h2>
                                                <SyncStatusBadge status={sync.status} />
                                            </div>
                                        </div>
                                        <Link className="text-sm text-fg-muted transition-colors hover:text-fg" href={`/connectors/${connector.key}`}>Open</Link>
                                    </div>

                                    <div className="grid grid-cols-3 gap-3">
                                        {[
                                            ['Selected', sync.selected_count],
                                            ['Ready', sync.selected_present_count],
                                            ['Missing', sync.selected_missing_count],
                                        ].map(([label, value]) => (
                                            <div className="mf-panel px-3 py-2.5 text-center" key={label}>
                                                <p className="text-[0.7rem] uppercase tracking-wide text-fg-subtle">{label}</p>
                                                <p className="mt-1 text-sm font-semibold">{value}</p>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5 text-sm">
                                        {lastRun ? (
                                            <span className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="font-medium">{runStatusLabel(lastRun.status)}</span>
                                                <span className="text-xs text-fg-subtle">Finished {formatCheckedAt(lastRun.finished_at)}</span>
                                            </span>
                                        ) : (
                                            <span className="text-fg-muted">No dry run yet</span>
                                        )}
                                    </div>

                                    <div className="mt-auto flex flex-wrap items-center gap-2">
                                        <Link className={buttonClasses('secondary', 'sm')} href={`/connectors/${connector.key}`}>View latest run</Link>
                                        {connector.configured ? (
                                            <Button loading={busy === connector.key} onClick={() => runDryRun(connector.key)} size="sm">
                                                Run dry run
                                            </Button>
                                        ) : (
                                            <Link className={buttonClasses('primary', 'sm')} href={`/connectors/${connector.key}`}>Configure</Link>
                                        )}
                                    </div>
                                </div>
                            </section>
                        );
                    })}

                    <section className="mf-col-12 mf-rise" style={{ '--mf-i': 3 } as CSSProperties}>
                        <div className="mf-panel p-6">
                            <div className="flex items-center gap-2">
                                <h2 className="text-lg font-semibold tracking-tight">Review Tasks</h2>
                                <Badge tone={reviewTasks.length > 0 ? 'error' : 'neutral'}>{reviewTasks.length} open</Badge>
                            </div>
                            <p className="mt-1 text-sm text-fg-muted">Attention items raised by dry runs. Resolve them, then run the dry run again.</p>

                            <div className="mt-5">
                                {reviewTasks.length === 0 ? (
                                    <EmptyState
                                        description="No attention items. Run a dry run on a configured connector to prepare future sync."
                                        icon={<LibraryIcon className="size-5" />}
                                        title="Nothing needs review"
                                    />
                                ) : (
                                    <ul className="grid gap-3">
                                        {reviewTasks.map((task) => (
                                            <li className="rounded-[--radius-md] border border-[var(--panel-border)] p-4" key={task.id}>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium capitalize">{task.connector ?? 'Connector'}</span>
                                                    <Badge tone={task.priority === 'high' ? 'error' : 'neutral'}>{task.priority}</Badge>
                                                </div>
                                                <ul className="mt-2 grid gap-1.5">
                                                    {task.issues.map((issue) => (
                                                        <li className="flex items-start gap-2 text-sm" key={issue.code}>
                                                            <Badge tone={issue.blocking ? 'error' : 'neutral'}>{issue.action}</Badge>
                                                            <span className="text-fg-muted">{issue.message}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
