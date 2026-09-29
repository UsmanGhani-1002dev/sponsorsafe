import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import AuthLayout from '@/layouts/auth-layout';
import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    token: string;
    account: { name: string; email: string; business: string | null; firstTime: boolean } | null;
}

export default function SetPassword({ token, account }: Props) {
    const form = useForm({ password: '', password_confirmation: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/set-password/${token}`, { onFinish: () => form.reset() });
    };

    if (!account) {
        return (
            <AuthLayout title="Link expired" heading="This link has expired">
                <div className="flex flex-col gap-4 text-[15px] text-ink-2">
                    <p>Links to set a password work once and expire after a while. It may already have been used.</p>
                    <p>If you were invited by your employer, ask them to send a new invite. Otherwise, go to sign in and choose "Forgot password?".</p>
                    <Link href="/login" className="inline-flex min-h-11 items-center justify-center rounded-lg bg-accent-fill px-4 font-semibold text-white hover:bg-accent-fill-hover">
                        Go to sign in
                    </Link>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title={account.firstTime ? 'Set your password' : 'Choose a new password'}
            heading={account.firstTime ? 'Set your password' : 'Choose a new password'}
            subheading={`${account.email}${account.business ? ` · ${account.business}` : ''}`}
        >
            <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                <input type="email" name="email" autoComplete="username" value={account.email} readOnly hidden />
                <Field id="password" label="New password" error={form.errors.password} hint="At least 8 characters.">
                    <Input id="password" type="password" autoComplete="new-password" autoFocus value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} invalid={!!form.errors.password} />
                </Field>
                <Field id="password_confirmation" label="Type it again">
                    <Input id="password_confirmation" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                </Field>
                <Button type="submit" disabled={form.processing}>
                    Save password
                </Button>
            </form>
        </AuthLayout>
    );
}
