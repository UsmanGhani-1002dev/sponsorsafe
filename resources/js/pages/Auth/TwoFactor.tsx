import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import AuthLayout from '@/layouts/auth-layout';
import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    setup: { secret: string; otpauth: string } | null;
    account: { name: string; email: string; business: string | null };
}

export default function TwoFactor({ setup, account }: Props) {
    const form = useForm({ code: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/login/verify', { onFinish: () => form.reset('code') });
    };

    return (
        <AuthLayout
            title={setup ? 'Set up your authenticator' : 'Enter your code'}
            heading={setup ? 'Set up your authenticator app' : 'Enter your code'}
            subheading={`${account.name} · ${account.business ?? ''}`}
        >
            <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                {setup ? (
                    <div className="flex flex-col gap-3 text-sm text-ink-2">
                        <p>Business admins sign in with a code from an authenticator app as well as their password. This keeps your employees' records safe.</p>
                        <ol className="list-decimal space-y-1 pl-5">
                            <li>Open Google Authenticator, Microsoft Authenticator or 1Password on your phone.</li>
                            <li>Add an account and choose to enter a setup key.</li>
                            <li>Type in the key below, then enter the 6-digit code the app shows.</li>
                        </ol>
                        <p className="rounded-lg border border-line bg-canvas p-3 text-center font-mono text-[15px] tracking-wide break-all text-ink">{setup.secret}</p>
                        <a href={setup.otpauth} className="w-fit font-semibold text-accent hover:underline">
                            Open in an authenticator app on this device
                        </a>
                    </div>
                ) : (
                    <p className="text-sm text-ink-2">Enter the 6-digit code from your authenticator app.</p>
                )}
                <Field id="code" label="6-digit code" error={form.errors.code}>
                    <Input
                        id="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        autoFocus
                        maxLength={7}
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        invalid={!!form.errors.code}
                        className="font-mono text-lg tracking-[0.3em]"
                    />
                </Field>
                <Button type="submit" disabled={form.processing}>
                    {setup ? 'Turn on and sign in' : 'Sign in'}
                </Button>
                <button type="button" onClick={() => router.post('/login/reset')} className="min-h-10 text-sm font-semibold text-accent hover:underline">
                    Start again
                </button>
            </form>
        </AuthLayout>
    );
}
