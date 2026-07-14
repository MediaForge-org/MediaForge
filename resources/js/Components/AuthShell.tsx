import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import Badge from '@/Components/UI/Badge';

interface AuthShellProps {
    title: string;
    subtitle: string;
    children: ReactNode;
    footer: ReactNode;
}

const CHIPS = ['Auth ready', 'Connectors ready', 'Library discovery', 'Settings foundation'];

/** Two-column branded auth layout shared by Login and Register. */
export default function AuthShell({ title, subtitle, children, footer }: AuthShellProps) {
    return (
        <main className="mx-auto flex min-h-screen w-full max-w-5xl items-center px-6 py-10">
            <div className="grid w-full items-stretch gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <div className="mf-hero mf-rise hidden flex-col justify-between p-8 lg:flex">
                    <div className="mf-glow -left-10 -top-10 size-60 bg-accent/25" />
                    <div className="relative flex items-center gap-3">
                        <span className="mf-orb size-11 rounded-[--radius-md] text-lg font-bold">M</span>
                        <span className="mf-gradient-text text-xl font-semibold tracking-tight">MediaForge</span>
                    </div>
                    <div className="relative">
                        <h2 className="text-3xl font-semibold leading-tight tracking-tight">
                            Your local media <span className="mf-gradient-text">command center</span>
                        </h2>
                        <div className="mt-5 flex flex-wrap gap-2">
                            {CHIPS.map((chip) => (
                                <Badge dot key={chip} tone="success">{chip}</Badge>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="mf-rise flex flex-col justify-center">
                    <div className="mf-panel p-7">
                        <Link className="mb-6 flex items-center gap-2.5 lg:hidden" href="/">
                            <span className="mf-orb size-9 rounded-[--radius-md] text-sm font-bold">M</span>
                            <span className="mf-gradient-text text-lg font-semibold">MediaForge</span>
                        </Link>
                        <div className="mb-6">
                            <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                            <p className="mt-1.5 text-sm text-fg-muted">{subtitle}</p>
                        </div>
                        {children}
                    </div>
                    <div className="mt-6 flex flex-col items-center gap-2 text-sm text-fg-muted">{footer}</div>
                </div>
            </div>
        </main>
    );
}
