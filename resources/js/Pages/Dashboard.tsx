import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Dashboard() {
    const form = useForm<Record<string, never>>({});

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
            </main>
        </>
    );
}
