import Badge, { type BadgeTone } from '@/Components/UI/Badge';

export type ConnectorStatus = 'not_configured' | 'unknown' | 'healthy' | 'unhealthy';

export type SyncStatus = 'not_ready' | 'ready_for_dry_run' | 'last_dry_run_completed' | 'attention_required';

export interface SyncIssue {
    code: string;
    message: string;
    action: string;
    blocking: boolean;
}

export interface SyncRunLibrary {
    external_id: string;
    name: string;
    type: string | null;
    status: 'planned' | 'skipped' | 'warning' | 'failed' | 'ready';
    planned_action: 'inspect_only' | 'future_sync_candidate' | 'skipped_not_selected' | 'skipped_missing';
}

export interface SyncRunSummary {
    discovered_count: number;
    selected_count: number;
    selected_present_count: number;
    selected_missing_count: number;
    ready_for_future_sync: boolean;
    issues: SyncIssue[];
    note: string;
}

export interface SyncRun {
    id: string;
    mode: string;
    status: 'pending' | 'running' | 'completed' | 'completed_with_warnings' | 'failed' | 'cancelled';
    started_at: string | null;
    finished_at: string | null;
    summary: SyncRunSummary;
    libraries?: SyncRunLibrary[];
}

export interface SyncFoundation {
    status: SyncStatus;
    selected_count: number;
    selected_present_count: number;
    selected_missing_count: number;
    discovered_count: number;
    open_review_count: number;
    last_run: SyncRun | null;
}

export type CatalogStatus = 'not_ready' | 'ready_for_snapshot' | 'last_snapshot_completed' | 'attention_required';

export type SnapshotRunStatus = 'pending' | 'running' | 'completed' | 'completed_with_warnings' | 'failed' | 'cancelled';

export type ExternalMediaKind =
    | 'movie'
    | 'series'
    | 'season'
    | 'episode'
    | 'audiobook'
    | 'book'
    | 'podcast'
    | 'music'
    | 'playlist'
    | 'folder'
    | 'unknown';

export interface CatalogIssue {
    code: string;
    message: string;
    action: string;
    blocking: boolean;
}

export interface SnapshotRunSummary {
    library_external_id: string;
    library_name: string;
    items_stored: number;
    items_seen: number;
    captured_count: number;
    remote_total: number;
    cap: number;
    truncated: boolean;
    http_status: number | null;
    outcome: string;
    issues: CatalogIssue[];
    note: string;
}

/** A connector reference echoed on runs/items ({ key, label }) or null when unknown. */
export interface ConnectorRef {
    key: string;
    label: string;
}

/** V2 C: the quality verdict of a normalized external item. */
export type NormalizationStatus = 'clean' | 'warning' | 'needs_review' | 'unsupported';

/** V2 C: one sanitized data-quality issue ({ code, message }). */
export interface NormalizationIssueView {
    code: string;
    message: string;
}

/** V2 C: the normalized read-model of one captured item. */
export interface ItemNormalization {
    kind: ExternalMediaKind;
    title: string;
    sort_title: string | null;
    release_year: number | null;
    season_number: number | null;
    episode_number: number | null;
    parent_title: string | null;
    runtime_seconds: number | null;
    confidence: number;
    status: NormalizationStatus;
    issues: NormalizationIssueView[];
}

/** V2 C: normalization aggregates for a scope (all / connector / library). */
export interface NormalizationSummary {
    normalized: number;
    clean: number;
    warning: number;
    needs_review: number;
    unsupported: number;
    unknown_kind: number;
    weak_metadata: number;
    duplicate_suspects: number;
}

