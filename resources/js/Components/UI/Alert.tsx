import type { ReactNode } from 'react';

import { AlertIcon, CheckIcon, InfoIcon } from '@/Components/UI/Icon';

export type AlertTone = 'success' | 'error' | 'info' | 'warning';

const TONES: Record<AlertTone, { wrap: string; icon: typeof InfoIcon }> = {
    success: { wrap: 'border-success/30 bg-success/10 text-success', icon: CheckIcon },
    error: { wrap: 'border-error/30 bg-error/10 text-error', icon: AlertIcon },
    warning: { wrap: 'border-warning/30 bg-warning/10 text-warning', icon: AlertIcon },
    info: { wrap: 'border-info/30 bg-info/10 text-info', icon: InfoIcon },
};

interface AlertProps {
    tone?: AlertTone;
    title?: string;
    children?: ReactNode;
    className?: string;
}

/** Inline status message for flash/success/error feedback. */
export default function Alert({ tone = 'info', title, children, className = '' }: AlertProps) {
    const meta = TONES[tone];
    const Icon = meta.icon;

    return (
        <div className={`flex items-start gap-3 rounded-[--radius-md] border px-4 py-3 text-sm ${meta.wrap} ${className}`} role="status">
            <Icon className="mt-0.5 size-4 shrink-0" />
            <div className="min-w-0">
                {title && <p className="font-semibold">{title}</p>}
                {children && <div className={title ? 'mt-0.5' : ''}>{children}</div>}
            </div>
        </div>
    );
}
