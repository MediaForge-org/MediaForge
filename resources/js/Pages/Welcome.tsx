import { Head, Link, usePage } from '@inertiajs/react';

interface WelcomeProps {
    version: string;
}

interface SharedPageProps {
    [key: string]: unknown;
    auth?: {
        user?: { id: string } | null;
    };
}

export default function Welcome({ version }: WelcomeProps) {
    const { auth } = usePage<SharedPageProps>().props;
    const destination = auth?.user ? '/dashboard' : '/login';
    const destinationLabel = auth?.user ? 'Open dashboard' : 'Sign in';

    return (
        <>
            <Head title="Welcome" />

            <main className="flex min-h-screen flex-col items-center justify-center gap-6 px-6 text-center">
                <div className="flex items-center gap-3">
                    <div className="grid size-12 place-items-center rounded-[--radius-md] bg-accent text-2xl font-bold text-on-accent">
                        M
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">MediaForge</h1>
                </div>

                <p className="max-w-prose text-fg-muted">
                    An open-source local media enhancement suite for Jellyfin &amp; Audiobookshelf.
                </p>

                <span className="rounded-[--radius-sm] border border-line bg-surface-raised px-3 py-1 font-mono text-sm text-fg-muted">
                    {version}
                </span>

                <Link
                    className="rounded-[--radius-sm] bg-accent px-4 py-2 font-medium text-on-accent"
                    href={destination}
                >
                    {destinationLabel}
                </Link>
            </main>
        </>
    );
}
