import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import OpsLayout from '@/layouts/ops-layout';
import { useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState, type FormEvent } from 'react';

/**
 * A saved key shows as a locked box ("••••vMy2", Saved) with Replace; the full key is never sent back
 * to the browser. Replace opens an empty field; Keep saved key closes it again without changing anything.
 */
function KeyInput({ id, saved, secret, placeholder, value, onChange, invalid }: { id: string; saved: string | null; secret?: boolean; placeholder: string; value: string; onChange: (v: string) => void; invalid: boolean }) {
    const [editing, setEditing] = useState(!saved || invalid);

    if (!editing) {
        return (
            <div className="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-line bg-canvas px-3">
                <span className="inline-flex min-w-0 items-center gap-2 text-[15px]">
                    <Lock size={14} aria-hidden className="shrink-0 text-muted" />
                    <span className="font-mono">{saved}</span>
                    <Badge tone="green">Saved</Badge>
                </span>
                <button id={id} type="button" onClick={() => setEditing(true)} className="min-h-9 shrink-0 rounded-md px-2 text-sm font-semibold text-accent hover:bg-accent-soft">
                    Replace
                </button>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            <Input id={id} type={secret ? 'password' : 'text'} autoComplete={secret ? 'new-password' : 'off'} spellCheck={false} autoFocus={!!saved} placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} invalid={invalid} />
            {saved && (
                <button
                    type="button"
                    onClick={() => {
                        onChange('');
                        setEditing(false);
                    }}
                    className="self-start text-sm font-semibold text-muted hover:text-ink-2 hover:underline"
                >
                    Keep saved key ({saved})
                </button>
            )}
        </div>
    );
}

interface Common {
    status: 'connected' | 'failed' | null;
    checkedAt: string | null;
    error: string | null;
    webhookUrl: string;
    events: string[];
}

interface Stripe extends Common {
    mode: 'test' | 'live';
    publishable: string | null;
    secret: string | null;
    webhookSecret: string | null;
}

interface PayPal extends Common {
    mode: 'sandbox' | 'live';
    clientId: string | null;
    secret: string | null;
    webhookId: string | null;
}

function Status({ g, live }: { g: Common; live: string }) {
    if (g.status === 'connected') return <Badge tone="green">Connected · {live}</Badge>;
    if (g.status === 'failed') return <Badge tone="red">Connection failed</Badge>;
    return <Badge tone="grey">Not connected</Badge>;
}

/** Where to point the gateway's webhooks, then the save button and when it was last tested. */
function Footer({ g, name, processing }: { g: Common; name: string; processing: boolean }) {
    return (
        <>
            <div className="rounded-lg border border-line bg-canvas p-4 text-sm">
                <p className="font-semibold">Webhook endpoint for the {name} dashboard</p>
                <p className="mt-1 font-mono text-[13px] break-all text-ink-2">{g.webhookUrl}</p>
                <p className="mt-2 text-muted">Events to send: {g.events.join(', ')}.</p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {processing ? 'Testing…' : 'Save and test connection'}
                </Button>
                {g.checkedAt && <span className="text-sm text-muted">Last tested {g.checkedAt}</span>}
            </div>
        </>
    );
}

