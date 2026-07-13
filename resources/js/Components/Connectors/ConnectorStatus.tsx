export type ConnectorStatus = 'not_configured' | 'unknown' | 'healthy' | 'unhealthy';

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

const STATUS_META: Record<ConnectorStatus, { label: string; className: string; dot: string }> = {
    not_configured: {
        label: 'Not configured',
        className: 'bg-surface-sunken text-fg-muted',
        dot: 'bg-fg-muted',
    },
    unknown: {
        label: 'Not checked',
        className: 'bg-surface-sunken text-fg-muted',
        dot: 'bg-fg-muted',
    },
    healthy: {
        label: 'Healthy',
        className: 'bg-success/10 text-success',
        dot: 'bg-success',
    },
    unhealthy: {
        label: 'Unhealthy',
        className: 'bg-error/10 text-error',
        dot: 'bg-error',
    },
};

export function StatusBadge({ status }: { status: ConnectorStatus }) {
    const meta = STATUS_META[status];

    return (
        <span
            className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${meta.className}`}
        >
            <span className={`size-2 rounded-full ${meta.dot}`} />
            {meta.label}
        </span>
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
