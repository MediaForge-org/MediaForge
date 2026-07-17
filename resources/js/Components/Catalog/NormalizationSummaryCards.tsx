import { Link } from '@inertiajs/react';

import { type NormalizationSummary } from '@/Components/Connectors/ConnectorStatus';

interface NormalizationSummaryCardsProps {
    summary: NormalizationSummary;
    /** Where each card links to, so a count is always explorable. */
    basePath: string;
    /** Extra query params to preserve (e.g. the connector/library scope). */
    scope?: Record<string, string>;
}

function href(basePath: string, scope: Record<string, string>, extra: Record<string, string>): string {
    const params = new URLSearchParams({ ...scope, ...extra });
    const qs = params.toString();

    return qs === '' ? basePath : `${basePath}?${qs}`;
}

/**
 * V2 C normalization aggregates for a scope. Every card is a link into the item
 * list filtered to exactly that count, so a number is never a dead end. Read-only:
 * these are counts over the stored normalization read-model.
 */
export default function NormalizationSummaryCards({ summary, basePath, scope = {} }: NormalizationSummaryCardsProps) {
    const cards: { label: string; value: number; hint: string; query: Record<string, string> }[] = [
        {
            label: 'Normalized items',
            value: summary.normalized,
            hint: 'Interpreted, read-only',
            query: { status: 'all' },
        },
        {
            label: 'Items with warnings',
            value: summary.warning + summary.needs_review,
            hint: 'Something is missing or odd',
            query: { status: 'all', normalization: 'warning' },
        },
        {
            label: 'Duplicate suspects',
            value: summary.duplicate_suspects,
            hint: 'Share a normalized identity',
            query: { status: 'all', duplicates: '1' },
        },
        {
            label: 'Unknown kind',
            value: summary.unknown_kind,
            hint: 'Could not be classified',
            query: { status: 'all', issue: 'unknown_kind' },
        },
        {
            label: 'Weak metadata',
            value: summary.weak_metadata,
            hint: 'Too thin to interpret',
            query: { status: 'all', issue: 'weak_metadata' },
        },
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            {cards.map((card) => (
                <Link
                    className="mf-card p-5 transition-colors hover:border-accent/40"
                    href={href(basePath, scope, card.query)}
                    key={card.label}
                >
                    <p className="text-sm font-medium text-fg-muted">{card.label}</p>
                    <p className="mt-3 text-2xl font-semibold tracking-tight">{card.value}</p>
                    <p className="mt-1.5 text-sm text-fg-subtle">{card.hint}</p>
                </Link>
            ))}
        </div>
    );
}
