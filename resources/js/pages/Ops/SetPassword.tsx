import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

/** The link in a super admin invite: choose a password, then sign in and add the authenticator app. */
export default function OpsSetPassword({ base, token, account }: { base: string; token: string; account: { name: string; email: string } | null }) {
    const form = useForm({ password: '', password_confirmation: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`${base}/set-password/${token}`, { onFinish: () => form.reset() });
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-5 bg-[#101828] px-4">
            <Head title="Set your password" />
            <div className="flex w-full max-w-[400px] flex-col gap-4 rounded-2xl bg-surface p-8">
                {account ? (
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <div>
                            <h1 className="text-[21px] font-semibold">Welcome, {account.name.split(' ')[0]}</h1>
                            <p className="mt-1 text-sm text-ink-2">Choose a password for {account.email}. Next you'll add an authenticator app.</p>
                        </div>
                        <Field id="password" label="Password" hint="At least 12 characters." error={form.errors.password}>
                            <Input id="password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} invalid={!!form.errors.password} />
                        </Field>
                        <Field id="password_confirmation" label="Password again" error={form.errors.password_confirmation}>
                            <Input id="password_confirmation" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            Save password
                        </Button>
                    </form>
                ) : (
                    <>
                        <h1 className="text-[21px] font-semibold">This link no longer works</h1>
                        <p className="text-sm text-ink-2">It has expired or was already used. Ask another super admin to send a new invite.</p>
                        <Link href={`${base}/login`} className="text-sm font-semibold text-accent hover:underline">
                            Go to sign in
                        </Link>
                    </>
                )}
            </div>
        </div>
    );
}
