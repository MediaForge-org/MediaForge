import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Login() {
    const form = useForm({
        email: '',
        password: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <>
            <Head title="Sign in" />

            <main className="flex min-h-screen items-center justify-center px-6">
                <form
                    className="w-full max-w-sm space-y-5 rounded-[--radius-md] border border-line bg-surface-raised p-6 shadow-sm"
                    onSubmit={submit}
                >
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Sign in to MediaForge</h1>
                        <p className="mt-1 text-sm text-fg-muted">Use your local account to continue.</p>
                    </div>

                    <label className="block space-y-1.5 text-sm font-medium">
                        <span>Email</span>
                        <input
                            autoComplete="email"
                            className="w-full rounded-[--radius-sm] border border-line bg-surface px-3 py-2 text-fg outline-none focus:border-accent"
                            name="email"
                            onChange={(event) => form.setData('email', event.target.value)}
                            required
                            type="email"
                            value={form.data.email}
                        />
                        {form.errors.email && <p className="text-sm text-danger">{form.errors.email}</p>}
                    </label>

                    <label className="block space-y-1.5 text-sm font-medium">
                        <span>Password</span>
                        <input
                            autoComplete="current-password"
                            className="w-full rounded-[--radius-sm] border border-line bg-surface px-3 py-2 text-fg outline-none focus:border-accent"
                            name="password"
                            onChange={(event) => form.setData('password', event.target.value)}
                            required
                            type="password"
                            value={form.data.password}
                        />
                    </label>

                    <button
                        className="w-full rounded-[--radius-sm] bg-accent px-3 py-2 font-medium text-on-accent disabled:cursor-not-allowed disabled:opacity-60"
                        disabled={form.processing}
                        type="submit"
                    >
                        Sign in
                    </button>
                </form>
            </main>
        </>
    );
}
