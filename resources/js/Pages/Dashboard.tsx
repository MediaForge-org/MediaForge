import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface DashboardPageProps {
    [key: string]: unknown;
    auth?: {
        user?: {
            name: string;
            email: string;
        } | null;
    };
}

export default function Dashboard() {
    const form = useForm<Record<string, never>>({});
    const { auth } = usePage<DashboardPageProps>().props;

    function logout(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/logout');
    }

    return (
        <>
            <Head title="Dashboard" />

            <main className="flex min-h-screen flex-col items-center justify-center gap-6 px-6 text-center">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">MediaForge Dashboard</h1>
                    <p className="mt-2 text-fg-muted">Your local MediaForge session is active.</p>
                    {auth?.user && (
                        <p className="mt-4 text-sm text-fg-muted">
                            Signed in as {auth.user.name} ({auth.user.email})
                        </p>
                    )}
                </div>

                <form onSubmit={logout}>
                    <button
                        className="rounded-[--radius-sm] border border-line bg-surface-raised px-3 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-60"
                        disabled={form.processing}
                        type="submit"
                    >
                        Sign out
                    </button>
                </form>

                <Link className="text-sm text-fg-muted hover:text-fg" href="/">
                    Back to home
                </Link>
            </main>
        </>
    );
}
