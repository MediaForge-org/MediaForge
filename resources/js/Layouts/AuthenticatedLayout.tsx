import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import NavLink from '@/Components/NavLink';
import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { ConnectorsIcon, DashboardIcon, LibraryIcon, ServerIcon, SettingsIcon } from '@/Components/UI/Icon';
import PresetSelector from '@/Components/UI/PresetSelector';
import ThemeToggle from '@/Components/UI/ThemeToggle';

interface SharedPageProps {
    [key: string]: unknown;
    auth: { user: { email: string; name: string } | null };
}

const NAV = [
    { href: '/dashboard', label: 'Dashboard', icon: <DashboardIcon className="size-4" /> },
    { href: '/connectors', label: 'Connectors', icon: <ConnectorsIcon className="size-4" /> },
    { href: '/settings', label: 'Settings', icon: <SettingsIcon className="size-4" /> },
];

const FUTURE = [
    { label: 'Jellyfin', icon: <ServerIcon className="size-4" /> },
    { label: 'Audiobookshelf', icon: <ServerIcon className="size-4" /> },
    { label: 'Library Overview', icon: <LibraryIcon className="size-4" /> },
    { label: 'Review Tasks', icon: <DashboardIcon className="size-4" /> },
];

function breadcrumb(url: string): [string, string] {
    if (url.startsWith('/connectors/jellyfin')) return ['Connectors', 'Jellyfin'];
    if (url.startsWith('/connectors/audiobookshelf')) return ['Connectors', 'Audiobookshelf'];
    if (url.startsWith('/connectors')) return ['Connectors', 'Providers'];
    if (url.startsWith('/settings')) return ['Settings', 'Foundation'];
    return ['Dashboard', 'Local workspace'];
}

function Brand({ subtitle = true }: { subtitle?: boolean }) {
    return (
        <Link className="flex items-center gap-3" href="/dashboard">
            <span className="mf-orb size-10 rounded-[--radius-md] text-base font-bold">M</span>
            <span>
                <span className="mf-gradient-text block text-lg font-semibold tracking-tight">MediaForge</span>
                {subtitle && <span className="block text-xs text-fg-subtle">Local Media OS</span>}
            </span>
        </Link>
    );
}

export default function AuthenticatedLayout({ children }: { children: ReactNode }) {
    const form = useForm<Record<string, never>>({});
    const { auth } = usePage<SharedPageProps>().props;
    const { url } = usePage();
    const [crumbA, crumbB] = breadcrumb(url);
    const initial = auth.user?.name?.charAt(0).toUpperCase() ?? 'M';

    function logout(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/logout');
    }

    const NavList = () =>
        NAV.map((item) => (
            <NavLink active={url.startsWith(item.href)} href={item.href} icon={item.icon} key={item.href}>
                {item.label}
            </NavLink>
        ));

    return (
        <div className="app-shell">
            <aside className="app-sidebar">
                <div className="flex h-24 items-center border-b border-[var(--sidebar-border)] px-6">
                    <Brand />
                </div>

                <nav aria-label="Primary navigation" className="flex flex-1 flex-col gap-7 overflow-y-auto p-4">
                    <div>
                        <p className="px-3 text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-fg-subtle">Workspace</p>
                        <div className="mt-2 grid gap-1">
                            <NavList />
                        </div>
                    </div>

                    <div>
                        <p className="px-3 text-[0.7rem] font-semibold uppercase tracking-[0.16em] text-fg-subtle">Future engine areas</p>
                        <div aria-label="Planned MediaForge areas" className="mt-2 grid gap-1">
                            {FUTURE.map((item) => (
                                <span
                                    aria-disabled="true"
                                    className="flex cursor-not-allowed items-center justify-between rounded-[--radius-md] px-3 py-2 text-sm text-fg-subtle"
                                    key={item.label}
                                >
                                    <span className="flex items-center gap-3">
                                        <span className="opacity-60">{item.icon}</span>
                                        {item.label}
                                    </span>
                                    <Badge tone="neutral">Later</Badge>
                                </span>
                            ))}
                        </div>
                    </div>
                </nav>

                <div className="border-t border-[var(--sidebar-border)] p-4">
                    {auth.user && (
                        <div className="mf-panel mb-3 flex items-center gap-3 p-3">
                            <span className="mf-orb size-9 rounded-full text-sm font-semibold">{initial}</span>
                            <div className="min-w-0 text-sm">
                                <p className="truncate font-medium">{auth.user.name}</p>
                                <p className="truncate text-xs text-fg-muted">{auth.user.email}</p>
                            </div>
                        </div>
                    )}
                    <form onSubmit={logout}>
                        <Button className="w-full" loading={form.processing} type="submit" variant="secondary">
                            Sign out
                        </Button>
                    </form>
                </div>
            </aside>

            <div className="app-main">
                <header className="app-topbar mf-desktop-only">
                    <div className="flex items-center gap-2 text-sm">
                        <span className="font-semibold">{crumbA}</span>
                        <span className="text-fg-subtle">/</span>
                        <span className="text-fg-muted">{crumbB}</span>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <span className="mf-status-pill mf-desktop-only">
                            <span className="size-2 rounded-full bg-success shadow-[0_0_8px_rgb(var(--status-success))]" />
                            Local runtime online
                        </span>
                        <Badge tone="accent">V1</Badge>
                        <ThemeToggle />
                        <PresetSelector />
                    </div>
                </header>

                <header className="mf-mobile-topbar mf-mobile-only">
                    <div className="flex items-center justify-between gap-3 px-4 py-3">
                        <Brand subtitle={false} />
                        <div className="flex items-center gap-2">
                            <ThemeToggle />
                            <PresetSelector />
                        </div>
                    </div>
                    <nav aria-label="Primary navigation" className="flex gap-1 overflow-x-auto px-4 pb-3">
                        <NavList />
                    </nav>
                </header>

                <main className="app-content">{children}</main>
            </div>
        </div>
    );
}
