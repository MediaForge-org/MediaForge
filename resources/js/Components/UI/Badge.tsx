import type { ReactNode } from 'react';

export type BadgeTone = 'neutral' | 'success' | 'warning' | 'error' | 'info' | 'accent';

const TONES: Record<BadgeTone, { chip: string; dot: string }> = {
    neutral: { chip: 'bg-fg/5 text-fg-muted ring-1 ring-inset ring-fg/10', dot: 'bg-fg-subtle' },
    success: { chip: 'bg-success/12 text-success ring-1 ring-inset ring-success/25', dot: 'bg-success' },
    warning: { chip: 'bg-warning/12 text-warning ring-1 ring-inset ring-warning/25', dot: 'bg-warning' },
    error: { chip: 'bg-error/12 text-error ring-1 ring-inset ring-error/25', dot: 'bg-error' },
    info: { chip: 'bg-info/12 text-info ring-1 ring-inset ring-info/25', dot: 'bg-info' },
    accent: { chip: 'bg-accent/12 text-accent ring-1 ring-inset ring-accent/25', dot: 'bg-accent' },
};

interface BadgeProps {
    children: ReactNode;
    tone?: BadgeTone;
    dot?: boolean;
    className?: string;
}

export default function Badge({ children, tone = 'neutral', dot = false, className = '' }: BadgeProps) {
    const meta = TONES[tone];

    return (
        <span
            className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${meta.chip} ${className}`}
        >
            {dot && <span className={`size-1.5 rounded-full ${meta.dot}`} />}
            {children}
        </span>
    );
}
