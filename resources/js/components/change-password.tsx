import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

/**
 * Change password while signed in (business admins, employees, super admins). Needs the current password, and
 * the authenticator code when two-step sign-in is on. The server signs out other devices and emails the person.
 */
export function ChangePasswordForm({ action, needsCode, minLength = 8, id = 'pw' }: { action: string; needsCode: boolean; minLength?: number; id?: string }) {
    const form = useForm({ current_password: '', password: '', password_confirmation: '', code: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(action, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () => form.reset('code'),
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4">
            <Field id={`${id}-current`} label="Current password" error={form.errors.current_password}>
                <Input id={`${id}-current`} type="password" autoComplete="current-password" value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} invalid={!!form.errors.current_password} />
            </Field>
            <Field id={`${id}-new`} label="New password" hint={`At least ${minLength} characters.`} error={form.errors.password}>
                <Input id={`${id}-new`} type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} invalid={!!form.errors.password} />
            </Field>
            <Field id={`${id}-confirm`} label="New password again" error={form.errors.password_confirmation}>
                <Input id={`${id}-confirm`} type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} invalid={!!form.errors.password_confirmation} />
            </Field>
            {needsCode && (
                <Field id={`${id}-code`} label="6-digit code from your authenticator app" error={form.errors.code}>
                    <Input id={`${id}-code`} inputMode="numeric" autoComplete="one-time-code" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} invalid={!!form.errors.code} className="font-mono tracking-[0.3em]" />
                </Field>
            )}
            <Button type="submit" disabled={form.processing} className="w-fit">
                Change password
            </Button>
            <p className="text-[13px] text-muted">Any other devices signed in with this login will be signed out, and we'll email you to confirm the change.</p>
        </form>
    );
}
