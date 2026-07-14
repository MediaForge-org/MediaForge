import Badge, { type BadgeTone } from '@/Components/UI/Badge';

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
