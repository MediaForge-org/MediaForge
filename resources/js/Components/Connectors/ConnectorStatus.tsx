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
