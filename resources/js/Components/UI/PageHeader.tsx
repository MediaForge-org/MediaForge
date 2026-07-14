import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { ArrowLeftIcon } from '@/Components/UI/Icon';

interface PageHeaderProps {
    title: string;
    eyebrow?: string;
    description?: ReactNode;
    actions?: ReactNode;
    backHref?: string;
    backLabel?: string;
}

/** The consistent top-of-page title block used on every authenticated page. */
export default function PageHeader({ title, eyebrow, description, actions, backHref, backLabel }: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-4">
            {backHref && (
                <Link
                    className="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-fg-muted transition-colors hover:text-fg"
                    href={backHref}
                >
                    <ArrowLeftIcon className="size-4" />
                    {backLabel ?? 'Back'}
                </Link>
            )}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0">
                    {eyebrow && (
                        <span className="inline-flex items-center gap-2 rounded-full bg-accent/10 px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-accent ring-1 ring-inset ring-accent/20">
                            {eyebrow}
                        </span>
                    )}
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight text-fg sm:text-4xl">{title}</h1>
                    {description && <p className="mt-3 max-w-2xl text-sm text-fg-muted sm:text-base">{description}</p>}
                </div>
                {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
            </div>
        </div>
    );
}
