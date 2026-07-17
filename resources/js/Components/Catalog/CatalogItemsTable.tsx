import { Link } from '@inertiajs/react';

import {
    type CatalogItemsPage,
    episodeLabel,
    formatCheckedAt,
    formatRuntime,
    mediaKindLabel,
    NormalizationStatusBadge,
} from '@/Components/Connectors/ConnectorStatus';
import Badge from '@/Components/UI/Badge';
import EmptyState from '@/Components/UI/EmptyState';
import { LibraryIcon } from '@/Components/UI/Icon';

interface CatalogItemsTableProps {
    page: CatalogItemsPage;
    /** Base path for pagination links (filters are merged from the current query). */
    basePath: string;
    /** Current query params (minus page) so pagination keeps the active filters. */
    query: Record<string, string>;
    /** Show the connector column (hidden on a single-connector page). */
    showConnector?: boolean;
    /** Show the library column (hidden on a single-library page). */
    showLibrary?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
}

function pageHref(basePath: string, query: Record<string, string>, page: number): string {
    const params = new URLSearchParams(query);
    if (page > 1) {
        params.set('page', String(page));
    } else {
        params.delete('page');
    }
    const qs = params.toString();

    return qs === '' ? basePath : `${basePath}?${qs}`;
}

export default function CatalogItemsTable({
    page,
    basePath,
    query,
    showConnector = true,
    showLibrary = true,
    emptyTitle = 'No external items match',
    emptyDescription = 'Adjust the filters, or take a read-only snapshot to capture external items. Nothing is imported, moved or deleted.',
}: CatalogItemsTableProps) {
    const { data, meta } = page;

    if (data.length === 0) {
        return <EmptyState description={emptyDescription} icon={<LibraryIcon className="size-5" />} title={emptyTitle} />;
    }

    const columnCount = 4 + (showConnector ? 1 : 0) + (showLibrary ? 1 : 0);

    return (
        <div className="mf-panel overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[44rem] text-left text-sm">
                    <thead className="border-b border-[var(--panel-border)] text-xs uppercase tracking-wide text-fg-subtle">
                        <tr>
                            <th className="px-4 py-3 font-medium">Title</th>
                            <th className="px-4 py-3 font-medium">Kind</th>
                            <th className="px-4 py-3 font-medium">Quality</th>
                            {showConnector && <th className="px-4 py-3 font-medium">Connector</th>}
                            {showLibrary && <th className="px-4 py-3 font-medium">Library</th>}
                            <th className="px-4 py-3 font-medium">Last seen</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[var(--panel-border)]">
                        {data.map((item) => {
                            const norm = item.normalization;
                            // Prefer the normalized reading; fall back to what the connector reported.
                            const title = norm?.title ?? item.title;
                            const kind = norm?.kind ?? item.media_kind;
                            const year = norm?.release_year ?? item.year;
                            const runtime = formatRuntime(norm?.runtime_seconds ?? item.runtime_seconds);
                            const episode = episodeLabel(norm?.season_number ?? null, norm?.episode_number ?? null);
                            const subtitle = [episode, year, runtime].filter(Boolean).join(' · ');

                            return (
                                <tr key={item.id}>
                                    <td className="px-4 py-3">
                                        <span className="flex flex-wrap items-center gap-2 font-medium">
                                            {title}
                                            {!item.is_present && <Badge tone="error">Missing</Badge>}
                                        </span>
                                        <span className="text-xs text-fg-subtle">
                                            {norm?.parent_title ? `${norm.parent_title} · ` : ''}
                                            {subtitle || '—'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge tone="neutral">{mediaKindLabel(kind)}</Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {norm ? (
                                            <span className="flex flex-col gap-1">
                                                <span className="flex items-center gap-2">
                                                    <NormalizationStatusBadge status={norm.status} />
                                                    <span className="text-xs text-fg-subtle">{norm.confidence}%</span>
                                                </span>
                                                {norm.issues.length > 0 && (
                                                    <span className="text-xs text-fg-subtle" title={norm.issues.map((i) => i.message).join(' ')}>
                                                        {norm.issues.length} {norm.issues.length === 1 ? 'issue' : 'issues'}
                                                    </span>
                                                )}
                                            </span>
                                        ) : (
                                            <span className="text-xs text-fg-subtle">Not normalized</span>
                                        )}
                                    </td>
                                    {showConnector && <td className="px-4 py-3 text-fg-muted">{item.connector?.label ?? '—'}</td>}
                                    {showLibrary && <td className="px-4 py-3 text-fg-muted">{item.library_name ?? '—'}</td>}
                                    <td className="px-4 py-3 text-xs text-fg-subtle">{formatCheckedAt(item.last_seen_at)}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                    <tfoot className="border-t border-[var(--panel-border)]">
                        <tr>
                            <td className="px-4 py-3" colSpan={columnCount}>
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <span className="text-xs text-fg-subtle">
                                        {meta.from ?? 0}–{meta.to ?? 0} of {meta.total}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        {meta.current_page > 1 ? (
                                            <Link
                                                className="rounded-[--radius-md] border border-[var(--panel-border)] px-3 py-1.5 text-xs text-fg-muted transition-colors hover:bg-[var(--nav-hover-bg)] hover:text-fg"
                                                href={pageHref(basePath, query, meta.current_page - 1)}
                                                preserveScroll
                                            >
                                                Previous
                                            </Link>
                                        ) : (
                                            <span className="rounded-[--radius-md] border border-[var(--panel-border)] px-3 py-1.5 text-xs text-fg-subtle opacity-50">
                                                Previous
                                            </span>
                                        )}
                                        <span className="text-xs text-fg-subtle">
                                            Page {meta.current_page} of {meta.last_page}
                                        </span>
                                        {meta.current_page < meta.last_page ? (
                                            <Link
                                                className="rounded-[--radius-md] border border-[var(--panel-border)] px-3 py-1.5 text-xs text-fg-muted transition-colors hover:bg-[var(--nav-hover-bg)] hover:text-fg"
                                                href={pageHref(basePath, query, meta.current_page + 1)}
                                                preserveScroll
                                            >
                                                Next
                                            </Link>
                                        ) : (
                                            <span className="rounded-[--radius-md] border border-[var(--panel-border)] px-3 py-1.5 text-xs text-fg-subtle opacity-50">
                                                Next
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}
