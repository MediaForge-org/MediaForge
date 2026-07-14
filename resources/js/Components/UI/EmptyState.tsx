import type { ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    description?: ReactNode;
    icon?: ReactNode;
    action?: ReactNode;
    className?: string;
}

/** Friendly placeholder for lists/areas that have no content yet. */
export default function EmptyState({ title, description, icon, action, className = '' }: EmptyStateProps) {
    return (
        <div
            className={`flex flex-col items-center justify-center rounded-[--radius-md] border border-dashed border-line bg-surface-sunken/50 px-6 py-10 text-center ${className}`}
        >
            {icon && (
                <div className="mb-3 grid size-11 place-items-center rounded-full bg-surface-raised text-fg-muted shadow-sm">
                    {icon}
                </div>
            )}
            <p className="font-medium text-fg">{title}</p>
            {description && <p className="mt-1 max-w-sm text-sm text-fg-muted">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
