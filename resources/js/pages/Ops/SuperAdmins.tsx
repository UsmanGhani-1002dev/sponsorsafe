import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Field, Input } from '@/components/ui/field';
import OpsLayout from '@/layouts/ops-layout';
import type { SharedProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface Admin {
    id: number;
    name: string;
    email: string;
    you: boolean;
    status: string;
    invited: boolean;
}

interface Props {
    base: string;
    admins: Admin[];
    allowedIps: string[];
    yourIp: string;
}

const opts = { preserveScroll: true, preserveState: true } as const;

export default function SuperAdmins({ base, admins, allowedIps, yourIp }: Props) {
    const { errors } = usePage<SharedProps>().props;
    const [removing, setRemoving] = useState<Admin | null>(null);
    const add = useForm({ name: '', email: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        add.post(`${base}/super-admins`, { ...opts, onSuccess: () => add.reset() });
    };

    return (
        <OpsLayout title="Super admins" base={base}>
            <h1 className="mb-1 text-[26px] font-semibold">Super admins</h1>
            <p className="mb-5 text-[15px] text-ink-2">People who can open this area. Each one has their own password and authenticator app.</p>

            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <Card className="overflow-hidden">
                    {errors.admin && (
                        <div className="p-4 pb-0">
                            <Alert>{errors.admin}</Alert>
                        </div>
                    )}
                    <ul>
                        {admins.map((a) => (
                            <li key={a.id} className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3.5 first:border-t-0">
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {a.name} {a.you && <span className="font-normal text-muted">(you)</span>}
                                    </p>
                                    <p className="text-[13px] break-all text-ink-2">{a.email}</p>
                                    <p className="text-[13px] text-muted">{a.status}</p>
                                </div>
                                {!a.you && (
                                    <div className="flex gap-1">
                                        {a.invited && (
                                            <Button variant="ghost" className="min-h-10 px-3 text-sm" onClick={() => router.post(`${base}/super-admins/${a.id}/resend`, {}, opts)}>
                                                Resend invite
                                            </Button>
                                        )}
                                        <Button variant="ghost" className="min-h-10 px-3 text-sm text-red-700 dark:text-red-300" onClick={() => setRemoving(a)}>
                                            Remove
                                        </Button>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card className="p-5 sm:p-6">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <h2 className="text-[17px] font-semibold">Add a super admin</h2>
                        <Field id="sa-name" label="Full name" error={add.errors.name}>
                            <Input id="sa-name" autoComplete="off" value={add.data.name} onChange={(e) => add.setData('name', e.target.value)} invalid={!!add.errors.name} />
                        </Field>
                        <Field id="sa-email" label="Email (their login)" error={add.errors.email}>
                            <Input id="sa-email" type="email" autoComplete="off" value={add.data.email} onChange={(e) => add.setData('email', e.target.value)} invalid={!!add.errors.email} />
                        </Field>
                        <Button type="submit" disabled={add.processing}>
                            Send invite
                        </Button>
                        <p className="text-[13px] text-muted">
                            They get an email to set a password (the link lasts 7 days), then add an authenticator app on first sign-in. Super admins see every business and can change prices and payment keys.
                        </p>
                    </form>
                </Card>
            </div>

            <Card className="mt-5 p-5 text-sm text-ink-2">
                <h2 className="mb-1 text-[15px] font-semibold text-ink">Allowed IP addresses</h2>
                {allowedIps.length ? (
                    <p>
                        This area only opens from: <span className="font-mono">{allowedIps.join(', ')}</span>. A new super admin's IP address must be added to <span className="font-mono">OPS_ALLOWED_IPS</span> in the server's <span className="font-mono">.env</span> file, or they will see "Not found".
                    </p>
                ) : (
                    <p>
                        Any IP address can open this area, because <span className="font-mono">OPS_ALLOWED_IPS</span> in the server's <span className="font-mono">.env</span> file is empty. On the live site, list your office and home IP addresses there.
                    </p>
                )}
                <p className="mt-1 text-muted">
                    Your IP address now: <span className="font-mono">{yourIp}</span>
                </p>
            </Card>

            <ConfirmDialog
                open={removing !== null}
                title={`Remove ${removing?.name ?? ''}?`}
                confirmLabel="Remove super admin"
                danger
                onClose={() => setRemoving(null)}
                onConfirm={() => removing && router.delete(`${base}/super-admins/${removing.id}`, { ...opts, onFinish: () => setRemoving(null) })}
            >
                They will no longer be able to sign in to the super admin area. Everything they did stays in the audit log.
            </ConfirmDialog>
        </OpsLayout>
    );
}
