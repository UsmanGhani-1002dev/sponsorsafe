import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function TwoFactor({ base, setup }: { base: string; setup: { secret: string; otpauth: string } | null }) {
    const form = useForm({ code: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`${base}/verify`, { onFinish: () => form.reset('code') });
    };
    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-5 bg-[#101828] px-4">
            <Head title="Verify" />
            <form onSubmit={submit} noValidate className="flex w-full max-w-[440px] flex-col gap-4 rounded-2xl bg-surface p-8">
                <h1 className="text-[21px] font-semibold">{setup ? 'Set up your authenticator app' : 'Enter your code'}</h1>
                {setup ? (
                    <div className="flex flex-col gap-2 text-sm text-ink-2">
                        <p>Add this account to Google Authenticator, Microsoft Authenticator or 1Password using the key below, then enter the 6-digit code it shows.</p>
                        <p className="break-all rounded-lg border border-line bg-canvas p-3 font-mono text-[13px]">{setup.secret}</p>
                        <a href={setup.otpauth} className="font-semibold text-accent">
                            Open in authenticator app (on this device)
                        </a>
                    </div>
                ) : (
                    <p className="text-sm text-ink-2">Enter the 6-digit code from your authenticator app.</p>
                )}
                <Field id="code" label="Authentication code" error={form.errors.code}>
                    <Input id="code" inputMode="numeric" autoComplete="one-time-code" autoFocus value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} invalid={!!form.errors.code} />
                </Field>
                <Button type="submit" disabled={form.processing}>
                    Verify and sign in
                </Button>
            </form>
        </div>
    );
}
