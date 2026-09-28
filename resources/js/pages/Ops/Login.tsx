import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function OpsLogin({ base }: { base: string }) {
    const form = useForm({ email: '', password: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`${base}/login`, { onFinish: () => form.reset('password') });
    };
    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-5 bg-ink px-4">
            <Head title="Restricted" />
            <form onSubmit={submit} noValidate className="flex w-full max-w-[400px] flex-col gap-4 rounded-2xl bg-white p-8">
                <h1 className="text-[21px] font-semibold">Restricted access</h1>
                <Field id="email" label="Email" error={form.errors.email}>
                    <Input id="email" type="email" autoComplete="username" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} invalid={!!form.errors.email} />
                </Field>
                <Field id="password" label="Password" error={form.errors.password}>
                    <Input id="password" type="password" autoComplete="current-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                </Field>
                <Button type="submit" disabled={form.processing}>
                    Continue
                </Button>
            </form>
        </div>
    );
}
