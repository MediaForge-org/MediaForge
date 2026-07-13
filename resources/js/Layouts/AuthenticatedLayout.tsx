import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import NavLink from '@/Components/NavLink';

interface SharedPageProps {
    [key: string]: unknown;
    auth: {
        user: {
            email: string;
            name: string;
        } | null;
    };
}

interface AuthenticatedLayoutProps {
    children: ReactNode;
}

export default function AuthenticatedLayout({ children }: AuthenticatedLayoutProps) {
    const form = useForm<Record<string, never>>({});
    const { auth } = usePage<SharedPageProps>().props;
    const { url } = usePage();

    function logout(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/logout');
    }

    return (
        <div className="min-h-screen bg-surface text-fg lg:flex">
            <aside className="hidden w-72 shrink-0 border-r border-line bg-surface-raised lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
                <div className="border-b border-line p-6">
                    <Link className="flex items-center gap-3 font-semibold tracking-tight" href="/dashboard">
                        <span className="grid size-10 place-items-center rounded-[--radius-md] bg-accent text-base font-bold text-on-accent">
                            M
                        </span>
                        <span>
                            <span className="block text-lg">MediaForge</span>
                            <span className="block text-xs font-normal text-fg-muted">Local media workspace</span>
                        </span>
                    </Link>
                </div>

                <nav aria-label="Primary navigation" className="flex flex-1 flex-col gap-7 p-4">
                    <div>
                        <p className="px-3 text-xs font-semibold uppercase tracking-[0.16em] text-fg-muted">Workspace</p>
                        <div className="mt-2 grid gap-1">
                            <NavLink active={url.startsWith('/dashboard')} href="/dashboard">
                                Dashboard
                            </NavLink>
                            <NavLink active={url.startsWith('/connectors')} href="/connectors">
                                Connectors
                            </NavLink>
                            <NavLink active={url.startsWith('/settings')} href="/settings">
                                Settings
                            </NavLink>
                        </div>
                    </div>

                    <div>
                        <p className="px-3 text-xs font-semibold uppercase tracking-[0.16em] text-fg-muted">Coming soon</p>
                        <div className="mt-2 grid gap-1" aria-label="Planned MediaForge areas">
                            {['Library Overview', 'Review Tasks'].map((label) => (
                                <span
                                    aria-disabled="true"
                                    className="flex cursor-not-allowed items-center justify-between rounded-[--radius-sm] px-3 py-2 text-sm text-fg-muted opacity-70"
                                    key={label}
                                >
                                    {label}
                                    <span className="rounded-full bg-surface-sunken px-2 py-0.5 text-[0.65rem] font-medium uppercase tracking-wide">
                                        Later V1
                                    </span>
                                </span>
                            ))}
                        </div>
                    </div>
                </nav>

                <div className="border-t border-line p-4">
                    {auth.user && (
                        <div className="mb-4 rounded-[--radius-md] bg-surface-sunken p-3 text-sm">
                            <p className="truncate font-medium">{auth.user.name}</p>
                            <p className="mt-1 truncate text-fg-muted">{auth.user.email}</p>
                        </div>
                    )}
                    <form onSubmit={logout}>
                        <button
                            className="w-full rounded-[--radius-sm] border border-line px-3 py-2 text-sm font-medium transition-colors hover:bg-surface-sunken disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={form.processing}
                            type="submit"
                        >
                            Sign out
                        </button>
                    </form>
                </div>
            </aside>

            <div className="min-w-0 flex-1">
                <header className="border-b border-line bg-surface-raised lg:hidden">
                    <div className="flex flex-col gap-3 px-5 py-4">
                        <div className="flex items-center justify-between gap-4">
                            <Link className="flex items-center gap-2 font-semibold tracking-tight" href="/dashboard">
                                <span className="grid size-8 place-items-center rounded-[--radius-sm] bg-accent text-sm font-bold text-on-accent">
                                    M
                                </span>
                                MediaForge
                            </Link>
                            <form onSubmit={logout}>
                                <button
                                    className="rounded-[--radius-sm] border border-line px-3 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-60"
                                    disabled={form.processing}
                                    type="submit"
                                >
                                    Sign out
                                </button>
                            </form>
                        </div>
                        <nav aria-label="Primary navigation" className="flex gap-1 overflow-x-auto">
                            <NavLink active={url.startsWith('/dashboard')} href="/dashboard">
                                Dashboard
                            </NavLink>
                            <NavLink active={url.startsWith('/connectors')} href="/connectors">
                                Connectors
                            </NavLink>
                            <NavLink active={url.startsWith('/settings')} href="/settings">
                                Settings
                            </NavLink>
                        </nav>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-7xl px-5 py-7 sm:px-8 sm:py-10">{children}</main>
            </div>
        </div>
    );
}