function StripeCard({ base, stripe }: { base: string; stripe: Stripe }) {
    const form = useForm({ mode: stripe.mode, publishable: '', secret: '', webhook_secret: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`${base}/gateways/stripe`, { preserveScroll: true, onSuccess: () => form.reset('publishable', 'secret', 'webhook_secret') });
    };
    const prefix = form.data.mode === 'live' ? 'live' : 'test';

    return (
        <Card className="p-6">
            <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-[17px] font-semibold">Stripe</h2>
                    <Status g={stripe} live={stripe.mode === 'live' ? 'Live' : 'Test'} />
                </div>
                {stripe.error && <p className="text-sm text-red-700 dark:text-red-300">{stripe.error}</p>}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="s-pk" label="Publishable key" error={form.errors.publishable}>
                        <KeyInput key={`pk${stripe.publishable}${stripe.checkedAt}`} id="s-pk" saved={stripe.publishable} placeholder={`pk_${prefix}_…`} value={form.data.publishable} onChange={(v) => form.setData('publishable', v)} invalid={!!form.errors.publishable} />
                    </Field>
                    <Field id="s-sk" label="Secret key" error={form.errors.secret}>
                        <KeyInput key={`sk${stripe.secret}${stripe.checkedAt}`} id="s-sk" secret saved={stripe.secret} placeholder={`sk_${prefix}_…`} value={form.data.secret} onChange={(v) => form.setData('secret', v)} invalid={!!form.errors.secret} />
                    </Field>
                    <Field id="s-wh" label="Webhook signing secret" error={form.errors.webhook_secret}>
                        <KeyInput key={`wh${stripe.webhookSecret}${stripe.checkedAt}`} id="s-wh" secret saved={stripe.webhookSecret} placeholder="whsec_…" value={form.data.webhook_secret} onChange={(v) => form.setData('webhook_secret', v)} invalid={!!form.errors.webhook_secret} />
                    </Field>
                    <Field id="s-mode" label="Mode" error={form.errors.mode}>
                        <Select id="s-mode" value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value as 'test' | 'live')}>
                            <option value="test">Test</option>
                            <option value="live">Live</option>
                        </Select>
                    </Field>
                </div>
                <Footer g={stripe} name="Stripe" processing={form.processing} />
            </form>
        </Card>
    );
}

function PayPalCard({ base, paypal }: { base: string; paypal: PayPal }) {
    const form = useForm({ mode: paypal.mode, client_id: '', secret: '', webhook_id: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`${base}/gateways/paypal`, { preserveScroll: true, onSuccess: () => form.reset('client_id', 'secret', 'webhook_id') });
    };

    return (
        <Card className="p-6">
            <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-[17px] font-semibold">PayPal</h2>
                    <Status g={paypal} live={paypal.mode === 'live' ? 'Live' : 'Sandbox'} />
                </div>
                {paypal.error && <p className="text-sm text-red-700 dark:text-red-300">{paypal.error}</p>}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="p-id" label="Client ID" error={form.errors.client_id}>
                        <KeyInput key={`id${paypal.clientId}${paypal.checkedAt}`} id="p-id" saved={paypal.clientId} placeholder="A…" value={form.data.client_id} onChange={(v) => form.setData('client_id', v)} invalid={!!form.errors.client_id} />
                    </Field>
                    <Field id="p-sec" label="Client secret" error={form.errors.secret}>
                        <KeyInput key={`sec${paypal.secret}${paypal.checkedAt}`} id="p-sec" secret saved={paypal.secret} placeholder="E…" value={form.data.secret} onChange={(v) => form.setData('secret', v)} invalid={!!form.errors.secret} />
                    </Field>
                    <Field id="p-wh" label="Webhook ID" error={form.errors.webhook_id}>
                        <KeyInput key={`wh${paypal.webhookId}${paypal.checkedAt}`} id="p-wh" saved={paypal.webhookId} placeholder="1AB23456CD789012E" value={form.data.webhook_id} onChange={(v) => form.setData('webhook_id', v)} invalid={!!form.errors.webhook_id} />
                    </Field>
                    <Field id="p-mode" label="Mode" error={form.errors.mode}>
                        <Select id="p-mode" value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value as 'sandbox' | 'live')}>
                            <option value="sandbox">Sandbox</option>
                            <option value="live">Live</option>
                        </Select>
                    </Field>
                </div>
                <p className="text-sm text-muted">The monthly subscription plan is created in PayPal automatically at the current price the first time someone subscribes.</p>
                <Footer g={paypal} name="PayPal" processing={form.processing} />
            </form>
        </Card>
    );
}

/** Stripe and PayPal keys. Saved keys are never sent back to the page: only "••••" and the last 4 characters. */
export default function Gateways({ base, stripe, paypal }: { base: string; stripe: Stripe; paypal: PayPal }) {
    return (
        <OpsLayout title="Payment gateways" base={base}>
            <h1 className="mb-5 text-[26px] font-semibold">Payment gateways</h1>
            <div className="flex max-w-3xl flex-col gap-5">
                <StripeCard base={base} stripe={stripe} />
                <PayPalCard base={base} paypal={paypal} />
                <p className="text-[13px] leading-relaxed text-muted">Keys are encrypted at rest and never shown again after saving; only the last 4 characters are displayed. Every change is written to the audit log.</p>
            </div>
        </OpsLayout>
    );
}
