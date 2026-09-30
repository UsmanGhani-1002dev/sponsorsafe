import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input } from '@/components/ui/field';
import PortalLayout from '@/layouts/portal-layout';
import { Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Props {
    sections: { title: string; fields: { label: string; value: string }[] }[];
    twoFactor: { enabled: boolean; setup: { secret: string; otpauth: string } | null };
}

export default function MyDetails({ sections, twoFactor }: Props) {
    return (
        <PortalLayout title="My details">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-[26px] font-semibold">My details</h1>
                <Link href="/me/update-details" className="inline-flex min-h-11 items-center rounded-lg border border-line-strong bg-surface px-4 text-[15px] font-semibold text-ink-2 hover:bg-canvas">
                    Something's changed
                </Link>
            </div>

            <div className="mt-5 flex flex-col gap-4">
                {sections.map((s) => (
                    <Card key={s.title} className="p-4 sm:p-6">
                        <h2 className="mb-2 text-[17px] font-semibold">{s.title}</h2>
                        <dl>
                            {s.fields.map((f) => (
                                <div key={f.label} className="grid gap-0.5 border-t border-line py-2.5 text-sm sm:grid-cols-[200px_minmax(0,1fr)] sm:gap-3">
                                    <dt className="text-muted">{f.label}</dt>
                                    <dd className="font-medium">{f.value}</dd>
                                </div>
                            ))}
                        </dl>
                    </Card>
                ))}
                <TwoStep {...twoFactor} />
            </div>
        </PortalLayout>
    );
}

/** Optional two-step sign-in with an authenticator app. */
function TwoStep({ enabled, setup }: Props['twoFactor']) {
    const confirm = useForm({ code: '' });
    const disable = useForm({ password: '' });
    const opts = { preserveScroll: true } as const;

    return (
        <Card className="p-4 sm:p-6">
            <div className="mb-2 flex items-center justify-between gap-3">
                <h2 className="text-[17px] font-semibold">Two-step sign-in</h2>
                <Badge tone={enabled ? 'green' : 'grey'}>{enabled ? 'On' : 'Off'}</Badge>
            </div>
            {enabled ? (
                <form
                    noValidate
                    className="flex flex-col gap-3"
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        disable.delete('/me/security/two-factor', { ...opts, onFinish: () => disable.reset() });
                    }}
                >
                    <p className="text-sm text-ink-2">You enter a code from your authenticator app each time you sign in. To turn it off, confirm your password.</p>
                    <Field id="tf-password" label="Password" error={disable.errors.password}>
                        <Input id="tf-password" type="password" autoComplete="current-password" value={disable.data.password} onChange={(e) => disable.setData('password', e.target.value)} invalid={!!disable.errors.password} />
                    </Field>
                    <Button type="submit" variant="secondary" disabled={disable.processing}>
                        Turn off two-step sign-in
                    </Button>
                </form>
            ) : setup ? (
                <form
                    noValidate
                    className="flex flex-col gap-3"
                    onSubmit={(e: FormEvent) => {
                        e.preventDefault();
                        confirm.post('/me/security/two-factor/confirm', { ...opts, onFinish: () => confirm.reset() });
                    }}
                >
                    <p className="text-sm text-ink-2">In Google Authenticator, Microsoft Authenticator or 1Password, add an account with this setup key, then enter the 6-digit code it shows.</p>
                    <p className="rounded-lg border border-line bg-canvas p-3 text-center font-mono text-[15px] tracking-wide break-all">{setup.secret}</p>
                    <a href={setup.otpauth} className="w-fit text-sm font-semibold text-accent hover:underline">
                        Open in an authenticator app on this phone
                    </a>
                    <Field id="tf-code" label="6-digit code" error={confirm.errors.code}>
                        <Input id="tf-code" inputMode="numeric" autoComplete="one-time-code" value={confirm.data.code} onChange={(e) => confirm.setData('code', e.target.value)} invalid={!!confirm.errors.code} className="font-mono tracking-[0.3em]" />
                    </Field>
                    <Button type="submit" disabled={confirm.processing}>
                        Turn on
                    </Button>
                </form>
            ) : (
                <div className="flex flex-col gap-3">
                    <p className="text-sm text-ink-2">Optional. Adds a code from an authenticator app on your phone when you sign in, so nobody else can get in with just your password.</p>
                    <Button variant="secondary" className="w-fit" onClick={() => router.post('/me/security/two-factor', {}, opts)}>
                        Set up two-step sign-in
                    </Button>
                </div>
            )}
        </Card>
    );
}
