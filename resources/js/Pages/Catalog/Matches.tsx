import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import {
    type CatalogLibraryOption,
    type ConnectorRef,
    episodeLabel,
    type ExternalMediaKind,
    type NormalizationIssueView,
    NormalizationStatusBadge,
    type NormalizationStatus,
    type NormalizationSummary,
    mediaKindLabel,
} from '@/Components/Connectors/ConnectorStatus';
import Badge from '@/Components/UI/Badge';
import { buttonClasses } from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import { CatalogIcon, ShieldIcon } from '@/Components/UI/Icon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface MatchItem {
    id: string;
    title: string;
    kind: ExternalMediaKind;
    release_year: number | null;
    season_number: number | null;
    episode_number: number | null;
    parent_title: string | null;
    confidence: number;
    status: NormalizationStatus;
    issues: NormalizationIssueView[];
    connector: ConnectorRef | null;
    library_name: string | null;
}

interface DuplicateGroup {
    group_key: string;
    title: string;
    release_year: number | null;
    kind: ExternalMediaKind;
    item_count: number;
    score: number;
    reason: string;
    items: MatchItem[];
}

interface EpisodeGroup {
    group_key: string;
    parent_title: string;
    season_number: number | null;
    item_count: number;
    missing_episode_count: number;
    score: number;
    reason: string;
    items: MatchItem[];
}

interface AudiobookGroup {
    group_key: string;
    title: string;
    release_year: number | null;
    item_count: number;
    score: number;
    reason: string;
    items: MatchItem[];
}

interface MatchPreview {
    duplicate_suspects: DuplicateGroup[];
    episode_groups: EpisodeGroup[];
    audiobook_groups: AudiobookGroup[];
    weak_metadata: MatchItem[];
    note: string;
}

interface MatchesPageProps {
    [key: string]: unknown;
    connectors: ConnectorRef[];
    preview: MatchPreview;
    normalization: NormalizationSummary;
    libraryOptions: CatalogLibraryOption[];
    filters: { connector: string; library: string };
}

const CONTROL =
    'rounded-[--radius-md] border border-[var(--panel-border)] bg-[rgb(var(--surface-hover))] px-3 py-2 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/25';

function scoreTone(score: number): 'success' | 'warning' | 'neutral' {
    if (score >= 80) return 'success';
    if (score >= 60) return 'warning';

    return 'neutral';
}

/** A candidate item row inside a group. Purely informational — nothing is clickable-to-accept. */
function ItemRow({ item, showEpisode = false }: { item: MatchItem; showEpisode?: boolean }) {
    const episode = showEpisode ? episodeLabel(item.season_number, item.episode_number) : null;
    const facts = [episode, item.release_year, item.connector?.label, item.library_name].filter(Boolean).join(' · ');

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 py-2.5 text-sm first:pt-0">
            <span className="min-w-0">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{item.title}</span>
                    <Badge tone="neutral">{mediaKindLabel(item.kind)}</Badge>
                </span>
                <span className="text-xs text-fg-subtle">{facts || '—'}</span>
            </span>
            <span className="flex shrink-0 items-center gap-2">
                <span className="text-xs text-fg-subtle">{item.confidence}%</span>
                <NormalizationStatusBadge status={item.status} />
            </span>
        </li>
    );
}

/** One suggestion group: what it is, why we think so, and the items involved. */
function GroupCard({
    title,
    subtitle,
    score,
    reason,
    itemCount,
    children,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    score: number;
    reason: string;
    itemCount: number;
    children: ReactNode;
}) {
    return (
        <div className="mf-panel p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-2 font-semibold">{title}</p>
                    {subtitle && <p className="mt-0.5 text-xs text-fg-subtle">{subtitle}</p>}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Badge tone="neutral">
                        {itemCount} {itemCount === 1 ? 'item' : 'items'}
                    </Badge>
                    <Badge tone={scoreTone(score)}>Score {score}</Badge>
                </div>
            </div>
            <p className="mt-2 text-sm text-fg-muted">{reason}</p>
            <ul className="mt-3 divide-y divide-[var(--panel-border)]">{children}</ul>
        </div>
    );
}

