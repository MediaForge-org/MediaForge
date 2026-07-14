import type { ReactNode } from 'react';

interface SectionHeaderProps {
    title: string;
    description?: ReactNode;
    action?: ReactNode;
}

/** Heading for a section within a page (smaller than PageHeader). */
export default function SectionHeader({ title, description, action }: SectionHeaderProps) {
    return (
        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div className="min-w-0">
                <h2 className="text-lg font-semibold tracking-tight text-fg">{title}</h2>
                {description && <p className="mt-1 text-sm text-fg-muted">{description}</p>}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}
