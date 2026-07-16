import { router } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';

import { filtersToQuery } from '@/Components/Catalog/catalogQuery';
import { type CatalogFilters, type ConnectorRef, type ExternalMediaKind, mediaKindLabel } from '@/Components/Connectors/ConnectorStatus';
import { SearchIcon } from '@/Components/UI/Icon';

const CONTROL =
    'rounded-[--radius-md] border border-[var(--panel-border)] bg-[rgb(var(--surface-hover))] px-3 py-2 text-sm text-fg outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/25 disabled:opacity-60';

const SORT_OPTIONS: { value: string; label: string }[] = [
    { value: 'title', label: 'Title' },
    { value: 'last_seen_at', label: 'Last seen' },
    { value: 'year', label: 'Year' },
    { value: 'media_kind', label: 'Kind' },
];

const STATUS_OPTIONS: { value: string; label: string }[] = [
    { value: 'present', label: 'Present' },
    { value: 'missing', label: 'Missing' },
    { value: 'all', label: 'All' },
];

const DEFAULTS: CatalogFilters = {
    q: '',
    connector: '',
    library: '',
    kind: '',
    status: 'present',
    sort: 'title',
    direction: 'asc',
};

interface LibraryOption {
    id: string;
    name: string;
}

interface CatalogFilterBarProps {
    /** The GET target these filters navigate to (e.g. "/catalog", "/catalog/jellyfin"). */
    basePath: string;
    filters: CatalogFilters;
    kinds: ExternalMediaKind[];
    /** Provided → render the connector select (only on the global overview). */
    connectorOptions?: ConnectorRef[];
    /** Provided → render the library select (overview + connector page). */
    libraryOptions?: LibraryOption[];
}

/**
 * Search / filter / sort controls for the read-only catalog.
 *
 * The controls are driven by a LOCAL DRAFT, never straight from the server props.
 * That is deliberate: filtering navigates via Inertia GET with `preserveState`, so a
 * response lands while the component (and the user's cursor) is still alive. Binding
 * the input to the echoed prop meant an in-flight response could overwrite freshly
 * typed characters with the older search value. `dirty` records that the box holds
 * text the user has not submitted yet, and while it is set no server echo may touch
 * the draft — typed text can never be lost.
 *
 * Search is applied explicitly (Enter or the Search button) rather than on a timer:
 * no debounce races, no request flood, no timers to clean up. Selects apply at once
 * and update the draft optimistically so they never visibly snap back mid-request,
 * and they carry the currently typed text along instead of discarding it.
 *
 * Nothing here mutates data — it only narrows what is displayed.
 */
export default function CatalogFilterBar({ basePath, filters, kinds, connectorOptions, libraryOptions }: CatalogFilterBarProps) {
    const [draft, setDraft] = useState<CatalogFilters>(filters);
    const dirty = useRef(false);

    // Only re-sync from the server on a real external change (reset, back/forward)
    // — and never while unsubmitted text is in the box.
    const serverKey = `${filters.q}|${filters.connector}|${filters.library}|${filters.kind}|${filters.status}|${filters.sort}|${filters.direction}`;

    useEffect(() => {
        if (dirty.current) {
            return;
        }

        setDraft(filters);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverKey]);

    function apply(overrides: Partial<CatalogFilters>) {
        const next = { ...draft, ...overrides };

        // Optimistic: the control shows the chosen value immediately, and what we
        // navigate to IS the draft, so the echo that comes back is a no-op.
        setDraft(next);
        dirty.current = false;

        router.get(basePath, filtersToQuery(next, connectorOptions !== undefined), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        apply({});
    }

    function resetFilters() {
        setDraft(DEFAULTS);
        dirty.current = false;
        router.get(basePath, {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    // Typed but not yet applied — the draft is state, so this stays in sync.
    const searchPending = draft.q !== filters.q;

    const hasActiveFilters =
        filters.q !== '' ||
        filters.connector !== '' ||
        filters.library !== '' ||
        filters.kind !== '' ||
        filters.status !== 'present' ||
        filters.sort !== 'title' ||
        filters.direction !== 'asc' ||
        draft.q !== '';

    return (
        <form className="mf-panel flex flex-col gap-3 p-4" onSubmit={submitSearch} role="search">
            <div className="flex flex-wrap items-center gap-3">
                <label className="relative flex-1 basis-64">
                    <span className="sr-only">Search titles</span>
                    <SearchIcon className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-fg-subtle" />
                    <input
                        aria-label="Search titles"
                        className={`${CONTROL} w-full pl-9`}
                        name="q"
                        onChange={(event) => {
                            dirty.current = true;
                            setDraft((current) => ({ ...current, q: event.target.value }));
                        }}
                        placeholder="Search titles, then press Enter…"
                        type="search"
                        value={draft.q}
                    />
                </label>

                <button className="mf-button mf-button-secondary px-4 py-2 text-sm" type="submit">
                    Search
                </button>

                {connectorOptions && (
                    <label className="flex items-center gap-2 text-sm">
                        <span className="sr-only">Connector</span>
                        <select
                            aria-label="Filter by connector"
                            className={CONTROL}
                            onChange={(event) => apply({ connector: event.target.value, library: '' })}
                            value={draft.connector}
                        >
                            <option value="">All connectors</option>
                            {connectorOptions.map((connector) => (
                                <option key={connector.key} value={connector.key}>
                                    {connector.label}
                                </option>
                            ))}
                        </select>
                    </label>
                )}

                {libraryOptions && libraryOptions.length > 0 && (
                    <label className="flex items-center gap-2 text-sm">
                        <span className="sr-only">Library</span>
                        <select
                            aria-label="Filter by library"
                            className={CONTROL}
                            onChange={(event) => apply({ library: event.target.value })}
                            value={draft.library}
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

                <label className="flex items-center gap-2 text-sm">
                    <span className="sr-only">Media kind</span>
                    <select
                        aria-label="Filter by media kind"
                        className={CONTROL}
                        onChange={(event) => apply({ kind: event.target.value })}
                        value={draft.kind}
                    >
                        <option value="">All kinds</option>
                        {kinds.map((kind) => (
                            <option key={kind} value={kind}>
                                {mediaKindLabel(kind)}
                            </option>
                        ))}
                    </select>
                </label>

                <label className="flex items-center gap-2 text-sm">
                    <span className="sr-only">Presence</span>
                    <select
                        aria-label="Filter by presence"
                        className={CONTROL}
                        onChange={(event) => apply({ status: event.target.value })}
                        value={draft.status}
                    >
                        {STATUS_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <div className="flex flex-wrap items-center gap-3 border-t border-[var(--panel-border)] pt-3">
                <span className="text-xs uppercase tracking-wide text-fg-subtle">Sort</span>
                <select aria-label="Sort by" className={CONTROL} onChange={(event) => apply({ sort: event.target.value })} value={draft.sort}>
                    {SORT_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <button
                    aria-label={`Sort ${draft.direction === 'asc' ? 'ascending' : 'descending'}`}
                    className={`${CONTROL} min-w-[6.5rem] text-left`}
                    onClick={() => apply({ direction: draft.direction === 'asc' ? 'desc' : 'asc' })}
                    type="button"
                >
                    {draft.direction === 'asc' ? '↑ Ascending' : '↓ Descending'}
                </button>

                {searchPending && <span className="text-xs text-fg-subtle">Press Enter to search</span>}

                {hasActiveFilters && (
                    <button className="ml-auto text-sm text-accent underline-offset-2 hover:underline" onClick={resetFilters} type="button">
                        Reset filters
                    </button>
                )}
            </div>
        </form>
    );
}