export default function CatalogMatches() {
    const { connectors, preview, normalization, libraryOptions, filters } = usePage<MatchesPageProps>().props;

    const hasAnything =
        preview.duplicate_suspects.length > 0 ||
        preview.episode_groups.length > 0 ||
        preview.audiobook_groups.length > 0 ||
        preview.weak_metadata.length > 0;

    function navigate(overrides: Partial<{ connector: string; library: string }>) {
        const next = { ...filters, ...overrides };
        const params: Record<string, string> = {};
        if (next.connector) params.connector = next.connector;
        if (next.library) params.library = next.library;

        router.get('/catalog/matches', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Matching Preview" />

            <AuthenticatedLayout>
                <div className="mf-grid">
                    <header className="mf-col-12 mf-rise flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 text-sm">
                                <Link className="text-fg-muted transition-colors hover:text-fg" href="/catalog">
                                    External Catalog
                                </Link>
                                <span className="text-fg-subtle">/</span>
                                <span className="text-fg-muted">Matching Preview</span>
                            </div>
                            <h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Matching Preview</h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">{preview.note}</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <label className="flex items-center gap-2 text-sm">
                                <span className="sr-only">Connector</span>
                                <select
                                    aria-label="Filter by connector"
                                    className={CONTROL}
                                    onChange={(event) => navigate({ connector: event.target.value, library: '' })}
                                    value={filters.connector}
                                >
                                    <option value="">All connectors</option>
                                    {connectors.map((connector) => (
                                        <option key={connector.key} value={connector.key}>
                                            {connector.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            {libraryOptions.length > 0 && (
                                <label className="flex items-center gap-2 text-sm">
                                    <span className="sr-only">Library</span>
                                    <select
                                        aria-label="Filter by library"
                                        className={CONTROL}
                                        onChange={(event) => navigate({ library: event.target.value })}
                                        value={filters.library}
                                    >
                                        <option value="">All libraries</option>
                                        {libraryOptions.map((library) => (
                                            <option key={library.id} value={library.id}>
                                                {library.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}
                            <Link className={buttonClasses('secondary', 'sm')} href="/catalog">
                                Back to catalog
                            </Link>
                        </div>
                    </header>

                    {/* The read-only promise, stated where the suggestions are. */}
                    <section className="mf-col-12">
                        <div className="mf-panel flex items-start gap-3 p-5">
                            <span className="grid size-9 shrink-0 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                <ShieldIcon className="size-4" />
                            </span>
                            <p className="text-xs text-fg-muted">
                                Matching preview only. No imports or merges in V2 C. Nothing here is accepted, merged or written to
                                MediaForge, and no files are copied, moved, deleted or renamed. An import plan arrives later.
                            </p>
                        </div>
                    </section>

                    {!hasAnything ? (
                        <section className="mf-col-12">
                            <EmptyState
                                description="Every normalized item looks unambiguous — no duplicate suspects, groupings or weak metadata to review. Take a snapshot or rebuild normalization to refresh this preview."
                                icon={<CatalogIcon className="size-5" />}
                                title="No matching issues found"
                            />
                        </section>
                    ) : (
                        <>
                            {/* Duplicate suspects */}
                            <section className="mf-col-12">
                                <h2 className="mb-3 text-lg font-semibold tracking-tight">
                                    Duplicate suspects{' '}
                                    <span className="text-sm font-normal text-fg-subtle">
                                        ({normalization.duplicate_suspects} items)
                                    </span>
                                </h2>
                                {preview.duplicate_suspects.length === 0 ? (
                                    <EmptyState description="No two items share a normalized identity." icon={<CatalogIcon className="size-5" />} title="No duplicate suspects" />
                                ) : (
                                    <div className="grid gap-4 xl:grid-cols-2">
                                        {preview.duplicate_suspects.map((group) => (
                                            <GroupCard
                                                itemCount={group.item_count}
                                                key={group.group_key}
                                                reason={group.reason}
                                                score={group.score}
                                                subtitle={[group.release_year, mediaKindLabel(group.kind)].filter(Boolean).join(' · ')}
                                                title={group.title}
                                            >
                                                {group.items.map((item) => (
                                                    <ItemRow item={item} key={item.id} />
                                                ))}
                                            </GroupCard>
                                        ))}
                                    </div>
                                )}
                            </section>

                            {/* Episode groupings */}
                            <section className="mf-col-12">
                                <h2 className="mb-3 text-lg font-semibold tracking-tight">Episode grouping candidates</h2>
                                {preview.episode_groups.length === 0 ? (
                                    <EmptyState description="No episodes with a shared series and season were captured." icon={<CatalogIcon className="size-5" />} title="No episode groups" />
                                ) : (
                                    <div className="grid gap-4 xl:grid-cols-2">
                                        {preview.episode_groups.map((group) => (
                                            <GroupCard
                                                itemCount={group.item_count}
                                                key={group.group_key}
                                                reason={group.reason}
                                                score={group.score}
                                                subtitle={group.season_number !== null ? `Season ${group.season_number}` : 'Season unknown'}
                                                title={group.parent_title}
                                            >
                                                {group.items.map((item) => (
                                                    <ItemRow item={item} key={item.id} showEpisode />
                                                ))}
                                            </GroupCard>
                                        ))}
                                    </div>
                                )}
                            </section>

                            {/* Audiobook/book groupings */}
                            <section className="mf-col-12">
                                <h2 className="mb-3 text-lg font-semibold tracking-tight">Audiobook and book grouping candidates</h2>
                                {preview.audiobook_groups.length === 0 ? (
                                    <EmptyState description="No audiobooks or books share a normalized title." icon={<CatalogIcon className="size-5" />} title="No audiobook groups" />
                                ) : (
                                    <div className="grid gap-4 xl:grid-cols-2">
                                        {preview.audiobook_groups.map((group) => (
                                            <GroupCard
                                                itemCount={group.item_count}
                                                key={group.group_key}
                                                reason={group.reason}
                                                score={group.score}
                                                subtitle={group.release_year !== null ? String(group.release_year) : 'Year unknown'}
                                                title={group.title}
                                            >
                                                {group.items.map((item) => (
                                                    <ItemRow item={item} key={item.id} />
                                                ))}
                                            </GroupCard>
                                        ))}
                                    </div>
                                )}
                            </section>

                            {/* Weak metadata */}
                            <section className="mf-col-12">
                                <h2 className="mb-3 text-lg font-semibold tracking-tight">
                                    Items with weak metadata{' '}
                                    <span className="text-sm font-normal text-fg-subtle">({normalization.needs_review} need review)</span>
                                </h2>
                                {preview.weak_metadata.length === 0 ? (
                                    <EmptyState description="Every item carries enough metadata to be interpreted." icon={<CatalogIcon className="size-5" />} title="No weak metadata" />
                                ) : (
                                    <div className="mf-panel p-5">
                                        <ul className="divide-y divide-[var(--panel-border)]">
                                            {preview.weak_metadata.map((item) => (
                                                <li className="flex flex-wrap items-start justify-between gap-3 py-3 text-sm first:pt-0" key={item.id}>
                                                    <span className="min-w-0">
                                                        <span className="flex flex-wrap items-center gap-2">
                                                            <span className="font-medium">{item.title}</span>
                                                            <Badge tone="neutral">{mediaKindLabel(item.kind)}</Badge>
                                                        </span>
                                                        <span className="text-xs text-fg-subtle">
                                                            {[item.connector?.label, item.library_name].filter(Boolean).join(' · ') || '—'}
                                                        </span>
                                                        {item.issues.length > 0 && (
                                                            <span className="mt-1 block text-xs text-fg-muted">
                                                                {item.issues.map((issue) => issue.message).join(' ')}
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="flex shrink-0 items-center gap-2">
                                                        <span className="text-xs text-fg-subtle">{item.confidence}%</span>
                                                        <NormalizationStatusBadge status={item.status} />
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </section>
                        </>
                    )}
                </div>
            </AuthenticatedLayout>
        </>
    );
}
