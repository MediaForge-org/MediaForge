import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface NavLinkProps {
    active: boolean;
    children: ReactNode;
    href: string;
    icon?: ReactNode;
}

export default function NavLink({ active, children, href, icon }: NavLinkProps) {
    return (
        <Link aria-current={active ? 'page' : undefined} className="mf-nav-item" href={href}>
            {icon && <span className={active ? 'text-accent' : 'text-fg-subtle'}>{icon}</span>}
            <span className="flex-1">{children}</span>
        </Link>
    );
}
