import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import AuthShell from '@/Components/AuthShell';
import Button from '@/Components/UI/Button';
import TextField from '@/Components/UI/TextField';

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

            <AuthShell
                subtitle="Use your local account to continue."
                title="Sign in to MediaForge"
                footer={
                    <>
                        <Link className="transition-colors hover:text-fg" href="/register">
                            Create an account
                        </Link>
                        <Link className="transition-colors hover:text-fg" href="/">
                            Back to home
                        </Link>
                    </>
                }
            >
                <form className="space-y-4" onSubmit={submit}>
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
                        autoComplete="current-password"
                        error={form.errors.password}
                        label="Password"
                        name="password"
                        onChange={(event) => form.setData('password', event.target.value)}
                        required
                        type="password"
                        value={form.data.password}
                    />
                    <Button className="w-full" loading={form.processing} type="submit">
                        Sign in
                    </Button>
                </form>
            </AuthShell>
        </>
    );
}
