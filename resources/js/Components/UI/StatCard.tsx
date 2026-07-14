import type { ReactNode } from 'react';

export type StatTone = 'neutral' | 'success' | 'warning' | 'error' | 'accent';

const DOT: Record<StatTone, string> = {
    neutral: 'bg-fg-subtle',
    success: 'bg-success',
    warning: 'bg-warning',
    error: 'bg-error',
    accent: 'bg-accent',
};

const HINT_TEXT: Record<StatTone, string> = {
    neutral: 'text-fg-muted',
    success: 'text-success',
    warning: 'text-warning',
    error: 'text-error',
    accent: 'text-accent',
};

interface StatCardProps {
    label: string;
    value: string;
    hint?: string;
    tone?: StatTone;
    icon?: ReactNode;
}

/** Compact metric tile for the dashboard status grid. */
export default function StatCard({ label, value, hint, tone = 'neutral', icon }: StatCardProps) {
    return (
        <div className="mf-card overflow-hidden p-5">
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-medium text-fg-muted">{label}</p>
                {icon && (
                    <span className="grid size-9 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-accent/20">
                        {icon}
                    </span>
                )}
            </div>
            <p className="mt-3 text-2xl font-semibold tracking-tight text-fg">{value}</p>
            {hint && (
                <p className={`mt-1.5 flex items-center gap-2 text-sm ${HINT_TEXT[tone]}`}>
                    {tone !== 'neutral' && <span className={`size-2 rounded-full ${DOT[tone]} shadow-[0_0_8px_currentColor]`} />}
                    {hint}
                </p>
            )}
        </div>
    );
}
