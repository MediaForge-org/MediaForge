import { Head, Link, usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge, { type BadgeTone } from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import SectionHeader from '@/Components/UI/SectionHeader';

interface SettingDefinition {
    description: string;
    key: string;
    type: string;
}

interface SettingsPageProps {
    [key: string]: unknown;
    definitions: SettingDefinition[];
}

const settingsAreas: { title: string; description: string; badge: string; tone: BadgeTone }[] = [
    { title: 'Application', description: 'Application identity, local behavior, and interface defaults.', badge: 'Read-only', tone: 'neutral' },
    { title: 'Security', description: 'Authentication, session protection, and API safety defaults.', badge: 'Read-only', tone: 'neutral' },
    { title: 'Media Paths', description: 'Library locations and media storage will be configured in a later V1 package.', badge: 'Later V1', tone: 'neutral' },
    { title: 'Connectors', description: 'Jellyfin and Audiobookshelf are configured on the Connectors page, not here.', badge: 'Later V1', tone: 'neutral' },
    { title: 'Playback', description: 'Playback reporting and watch-state preferences are planned for later V1.', badge: 'Later V1', tone: 'neutral' },
    { title: 'Privacy', description: 'Local data handling controls will be expanded in a later V1 package.', badge: 'Later V1', tone: 'neutral' },
];

export default function SettingsIndex() {
    const { definitions } = usePage<SettingsPageProps>().props;

    return (
        <>
            <Head title="Settings" />

            <AuthenticatedLayout>
                <div className="flex flex-col gap-10">
                    <div className="mf-rise">
                        <PageHeader
                            description="A structured, read-only overview of what MediaForge configures locally. Editing, secrets, and connector setup are intentionally outside this package."
                            eyebrow="V1 foundation"
                            title="Settings"
                        />
                    </div>

                    <section aria-label="Configuration areas" className="mf-rise" style={{ '--mf-i': 1 } as CSSProperties}>
                        <SectionHeader
                            description="All areas are visible for orientation and remain read-only."
                            title="Configuration areas"
                        />
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {settingsAreas.map((area) => (
                                <Card interactive key={area.title}>
                                    <div className="flex items-start justify-between gap-4">
                                        <h3 className="font-semibold">{area.title}</h3>
                                        <Badge tone={area.tone}>{area.badge}</Badge>
                                    </div>
                                    <p className="mt-3 text-sm text-fg-muted">{area.description}</p>
                                </Card>
                            ))}
                        </div>
                    </section>

                    <section aria-label="Registered defaults" className="mf-rise" style={{ '--mf-i': 2 } as CSSProperties}>
                        <SectionHeader
                            description="Typed defaults from code. No secrets or connector credentials are displayed."
                            title="Registered defaults"
                        />
                        <Card padded={false}>
                            <ul className="divide-y divide-line">
                                {definitions.map((definition) => (
                                    <li className="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-start sm:justify-between" key={definition.key}>
                                        <div className="min-w-0">
                                            <p className="font-mono text-sm font-medium">{definition.key}</p>
                                            <p className="mt-1 text-sm text-fg-muted">{definition.description}</p>
                                        </div>
                                        <Badge className="font-mono" tone="neutral">
                                            {definition.type}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </section>

                    <Link className="inline-flex w-fit text-sm font-medium text-accent transition-colors hover:text-accent-hover" href="/dashboard">
                        ← Back to dashboard
                    </Link>
                </div>
            </AuthenticatedLayout>
        </>
    );
}
