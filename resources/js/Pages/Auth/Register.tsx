import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Create account" />

            <main className="flex min-h-screen items-center justify-center px-6 py-10">
                <form
                    className="w-full max-w-sm space-y-5 rounded-[--radius-md] border border-line bg-surface-raised p-6 shadow-sm"
                    onSubmit={submit}
                >
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Create your MediaForge account</h1>
                        <p className="mt-1 text-sm text-fg-muted">Set up a local account to continue.</p>
                    </div>

                    <label className="block space-y-1.5 text-sm font-medium">
                        <span>Name</span>
                        <input
                            autoComplete="name"
                            className="w-full rounded-[--radius-sm] border border-line bg-surface px-3 py-2 text-fg outline-none focus:border-accent"
                            name="name"
                            onChange={(event) => form.setData('name', event.target.value)}
                            required
                            type="text"
                            value={form.data.name}
                        />
                        {form.errors.name && <p className="text-sm text-danger">{form.errors.name}</p>}
                    </label>

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
                            autoComplete="new-password"
                            className="w-full rounded-[--radius-sm] border border-line bg-surface px-3 py-2 text-fg outline-none focus:border-accent"
                            name="password"
                            onChange={(event) => form.setData('password', event.target.value)}
                            required
                            type="password"
                            value={form.data.password}
                        />
                        {form.errors.password && <p className="text-sm text-danger">{form.errors.password}</p>}
                    </label>

                    <label className="block space-y-1.5 text-sm font-medium">
                        <span>Confirm password</span>
                        <input
                            autoComplete="new-password"
                            className="w-full rounded-[--radius-sm] border border-line bg-surface px-3 py-2 text-fg outline-none focus:border-accent"
                            name="password_confirmation"
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                            required
                            type="password"
                            value={form.data.password_confirmation}
                        />
                    </label>

                    <button
                        className="w-full rounded-[--radius-sm] bg-accent px-3 py-2 font-medium text-on-accent disabled:cursor-not-allowed disabled:opacity-60"
                        disabled={form.processing}
                        type="submit"
                    >
                        Create account
                    </button>

                    <Link className="block text-center text-sm text-fg-muted hover:text-fg" href="/login">
                        Already have an account? Sign in
                    </Link>
                    <Link className="block text-center text-sm text-fg-muted hover:text-fg" href="/">
                        Back to home
                    </Link>
                </form>
            </main>
        </>
    );
}
