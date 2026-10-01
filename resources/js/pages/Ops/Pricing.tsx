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
    corporate: number;
    moveOn: string;
    list: { id: number; name: string; admin: string | null; employees: number; now: string; moveTo: string | null; payment: string | null; suspended: boolean; movesOn: string | null }[];
}

type Tiers = Record<string, { price: string; limit: string }>;

interface Props {
    base: string;
    tierNames: Record<string, string>;
    values: { tiers: Tiers; training: string; grace: string };
    subscribers: Subscribers;
}

export default function Pricing({ base, tierNames, values, subscribers }: Props) {
    const form = useForm(values);
    const errors = form.errors as Record<string, string>;
    const setTier = (key: string, field: 'price' | 'limit', value: string) => form.setData('tiers', { ...form.data.tiers, [key]: { ...form.data.tiers[key], [field]: value } });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`${base}/pricing`, { preserveScroll: true });
    };

    return (
        <OpsLayout title="Plans and pricing" base={base}>
            <h1 className="mb-5 text-[26px] font-semibold">Plans and pricing</h1>
            <div className="flex max-w-4xl flex-col gap-5">
                <Card className="p-6">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                        {Object.entries(tierNames).map(([key, name]) => (
                            <fieldset key={key} className="grid gap-4 sm:grid-cols-3">
                                <legend className="mb-2 text-[15px] font-semibold">{name}</legend>
                                <Field id={`${key}-price`} label="Monthly price (£)" error={errors[`tiers.${key}.price`]}>
                                    <Input id={`${key}-price`} inputMode="decimal" value={form.data.tiers[key].price} onChange={(e) => setTier(key, 'price', e.target.value)} invalid={!!errors[`tiers.${key}.price`]} />
                                </Field>
                                <Field id={`${key}-limit`} label="Up to (employees)" error={errors[`tiers.${key}.limit`]}>
                                    <Input id={`${key}-limit`} inputMode="numeric" value={form.data.tiers[key].limit} onChange={(e) => setTier(key, 'limit', e.target.value)} invalid={!!errors[`tiers.${key}.limit`]} />
                                </Field>
                            </fieldset>
                        ))}
                        <p className="text-sm text-muted">More employees than the largest plan is the Corporate package: agree a price, then set it on that business in Businesses → Set plan.</p>
                        <div className="grid gap-4 border-t border-line pt-5 sm:grid-cols-3">
                            <Field id="training" label="1-to-1 training per person (£)" error={errors.training}>
                                <Input id="training" inputMode="decimal" value={form.data.training} onChange={(e) => form.setData('training', e.target.value)} invalid={!!errors.training} />
                            </Field>
                            <Field id="grace" label="Grace period (days)" error={errors.grace} hint="After a failed payment, before access is paused.">
                                <Input id="grace" inputMode="numeric" value={form.data.grace} onChange={(e) => form.setData('grace', e.target.value)} invalid={!!errors.grace} />
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

/** Subscribers not on today's price for their plan: email them now, move them in 30 days. */
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
                <p className="text-[15px] text-ink-2">Every subscriber is on today's price for their plan.</p>
            ) : (
                <>
                    <p className="text-[15px] text-ink-2">
                        {s.older} subscriber{s.older === 1 ? ' is' : 's are'} on an older price. Businesses on the original plan move to the plan that fits their current employees.
                    </p>
                    <div className="overflow-x-auto rounded-lg border border-line">
                        <table className="w-full min-w-[760px] text-left text-sm">
                            <thead className="bg-canvas text-[13px] text-ink-2">
                                <tr>
                                    {['Business', 'Employees', 'Now', 'Moves to', 'Pays by', 'Status'].map((h) => (
                                        <th key={h} scope="col" className="px-4 py-2.5 font-semibold">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {s.list.map((b) => (
                                    <tr key={b.id} className="border-t border-line align-top">
                                        <td className="px-4 py-2.5">
                                            <p className="font-semibold">
                                                {b.name}
                                                {b.suspended && <span className="ml-2 font-normal text-muted">(suspended)</span>}
                                            </p>
                                            <p className="text-xs break-all text-muted">{b.admin ?? '—'}</p>
                                        </td>
                                        <td className="px-4 py-2.5 font-mono">{b.employees}</td>
                                        <td className="px-4 py-2.5">{b.now}</td>
                                        <td className="px-4 py-2.5">{b.moveTo ?? <span className="text-muted">Needs a Corporate price</span>}</td>
                                        <td className="px-4 py-2.5">{b.payment ?? '—'}</td>
                                        <td className="px-4 py-2.5">
                                            {b.movesOn ? (
                                                <Badge tone="blue">Emailed · moves {b.movesOn}</Badge>
                                            ) : b.moveTo ? (
                                                <Badge tone="grey">Not told yet</Badge>
                                            ) : (
                                                <Badge tone="amber">Set plan in Businesses</Badge>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {s.scheduled > 0 && <p className="text-sm text-muted">{s.scheduled} already emailed.</p>}
                    {s.corporate > 0 && (
                        <p className="text-sm text-muted">
                            {s.corporate} {s.corporate === 1 ? 'has' : 'have'} more employees than the largest plan: agree a Corporate price with them and set it in Businesses.
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
                title={`Move ${s.waiting} subscriber${s.waiting === 1 ? '' : 's'}?`}
                confirmLabel="Email them now"
                processing={processing}
                onClose={() => setConfirming(false)}
                onConfirm={move}
            >
                Each business admin is emailed today with their new plan, price and the date it starts ({s.moveOn}). They can cancel any time before then.
            </ConfirmDialog>
        </Card>
    );
}
