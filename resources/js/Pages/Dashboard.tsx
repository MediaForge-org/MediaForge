import { Head, usePage } from '@inertiajs/react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface DashboardPageProps {
    [key: string]: unknown;
    status: string;
}

const upcomingAreas = [
    ['Jellyfin Connector', 'Coming in a later V1 package.'],
    ['Audiobookshelf Connector', 'Coming in a later V1 package.'],
    ['Library Health', 'Coming later.'],
    ['Review Tasks', 'Coming later.'],
];

export default function Dashboard() {
    const { status } = usePage<DashboardPageProps>().props;

    return (
        <>
            <Head title="Dashboard" />

            <AuthenticatedLayout>
                <section className="flex flex-col gap-8">
                    <div className="rounded-[--radius-md] border border-line bg-surface-raised p-6 shadow-sm sm:p-8">
                        <div>
                            <p className="text-sm font-medium text-accent">{status}</p>
                            <h1 className="mt-1 text-3xl font-semibold tracking-tight sm:text-4xl">Your MediaForge workspace</h1>
                            <p className="mt-2 max-w-2xl text-fg-muted">
                                A local control surface for your media ecosystem. The foundation is live; integrations and operational workflows are deliberately staged for later V1 packages.
                            </p>
                        </div>
                    </div>

                    <section aria-label="Foundation status">
                        <div className="mb-4">
                            <h2 className="text-xl font-semibold tracking-tight">Foundation status</h2>
                            <p className="mt-1 text-sm text-fg-muted">A quick view of the local app before connector packages are enabled.</p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <article className="rounded-[--radius-md] border border-line bg-surface-raised p-5 shadow-sm">
                                <p className="text-sm text-fg-muted">V1 Foundation Status</p>
                                <p className="mt-2 text-lg font-semibold">Ready</p>
                                <p className="mt-1 flex items-center gap-2 text-sm text-success">
                                    <span className="size-2 rounded-full bg-success" /> Core shell is available
                                </p>
                            </article>
                            <article className="rounded-[--radius-md] border border-line bg-surface-raised p-5 shadow-sm">
                                <p className="text-sm text-fg-muted">Auth Status</p>
                                <p className="mt-2 text-lg font-semibold">Authenticated</p>
                                <p className="mt-1 flex items-center gap-2 text-sm text-success">
                                    <span className="size-2 rounded-full bg-success" /> Local session active
                                </p>
                            </article>
                            <article className="rounded-[--radius-md] border border-line bg-surface-raised p-5 shadow-sm">
                                <p className="text-sm text-fg-muted">Local Server Status</p>
                                <p className="mt-2 text-lg font-semibold">Online</p>
                                <p className="mt-1 flex items-center gap-2 text-sm text-success">
                                    <span className="size-2 rounded-full bg-success" /> Application responding
                                </p>
                            </article>
                            <article className="rounded-[--radius-md] border border-line bg-surface-raised p-5 shadow-sm">
                                <p className="text-sm text-fg-muted">Settings Status</p>
                                <p className="mt-2 text-lg font-semibold">Read-only</p>
                                <p className="mt-1 text-sm text-fg-muted">Typed defaults are visible.</p>
                            </article>
                        </div>
                    </section>

                    <section className="rounded-[--radius-md] border border-line bg-surface-sunken p-5 sm:p-6">
                        <h2 className="text-xl font-semibold tracking-tight">What happens next</h2>
                        <p className="mt-2 max-w-3xl text-sm text-fg-muted">
                            Connector setup, library reporting, and review workflows will be introduced as separate V1 packages. The navigation keeps those future areas visible without presenting unavailable actions as links.
                        </p>
                    </section>

                    <section>
                        <div className="mb-4">
                            <h2 className="text-xl font-semibold tracking-tight">Media workspace roadmap</h2>
                            <p className="mt-1 text-sm text-fg-muted">Visible previews only — no connector data or configuration is active yet.</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {upcomingAreas.map(([title, description]) => (
                                <article className="rounded-[--radius-md] border border-dashed border-line bg-surface-raised p-5" key={title}>
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 className="font-semibold">{title}</h3>
                                            <p className="mt-2 text-sm text-fg-muted">{description}</p>
                                        </div>
                                        <span className="shrink-0 rounded-full bg-surface-sunken px-2.5 py-1 text-xs font-medium text-fg-muted">
                                            Later V1
                                        </span>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
