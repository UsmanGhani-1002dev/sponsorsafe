import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import OpsLayout from '@/layouts/ops-layout';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface Stripe {
    mode: 'test' | 'live';
    publishable: string | null;
    secret: string | null;
    webhookSecret: string | null;
    status: 'connected' | 'failed' | null;
    checkedAt: string | null;
    error: string | null;
    webhookUrl: string;
    events: string[];
}

/** Stripe keys (PayPal next). Saved keys are never sent back to the page: only "••••" and the last 4 characters. */
export default function Gateways({ base, stripe }: { base: string; stripe: Stripe }) {
    const form = useForm({ mode: stripe.mode, publishable: '', secret: '', webhook_secret: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`${base}/gateways/stripe`, { preserveScroll: true, onSuccess: () => form.reset('publishable', 'secret', 'webhook_secret') });
    };
    const saved = (v: string | null) => (v ? `Saved: ${v}. Leave blank to keep it.` : undefined);
    const prefix = form.data.mode === 'live' ? 'live' : 'test';

    return (
        <OpsLayout title="Payment gateways" base={base}>
            <h1 className="mb-5 text-[26px] font-semibold">Payment gateways</h1>
            <div className="flex max-w-3xl flex-col gap-5">
                <Card className="p-6">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-[17px] font-semibold">Stripe</h2>
                            {stripe.status === 'connected' ? (
                                <Badge tone="green">Connected · {stripe.mode === 'live' ? 'Live' : 'Test'}</Badge>
                            ) : stripe.status === 'failed' ? (
                                <Badge tone="red">Connection failed</Badge>
                            ) : (
                                <Badge tone="grey">Not connected</Badge>
                            )}
                        </div>
                        {stripe.error && <p className="text-sm text-red-700 dark:text-red-300">{stripe.error}</p>}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="s-pk" label="Publishable key" error={form.errors.publishable} hint={saved(stripe.publishable)}>
                                <Input id="s-pk" autoComplete="off" spellCheck={false} placeholder={`pk_${prefix}_…`} value={form.data.publishable} onChange={(e) => form.setData('publishable', e.target.value)} invalid={!!form.errors.publishable} />
                            </Field>
                            <Field id="s-sk" label="Secret key" error={form.errors.secret} hint={saved(stripe.secret)}>
                                <Input id="s-sk" type="password" autoComplete="new-password" placeholder={`sk_${prefix}_…`} value={form.data.secret} onChange={(e) => form.setData('secret', e.target.value)} invalid={!!form.errors.secret} />
                            </Field>
                            <Field id="s-wh" label="Webhook signing secret" error={form.errors.webhook_secret} hint={saved(stripe.webhookSecret)}>
                                <Input id="s-wh" type="password" autoComplete="new-password" placeholder="whsec_…" value={form.data.webhook_secret} onChange={(e) => form.setData('webhook_secret', e.target.value)} invalid={!!form.errors.webhook_secret} />
                            </Field>
                            <Field id="s-mode" label="Mode" error={form.errors.mode}>
                                <Select id="s-mode" value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value as 'test' | 'live')}>
                                    <option value="test">Test</option>
                                    <option value="live">Live</option>
                                </Select>
                            </Field>
                        </div>
                        <div className="rounded-lg border border-line bg-canvas p-4 text-sm">
                            <p className="font-semibold">Webhook endpoint for the Stripe dashboard</p>
                            <p className="mt-1 font-mono text-[13px] break-all text-ink-2">{stripe.webhookUrl}</p>
                            <p className="mt-2 text-muted">Events to send: {stripe.events.join(', ')}.</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Testing…' : 'Save and test connection'}
                            </Button>
                            {stripe.checkedAt && <span className="text-sm text-muted">Last tested {stripe.checkedAt}</span>}
                        </div>
                    </form>
                </Card>

                <Card className="flex items-center justify-between gap-3 p-6">
                    <div>
                        <h2 className="text-[17px] font-semibold">PayPal</h2>
                        <p className="mt-1 text-sm text-muted">Client ID, secret and subscription plan ID. Arrives in the next step.</p>
                    </div>
                    <Badge tone="grey">Soon</Badge>
                </Card>

                <p className="text-[13px] leading-relaxed text-muted">Keys are encrypted at rest and never shown again after saving; only the last 4 characters are displayed. Every change is written to the audit log.</p>
            </div>
        </OpsLayout>
    );
}
