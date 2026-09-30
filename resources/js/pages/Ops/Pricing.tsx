import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Field, Input } from '@/components/ui/field';
import OpsLayout from '@/layouts/ops-layout';
import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface Subscribers {
    older: number;
    waiting: number;
    scheduled: number;
    scheduledOn: string | null;
    plans: string[];
    moveOn: string;
    list: { id: number; name: string; admin: string | null; plan: string; payment: string | null; suspended: boolean; movesOn: string | null }[];
}

interface Props {
    base: string;
    values: { price: string; limit: string; training: string; grace: string };
    subscribers: Subscribers;
}

export default function Pricing({ base, values, subscribers }: Props) {
    const form = useForm(values);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`${base}/pricing`, { preserveScroll: true });
    };

    return (
        <OpsLayout title="Plans and pricing" base={base}>
            <h1 className="mb-5 text-[26px] font-semibold">Plans and pricing</h1>
            <div className="flex max-w-3xl flex-col gap-5">
                <Card className="p-6">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field id="price" label="Monthly price (£)" error={form.errors.price}>
                                <Input id="price" inputMode="decimal" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} invalid={!!form.errors.price} />
                            </Field>
                            <Field id="limit" label="Employee limit" error={form.errors.limit}>
                                <Input id="limit" inputMode="numeric" value={form.data.limit} onChange={(e) => form.setData('limit', e.target.value)} invalid={!!form.errors.limit} />
                            </Field>
                            <Field id="training" label="1-to-1 training per person (£)" error={form.errors.training}>
                                <Input id="training" inputMode="decimal" value={form.data.training} onChange={(e) => form.setData('training', e.target.value)} invalid={!!form.errors.training} />
                            </Field>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field id="grace" label="Grace period (days)" error={form.errors.grace} hint="After a failed payment, before access is paused.">
                                <Input id="grace" inputMode="numeric" value={form.data.grace} onChange={(e) => form.setData('grace', e.target.value)} invalid={!!form.errors.grace} />
                            </Field>
                        </div>
                        <Alert tone="info">New prices show on the website straight away. Existing subscribers keep their current price until you move them below.</Alert>
                        <div>
                            <Button type="submit" disabled={form.processing || !form.isDirty}>
                                Save pricing
                            </Button>
                        </div>
                    </form>
                </Card>
                <ExistingSubscribers base={base} s={subscribers} />
            </div>
        </OpsLayout>
    );
}

/** Subscribers on an older price or employee limit: email them now, move them in 30 days. */
function ExistingSubscribers({ base, s }: { base: string; s: Subscribers }) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const move = () => {
        setProcessing(true);
        router.post(`${base}/pricing/move`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });
    };

    return (
        <Card className="flex flex-col gap-3 p-6">
            <h2 className="text-[17px] font-semibold">Existing subscribers</h2>
            {s.older === 0 ? (
                <p className="text-[15px] text-ink-2">Every subscriber is on the current plan.</p>
            ) : (
                <>
                    <p className="text-[15px] text-ink-2">
                        {s.older} subscriber{s.older === 1 ? ' is' : 's are'} on an older plan: {s.plans.join(', ')}.
                    </p>
                    <div className="overflow-x-auto rounded-lg border border-line">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="bg-canvas text-[13px] text-ink-2">
                                <tr>
                                    {['Business', 'Admin email', 'Now', 'Pays by', 'Status'].map((h) => (
                                        <th key={h} scope="col" className="px-4 py-2.5 font-semibold">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {s.list.map((b) => (
                                    <tr key={b.id} className="border-t border-line">
                                        <td className="px-4 py-2.5 font-semibold">
                                            {b.name}
                                            {b.suspended && <span className="ml-2 font-normal text-muted">(suspended)</span>}
                                        </td>
                                        <td className="px-4 py-2.5 break-all text-ink-2">{b.admin ?? '—'}</td>
                                        <td className="px-4 py-2.5 whitespace-nowrap">{b.plan}</td>
                                        <td className="px-4 py-2.5">{b.payment ?? '—'}</td>
                                        <td className="px-4 py-2.5">{b.movesOn ? <Badge tone="blue">Emailed · moves {b.movesOn}</Badge> : <Badge tone="grey">Not told yet</Badge>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {s.scheduled > 0 && (
                        <p className="text-sm text-muted">
                            {s.scheduled} already emailed; they move on {s.scheduledOn}.
                        </p>
                    )}
                    {s.waiting > 0 && (
                        <div>
                            <Button variant="secondary" onClick={() => setConfirming(true)}>
                                Email {s.waiting} and move them on {s.moveOn}
                            </Button>
                        </div>
                    )}
                </>
            )}
            <p className="text-[13px] text-muted">Subscribers are told 30 days ahead. On the date, card subscriptions move to the new price in Stripe and PayPal plans are updated; the new price applies from their next payment.</p>
            <ConfirmDialog
                open={confirming}
                title={`Move ${s.waiting} subscriber${s.waiting === 1 ? '' : 's'} to the current plan?`}
                confirmLabel="Email them now"
                processing={processing}
                onClose={() => setConfirming(false)}
                onConfirm={move}
            >
                Each business admin is emailed today with the new price and the date it starts ({s.moveOn}). They can cancel any time before then.
            </ConfirmDialog>
        </Card>
    );
}
