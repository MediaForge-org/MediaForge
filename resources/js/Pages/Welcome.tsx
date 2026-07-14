import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';

import Badge from '@/Components/UI/Badge';
import { buttonClasses } from '@/Components/UI/Button';
import { ConnectorsIcon, LibraryIcon, SettingsIcon, ShieldIcon } from '@/Components/UI/Icon';

interface WelcomeProps {
    version: string;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: { user?: { id: string } | null };
}

const CAPABILITIES: { title: string; description: string; icon: ReactNode }[] = [
    { title: 'Local-first', description: 'Everything runs on your own machine — no cloud dependency.', icon: <ShieldIcon className="size-5" /> },
    { title: 'Secret-safe connectors', description: 'API tokens are encrypted and never rendered.', icon: <ConnectorsIcon className="size-5" /> },
    { title: 'Library discovery', description: 'Discover the libraries each server exposes.', icon: <LibraryIcon className="size-5" /> },
    { title: 'Future engine mode', description: 'Connectors can evolve into integrated engines later.', icon: <SettingsIcon className="size-5" /> },
];

const READY_CHIPS = ['Auth ready', 'Connectors ready', 'Library discovery ready', 'Settings foundation'];

export default function Welcome({ version }: WelcomeProps) {
    const { auth } = usePage<SharedPageProps>().props;

    return (
        <>
            <Head title="Welcome" />

            <main className="mx-auto flex min-h-screen w-full max-w-6xl flex-col justify-center gap-10 px-6 py-16">
                <div className="grid items-center gap-8 lg:grid-cols-2">
                    <div className="mf-rise flex flex-col gap-6">
                        <div className="flex items-center gap-3">
                            <span className="mf-orb size-12 rounded-[--radius-lg] text-xl font-bold">M</span>
                            <span className="mf-gradient-text text-2xl font-semibold tracking-tight">MediaForge</span>
                        </div>
                        <span className="mf-status-pill w-fit">
                            <span className="size-1.5 rounded-full bg-accent" /> {version}
                        </span>
                        <h1 className="text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                            Build your local media <span className="mf-gradient-text">command center</span>
                        </h1>
                        <p className="max-w-lg text-lg text-fg-muted">
                            Authentication, connectors, library discovery and a settings foundation — a local control surface for
                            Jellyfin &amp; Audiobookshelf.
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {auth?.user ? (
                                <Link className={buttonClasses('primary')} href="/dashboard">Open dashboard</Link>
                            ) : (
                                <>
                                    <Link className={buttonClasses('primary')} href="/login">Sign in</Link>
                                    <Link className={buttonClasses('secondary')} href="/register">Create account</Link>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="mf-hero mf-rise p-7" style={{ '--mf-i': 1 } as CSSProperties}>
                        <div className="mf-glow -right-10 -top-10 size-52 bg-accent/30" />
                        <div className="relative">
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-fg-subtle">V1 Workspace</p>
                            <p className="mt-1 text-xl font-semibold">Foundation online</p>
                            <div className="mt-5 grid gap-2">
                                {READY_CHIPS.map((chip) => (
                                    <div className="mf-panel flex items-center justify-between px-4 py-3 text-sm" key={chip}>
                                        <span>{chip}</span>
                                        <Badge dot tone="success">Ready</Badge>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {CAPABILITIES.map((cap, i) => (
                        <div className="mf-card mf-rise p-5" key={cap.title} style={{ '--mf-i': i + 2 } as CSSProperties}>
                            <span className="grid size-11 place-items-center rounded-[--radius-md] bg-accent/10 text-accent ring-1 ring-inset ring-accent/20">
                                {cap.icon}
                            </span>
                            <p className="mt-3 font-semibold">{cap.title}</p>
                            <p className="mt-1 text-sm text-fg-muted">{cap.description}</p>
                        </div>
                    ))}
                </div>
            </main>
        </>
    );
}
