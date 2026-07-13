import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface NavLinkProps {
    active: boolean;
    children: ReactNode;
    href: string;
}

export default function NavLink({ active, children, href }: NavLinkProps) {
    return (
        <Link
            aria-current={active ? 'page' : undefined}
            className={`rounded-[--radius-sm] px-3 py-2 text-sm font-medium transition-colors ${
                active
                    ? 'bg-surface-sunken text-fg'
                    : 'text-fg-muted hover:bg-surface-sunken hover:text-fg'
            }`}
            href={href}
        >
            {children}
        </Link>
    );
}