/** One browsable captured external item (CatalogReadModel::itemView). */
export interface CatalogItemRow {
    id: string;
    title: string;
    media_kind: ExternalMediaKind;
    year: number | null;
    index_number: number | null;
    parent_index_number: number | null;
    runtime_seconds: number | null;
    connector: ConnectorRef | null;
    library_name: string | null;
    is_present: boolean;
    last_seen_at: string | null;
    /** null when the item predates normalization (not yet rebuilt). */
    normalization: ItemNormalization | null;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface CatalogItemsPage {
    data: CatalogItemRow[];
    meta: PaginationMeta;
}

/** The applied catalog filters, echoed back so the UI selects stay in sync. */
export interface CatalogFilters {
    q: string;
    connector: string;
    library: string;
    kind: string;
    status: string;
    sort: string;
    direction: string;
    /** V2 C: normalization verdict filter ('all' = unfiltered). */
    normalization: string;
    /** V2 C: a single normalization issue code ('' = unfiltered). */
    issue: string;
    /** V2 C: '1' = only duplicate suspects. */
    duplicates: string;
}

/** A library option for the catalog filter + connector page (with capture counts). */
export interface CatalogLibraryOption {
    id: string;
    name: string;
    type: string | null;
    connector: ConnectorRef | null;
    present_item_count: number;
    missing_item_count: number;
    is_enabled: boolean;
    discovery_status: 'present' | 'missing';
}

/** Scoped counts + runs for a single library catalog page (CatalogReadModel::libraryScope). */
export interface CatalogLibraryScope {
    external_item_count: number;
    present_item_count: number;
    missing_item_count: number;
    snapshot_run_count: number;
    last_run: SnapshotRun | null;
    latest_runs: LatestSnapshotRun[];
}

/** A row in the "latest snapshot runs" lists (CatalogReadModel::latestRuns). */
export interface LatestSnapshotRun {
    id: string;
    status: SnapshotRunStatus;
    connector: ConnectorRef | null;
    library_name: string | null;
    items_stored_count: number;
    items_seen_count: number;
    warnings_count: number;
    errors_count: number;
    finished_at: string | null;
}

export interface SnapshotRun {
    id: string;
    status: SnapshotRunStatus;
    started_at: string | null;
    finished_at: string | null;
    items_seen_count: number;
    items_stored_count: number;
    warnings_count: number;
    errors_count: number;
    summary: SnapshotRunSummary;
}

/** Per-library capture counts, keyed by connector_library_id. */
export interface CatalogLibraryCapture {
    external_item_count: number;
    last_seen_at: string | null;
}

export interface CatalogFoundation {
    status: CatalogStatus;
    external_item_count: number;
    present_item_count: number;
    missing_item_count: number;
    snapshot_run_count: number;
    open_review_count: number;
    last_run: SnapshotRun | null;
    libraries: Record<string, CatalogLibraryCapture>;
}

export interface ConnectorSummary {
    key: string;
    label: string;
    base_url: string;
    configured: boolean;
    secret_configured: boolean;
    status: ConnectorStatus;
    health_status: string;
    health_detail: string | null;
    last_checked_at: string | null;
    last_healthy_at: string | null;
    library_count: number;
    libraries_discovered_at: string | null;
    last_discovery_error: string | null;
    sync: SyncFoundation;
    catalog: CatalogFoundation;
}

export interface DiscoveredLibrary {
    id: string;
    external_id: string;
    name: string;
    type: string | null;
    path: string | null;
    is_enabled: boolean;
    discovery_status: 'present' | 'missing';
    last_seen_at: string | null;
}

export interface ConnectorDetail extends ConnectorSummary {
    libraries: DiscoveredLibrary[];
}

const STATUS_META: Record<ConnectorStatus, { label: string; tone: BadgeTone }> = {
    not_configured: { label: 'Not configured', tone: 'neutral' },
    unknown: { label: 'Not checked', tone: 'neutral' },
    healthy: { label: 'Healthy', tone: 'success' },
    unhealthy: { label: 'Unhealthy', tone: 'error' },
};

export function StatusBadge({ status }: { status: ConnectorStatus }) {
    const meta = STATUS_META[status];

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

export function formatCheckedAt(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? 'Never' : date.toLocaleString();
}

export function discoverySummary(connector: ConnectorSummary): string {
    if (!connector.libraries_discovered_at) {
        return 'Not discovered';
    }

    const count = connector.library_count;

    return `${count} ${count === 1 ? 'library' : 'libraries'} discovered`;
}

const SYNC_STATUS_META: Record<SyncStatus, { label: string; tone: BadgeTone }> = {
    not_ready: { label: 'Not ready', tone: 'neutral' },
    ready_for_dry_run: { label: 'Ready for dry run', tone: 'accent' },
    last_dry_run_completed: { label: 'Last dry run completed', tone: 'success' },
    attention_required: { label: 'Attention required', tone: 'error' },
};

export function SyncStatusBadge({ status }: { status: SyncStatus }) {
    const meta = SYNC_STATUS_META[status];

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

const RUN_STATUS_LABEL: Record<SyncRun['status'], string> = {
    pending: 'Pending',
    running: 'Running',
    completed: 'Dry run completed',
    completed_with_warnings: 'Dry run completed with warnings',
    failed: 'Dry run failed',
    cancelled: 'Cancelled',
};

export function runStatusLabel(status: SyncRun['status']): string {
    return RUN_STATUS_LABEL[status];
}

const PLANNED_ACTION_LABEL: Record<SyncRunLibrary['planned_action'], string> = {
    inspect_only: 'Inspect only',
    future_sync_candidate: 'Future sync candidate',
    skipped_not_selected: 'Skipped — not selected',
    skipped_missing: 'Skipped — missing',
};

export function plannedActionLabel(action: SyncRunLibrary['planned_action']): string {
    return PLANNED_ACTION_LABEL[action];
}

const CATALOG_STATUS_META: Record<CatalogStatus, { label: string; tone: BadgeTone }> = {
    not_ready: { label: 'Not ready', tone: 'neutral' },
    ready_for_snapshot: { label: 'Ready for snapshot', tone: 'accent' },
    last_snapshot_completed: { label: 'Last snapshot completed', tone: 'success' },
    attention_required: { label: 'Attention required', tone: 'error' },
};

export function CatalogStatusBadge({ status }: { status: CatalogStatus }) {
    const meta = CATALOG_STATUS_META[status];

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

const SNAPSHOT_STATUS_LABEL: Record<SnapshotRunStatus, string> = {
    pending: 'Pending',
    running: 'Running',
    completed: 'Snapshot completed',
    completed_with_warnings: 'Snapshot completed with warnings',
    failed: 'Snapshot failed',
    cancelled: 'Cancelled',
};

export function snapshotStatusLabel(status: SnapshotRunStatus): string {
    return SNAPSHOT_STATUS_LABEL[status];
}

const MEDIA_KIND_LABEL: Record<ExternalMediaKind, string> = {
    movie: 'Movie',
    series: 'Series',
    season: 'Season',
    episode: 'Episode',
    audiobook: 'Audiobook',
    book: 'Book',
    podcast: 'Podcast',
    music: 'Music',
    playlist: 'Playlist',
    folder: 'Folder',
    unknown: 'Unknown',
};

export function mediaKindLabel(kind: ExternalMediaKind): string {
    return MEDIA_KIND_LABEL[kind] ?? 'Unknown';
}

const NORMALIZATION_STATUS_META: Record<NormalizationStatus, { label: string; tone: BadgeTone }> = {
    clean: { label: 'Clean', tone: 'success' },
    warning: { label: 'Warning', tone: 'warning' },
    needs_review: { label: 'Needs review', tone: 'error' },
    unsupported: { label: 'Not media', tone: 'neutral' },
};

export function NormalizationStatusBadge({ status }: { status: NormalizationStatus }) {
    const meta = NORMALIZATION_STATUS_META[status];

    return (
        <Badge dot tone={meta.tone}>
            {meta.label}
        </Badge>
    );
}

export function normalizationStatusLabel(status: NormalizationStatus): string {
    return NORMALIZATION_STATUS_META[status]?.label ?? 'Unknown';
}

/** Turn a normalization issue code into a readable label ("unknown_kind" → "Unknown kind"). */
export function issueLabel(code: string): string {
    const text = code.replace(/_/g, ' ');

    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Compact "S02E05" style label, or null when there is nothing to show. */
export function episodeLabel(season: number | null, episode: number | null): string | null {
    if (season === null && episode === null) {
        return null;
    }

    const seasonPart = season !== null ? `S${String(season).padStart(2, '0')}` : '';
    const episodePart = episode !== null ? `E${String(episode).padStart(2, '0')}` : '';

    return `${seasonPart}${episodePart}`;
}

/** Format a runtime in seconds as a compact "1h 24m" / "48m" string. */
export function formatRuntime(seconds: number | null): string | null {
    if (!seconds || seconds <= 0) {
        return null;
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.round((seconds % 3600) / 60);

    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
}
