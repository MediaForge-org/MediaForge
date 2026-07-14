import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import AuthShell from '@/Components/AuthShell';
import Button from '@/Components/UI/Button';
import TextField from '@/Components/UI/TextField';

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

            <AuthShell
                subtitle="Set up a local account to continue."
                title="Create your MediaForge account"
                footer={
                    <>
                        <Link className="transition-colors hover:text-fg" href="/login">
                            Already have an account? Sign in
                        </Link>
                        <Link className="transition-colors hover:text-fg" href="/">
                            Back to home
                        </Link>
                    </>
                }
            >
                <form className="space-y-4" onSubmit={submit}>
                    <TextField
                        autoComplete="name"
                        error={form.errors.name}
                        label="Name"
                        name="name"
                        onChange={(event) => form.setData('name', event.target.value)}
                        required
                        type="text"
                        value={form.data.name}
                    />
                    <TextField
                        autoComplete="email"
                        error={form.errors.email}
                        label="Email"
                        name="email"
                        onChange={(event) => form.setData('email', event.target.value)}
                        required
                        type="email"
                        value={form.data.email}
                    />
                    <TextField
                        autoComplete="new-password"
                        error={form.errors.password}
                        hint="At least 8 characters."
                        label="Password"
                        name="password"
                        onChange={(event) => form.setData('password', event.target.value)}
                        required
                        type="password"
                        value={form.data.password}
                    />
                    <TextField
                        autoComplete="new-password"
                        label="Confirm password"
                        name="password_confirmation"
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        required
                        type="password"
                        value={form.data.password_confirmation}
                    />
                    <Button className="w-full" loading={form.processing} type="submit">
                        Create account
                    </Button>
                </form>
            </AuthShell>
        </>
    );
}
