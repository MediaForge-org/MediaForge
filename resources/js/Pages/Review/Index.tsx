import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { type ConnectorSummary, formatCheckedAt, StatusBadge } from '@/Components/Connectors/ConnectorStatus';
import Alert from '@/Components/UI/Alert';
import Badge, { type BadgeTone } from '@/Components/UI/Badge';
import Button, { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { AlertIcon, CheckIcon, ShieldIcon } from '@/Components/UI/Icon';

type ReviewStatus = 'all_clear' | 'warnings' | 'attention_required';
type TaskStatus = 'open' | 'in_review' | 'resolved' | 'dismissed' | 'expired';

interface ReviewIssue {
    code: string;
    message: string;
    action: string;
    blocking: boolean;
}

interface ReviewTaskItem {
    id: string;
    task_type: string;
    subject_type: string;
    subject_id: string;
    status: TaskStatus;
    priority: 'low' | 'normal' | 'high';
    connector: { key: string; label: string } | null;
    issues: ReviewIssue[];
    created_at: string | null;
    resolved_at: string | null;
    resolution: { reason: string } | null;
    can_manage: boolean;
}

interface ReviewSummary {
    status: ReviewStatus;
    open_task_count: number;
}

interface ReviewPageProps {
    [key: string]: unknown;
    connectors: ConnectorSummary[];
    openTasks: ReviewTaskItem[];
    resolvedTasks: ReviewTaskItem[];
    summary: ReviewSummary;
    flash: { success: string | null; error: string | null };
}

const SUMMARY_META: Record<ReviewStatus, { label: string; tone: BadgeTone }> = {
    all_clear: { label: 'All clear', tone: 'success' },
    warnings: { label: 'Warnings', tone: 'warning' },
    attention_required: { label: 'Attention required', tone: 'error' },
};

const RESOLUTION_LABEL: Record<string, string> = {
    dry_run_clean: 'Cleared automatically by a clean dry run',
    dismissed_by_user: 'Dismissed manually',
};

function taskTitle(task: ReviewTaskItem): string {
    const who = task.connector?.label ?? 'Connector sync';

    return `${who} needs attention`;
}

export default function ReviewIndex() {
    const { connectors, openTasks, resolvedTasks, summary, flash } = usePage<ReviewPageProps>().props;
    const [busy, setBusy] = useState<string | null>(null);

    function post(url: string, tag: string) {
        setBusy(tag);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    }

    const highPriority = openTasks.filter((t) => t.priority === 'high');
    const otherOpen = openTasks.filter((t) => t.priority !== 'high');

    const healthyConfigured = connectors.filter((c) => c.configured && c.status === 'healthy').length;
    const configuredCount = connectors.filter((c) => c.configured).length;

    const dryRunWarningCount = connectors.filter((c) => {
        const status = c.sync.last_run?.status;

        return status === 'completed_with_warnings' || status === 'failed';
    }).length;

    const readyForSyncCount = connectors.filter((c) => c.sync.status === 'last_dry_run_completed').length;

    const nextActions: { label: string; href: string }[] = [];
    if (summary.open_task_count > 0) {
        nextActions.push({
            label: `Resolve ${summary.open_task_count} open review ${summary.open_task_count === 1 ? 'task' : 'tasks'}`,
            href: '#open-tasks',
        });
    }
    for (const connector of connectors) {
        if (!connector.configured) {
            nextActions.push({ label: `Configure ${connector.label}`, href: `/connectors/${connector.key}` });
        } else if (connector.sync.status === 'ready_for_dry_run' && !connector.sync.last_run) {
            nextActions.push({ label: `Run a dry run for ${connector.label}`, href: `/connectors/${connector.key}` });
        }
    }

    function TaskCard({ task }: { task: ReviewTaskItem }) {
        const isOpen = task.status === 'open' || task.status === 'in_review';

        return (
            <div className="mf-panel p-5">
                <div className="flex items-start gap-3">
                    <span
                        className={`grid size-10 shrink-0 place-items-center rounded-[--radius-md] ring-1 ring-inset ${
                            task.priority === 'high'
                                ? 'bg-error/10 text-error ring-error/20'
                                : 'bg-warning/10 text-warning ring-warning/20'
                        }`}
                    >
                        <AlertIcon className="size-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="font-semibold">{taskTitle(task)}</h3>
                            <Badge tone={task.priority === 'high' ? 'error' : 'warning'}>
                                {task.priority === 'high' ? 'Needs attention' : 'Warning'}
                            </Badge>
                            {!isOpen && <Badge tone="neutral">{task.status}</Badge>}
                        </div>
                        <p className="mt-1 text-xs text-fg-subtle">
                            Opened {formatCheckedAt(task.created_at)}
                            {task.resolved_at && ` · Resolved ${formatCheckedAt(task.resolved_at)}`}
                        </p>
                        {task.resolution?.reason && (
                            <p className="mt-1 text-xs text-fg-subtle">
                                {RESOLUTION_LABEL[task.resolution.reason] ?? task.resolution.reason}
                            </p>
                        )}

                        {task.issues.length > 0 && (
                            <ul className="mt-3 grid gap-1.5">
                                {task.issues.map((issue) => (
                                    <li className="flex items-start gap-2 text-sm" key={issue.code}>
                                        <Badge tone={issue.blocking ? 'error' : 'neutral'}>{issue.action}</Badge>
                                        <span className="text-fg-muted">{issue.message}</span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            {task.connector && (
                                <Link className={buttonClasses('secondary', 'sm')} href={`/connectors/${task.connector.key}`}>
                                    View connector
                                </Link>
                            )}
                            <Link className={buttonClasses('secondary', 'sm')} href="/sync">
                                View sync
                            </Link>
                            {task.connector && (
                                <Button
                                    loading={busy === `dry-${task.id}`}
                                    onClick={() => post(`/connectors/${task.connector!.key}/sync/dry-run`, `dry-${task.id}`)}
                                    size="sm"
                                    variant="secondary"
                                >
                                    Run dry run
                                </Button>
                            )}
                            {task.can_manage && isOpen && (
                                <Button
                                    loading={busy === `dismiss-${task.id}`}
                                    onClick={() => post(`/review/tasks/${task.id}/dismiss`, `dismiss-${task.id}`)}
                                    size="sm"
                                    variant="ghost"
                                >
                                    Dismiss
                                </Button>
                            )}
                            {task.can_manage && !isOpen && (
                                <Button
                                    loading={busy === `reopen-${task.id}`}
                                    onClick={() => post(`/review/tasks/${task.id}/reopen`, `reopen-${task.id}`)}
                                    size="sm"
                                    variant="ghost"
                                >
                                    Reopen
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <>
            <Head title="Review Center" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <span className="mf-status-pill mb-3">V1 foundation</span>
                            <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Review Center</h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">
                                Resolve connector, discovery and dry-run issues before future sync.
                            </p>
                        </div>
                        <Badge dot tone={SUMMARY_META[summary.status].tone}>
                            {SUMMARY_META[summary.status].label}
                        </Badge>
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

                    {/* Top summary cards */}
                    <section className="mf-col-12">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                { label: 'Open Tasks', value: String(summary.open_task_count), hint: 'Across all connectors' },
                                { label: 'Connector Health', value: `${healthyConfigured}/${configuredCount || connectors.length} healthy`, hint: 'From the last connection test' },
                                { label: 'Dry Run Warnings', value: String(dryRunWarningCount), hint: 'Latest run had issues' },
                                { label: 'Ready for Future Sync', value: String(readyForSyncCount), hint: 'Last dry run completed cleanly' },
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
                    <section className="mf-col-8" id="open-tasks">
                        <h2 className="mb-4 text-lg font-semibold tracking-tight">Review tasks</h2>

                        {openTasks.length === 0 ? (
                            <EmptyState
                                description="No connector or sync issues need attention right now."
                                icon={<CheckIcon className="size-5" />}
                                title="All clear"
                            />
                        ) : (
                            <div className="grid gap-4">
                                {highPriority.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-subtle">
                                            Needs attention ({highPriority.length})
                                        </p>
                                        <div className="grid gap-3">
                                            {highPriority.map((task) => (
                                                <TaskCard key={task.id} task={task} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {otherOpen.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-subtle">
                                            Warnings ({otherOpen.length})
                                        </p>
                                        <div className="grid gap-3">
                                            {otherOpen.map((task) => (
                                                <TaskCard key={task.id} task={task} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {resolvedTasks.length > 0 && (
                            <div className="mt-8">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-fg-subtle">
                                    Recently resolved ({resolvedTasks.length})
                                </p>
                                <div className="grid gap-3">
                                    {resolvedTasks.map((task) => (
                                        <TaskCard key={task.id} task={task} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </section>

                    {/* Side column */}
                    <section className="mf-col-4">
                        <div className="grid gap-4">
                            <div className="mf-panel p-5">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-fg-subtle">
                                    Connector health
                                </h2>
                                <div className="grid gap-2">
                                    {connectors.map((connector) => (
                                        <Link
                                            className="flex items-center justify-between gap-3 rounded-[--radius-md] bg-[var(--nav-hover-bg)] px-3.5 py-2.5 text-sm transition-colors hover:bg-[var(--nav-active-bg)]"
                                            href={`/connectors/${connector.key}`}
                                            key={connector.key}
                                        >
                                            <span className="text-fg-muted">{connector.label}</span>
                                            <StatusBadge status={connector.status} />
                                        </Link>
                                    ))}
                                </div>
                            </div>

                            <div className="mf-panel p-5">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-fg-subtle">
                                    Next recommended steps
                                </h2>
                                {nextActions.length === 0 ? (
                                    <p className="text-sm text-fg-muted">Nothing pending — connectors are configured and up to date.</p>
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

                            <div className="mf-panel flex items-start gap-3 p-5">
                                <span className="grid size-9 shrink-0 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                    <ShieldIcon className="size-4" />
                                </span>
                                <p className="text-xs text-fg-muted">
                                    Review Center only guides safe preparation. No media files are changed in V1.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
