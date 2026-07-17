import { type CatalogFilters } from '@/Components/Connectors/ConnectorStatus';

/**
 * Serialize applied catalog filters into GET query params, dropping defaults so the
 * URL stays clean (present/title/asc are implicit). `includeConnector` is false on
 * connector/library pages where the connector is fixed by the route. This is the
 * single source of truth shared by the filter bar and the pagination links.
 */
export function filtersToQuery(filters: CatalogFilters, includeConnector: boolean): Record<string, string> {
    const params: Record<string, string> = {};

    if (filters.q) params.q = filters.q;
    if (includeConnector && filters.connector) params.connector = filters.connector;
    if (filters.library) params.library = filters.library;
    if (filters.kind) params.kind = filters.kind;
    if (filters.status && filters.status !== 'present') params.status = filters.status;
    if (filters.sort && filters.sort !== 'title') params.sort = filters.sort;
    if (filters.direction && filters.direction !== 'asc') params.direction = filters.direction;
    // V2 C normalization filters ('all'/'' are the implicit defaults).
    if (filters.normalization && filters.normalization !== 'all') params.normalization = filters.normalization;
    if (filters.issue) params.issue = filters.issue;
    if (filters.duplicates === '1') params.duplicates = '1';

    return params;
}
