import { Head, Link, usePage } from '@inertiajs/react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface SettingDefinition {
    description: string;
    key: string;
    type: string;
}

interface SettingsPageProps {
    [key: string]: unknown;
    definitions: SettingDefinition[];
}

const settingsAreas = [
    ['Application', 'Application identity, local behavior, and interface defaults.'],
    ['Security', 'Authentication, session protection, and API safety defaults.'],
    ['Media Paths', 'Library locations and media storage will be configured in a later V1 package.'],
    ['Connectors', 'Jellyfin and Audiobookshelf setup is intentionally not active yet.'],
    ['Playback', 'Playback reporting and watch-state preferences are planned for later V1.'],
    ['Privacy', 'Local data handling controls will be expanded in a later V1 package.'],
];

export default function SettingsIndex() {
    const { definitions } = usePage<SettingsPageProps>().props;

    return (
        <>
            <Head title="Settings" />

            <AuthenticatedLayout>
                <section className="flex flex-col gap-8">
                    <div className="rounded-[--radius-md] border border-line bg-surface-raised p-6 shadow-sm sm:p-8">
                        <p className="text-sm font-medium text-accent">V1 foundation</p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">Settings</h1>
                        <p className="mt-2 max-w-2xl text-fg-muted">
                            A structured, read-only overview of what MediaForge will configure locally. Editing, secrets, and connector setup are intentionally outside this package.
                        </p>
                    </div>

                    <section>
                        <div className="mb-4">
                            <h2 className="text-xl font-semibold tracking-tight">Configuration areas</h2>
                            <p className="mt-1 text-sm text-fg-muted">All areas are visible for orientation and remain read-only.</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {settingsAreas.map(([title, description]) => (
                                <article className="rounded-[--radius-md] border border-line bg-surface-raised p-5 shadow-sm" key={title}>
                                    <div className="flex items-start justify-between gap-4">
                                        <h3 className="font-semibold">{title}</h3>
                                        <span className="rounded-full bg-surface-sunken px-2.5 py-1 text-xs font-medium text-fg-muted">Read-only</span>
                                    </div>
                                    <p className="mt-3 text-sm text-fg-muted">{description}</p>
                                </article>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-[--radius-md] border border-line bg-surface-raised shadow-sm">
                        <div className="border-b border-line px-5 py-4">
                            <h2 className="font-semibold">Registered defaults</h2>
                            <p className="mt-1 text-sm text-fg-muted">No secrets or connector credentials are displayed.</p>
                        </div>
                        <ul className="divide-y divide-line">
                            {definitions.map((definition) => (
                                <li className="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:justify-between" key={definition.key}>
                                    <div>
                                        <p className="font-mono text-sm font-medium">{definition.key}</p>
                                        <p className="mt-1 text-sm text-fg-muted">{definition.description}</p>
                                    </div>
                                    <span className="w-fit rounded-full bg-surface-sunken px-2.5 py-1 font-mono text-xs text-fg-muted">
                                        {definition.type}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <Link className="inline-flex text-sm font-medium text-accent hover:text-accent-hover" href="/dashboard">
                        Back to dashboard
                    </Link>
                </section>
            </AuthenticatedLayout>
        </>
    );
}
