import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Field, Input, Select } from '@/components/ui/field';
import OpsLayout from '@/layouts/ops-layout';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Row {
    id: number;
    name: string;
    status: 'active' | 'suspended' | 'pending';
    suspendedReason: 'manual' | 'payment' | 'cancelled' | null;
    graceEnds: string | null;
    admin: { name: string; email: string } | null;
    employees: number;
    limit: number;
    plan: string | null;
    planName: string;
    price: number;
    payment: string | null;
    next_payment: string | null;
    joined: string;
}

const reasons = { manual: 'by you', payment: 'not paid', cancelled: 'cancelled' } as const;

function Status({ b }: { b: Row }) {
    if (b.status === 'pending') return <Badge tone="blue">Awaiting payment</Badge>;
    if (b.status === 'suspended') return <Badge tone="red">Suspended{b.suspendedReason ? ` · ${reasons[b.suspendedReason]}` : ''}</Badge>;
    if (b.graceEnds) return <Badge tone="amber">Payment failed · until {b.graceEnds}</Badge>;
    return <Badge tone="green">Active</Badge>;
}

interface Tier {
    key: string;
    name: string;
    price: string;
    limit: number;
}

/** Starter / Standard at today's price, or a Corporate package with the agreed price and limit. */
function SetPlan({ base, business, tiers, onClose }: { base: string; business: Row | null; tiers: Tier[]; onClose: () => void }) {
    const form = useForm({ plan: 'corporate', price: '', limit: '' });
    useEffect(() => {
        if (business) form.setData({ plan: business.plan ?? 'corporate', price: business.plan === 'corporate' ? String(business.price) : '', limit: business.plan === 'corporate' ? String(business.limit) : '' });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [business?.id]);
    const corporate = form.data.plan === 'corporate';

    return (
        <ConfirmDialog
            open={business !== null}
            title={`Set plan for ${business?.name ?? ''}`}
            confirmLabel="Save plan"
            processing={form.processing}
            onClose={onClose}
            onConfirm={() => form.post(`${base}/businesses/${business?.id}/plan`, { preserveScroll: true, onSuccess: onClose })}
        >
            <div className="flex flex-col gap-4">
                <Field id="sp-plan" label="Plan" error={form.errors.plan}>
                    <Select id="sp-plan" value={form.data.plan} onChange={(e) => form.setData('plan', e.target.value)}>
                        {tiers.map((t) => (
                            <option key={t.key} value={t.key}>
                                {t.name} · £{t.price} · up to {t.limit}
                            </option>
                        ))}
                        <option value="corporate">Corporate · agreed price</option>
                    </Select>
                </Field>
                {corporate && (
                    <div className="grid grid-cols-2 gap-3">
                        <Field id="sp-price" label="Monthly price (£)" error={form.errors.price}>
                            <Input id="sp-price" inputMode="decimal" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} invalid={!!form.errors.price} />
                        </Field>
                        <Field id="sp-limit" label="Up to (employees)" error={form.errors.limit}>
                            <Input id="sp-limit" inputMode="numeric" value={form.data.limit} onChange={(e) => form.setData('limit', e.target.value)} invalid={!!form.errors.limit} />
                        </Field>
                    </div>
                )}
                {!corporate && form.errors.limit && <p className="text-sm text-red-700 dark:text-red-300">{form.errors.limit}</p>}
                <p className="text-sm text-muted">The employee limit changes now. Card payments move to the new price from their next payment.</p>
            </div>
        </ConfirmDialog>
    );
}

export default function Businesses({ base, tiers, businesses, stats }: { base: string; tiers: Tier[]; businesses: Row[]; stats: { active: number; suspended: number; revenue: number; employees: number } }) {
    const [planFor, setPlanFor] = useState<Row | null>(null);
    const toggle = (b: Row) => {
        if (b.status === 'active' && !confirm(`Suspend ${b.name}? Their admins and employees will be signed out.`)) return;
        router.post(`${base}/businesses/${b.id}/toggle`, {}, { preserveScroll: true });
    };
    const tiles = [
        ['Active businesses', stats.active],
        ['Suspended', stats.suspended],
        ['Monthly revenue', `£${stats.revenue}`],
        ['Employees managed', stats.employees],
    ];
    return (
        <OpsLayout title="Businesses" base={base}>
            <h1 className="mb-5 text-[26px] font-semibold">Businesses</h1>
            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                {tiles.map(([label, value]) => (
                    <Card key={label} className="p-4">
                        <p className="text-[13px] text-muted">{label}</p>
                        <p className="text-2xl font-semibold">{value}</p>
                    </Card>
                ))}
            </div>
            <Card className="overflow-x-auto">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="bg-canvas text-[13px] text-ink-2">
                        <tr>
                            {['Business', 'Admin', 'Staff', 'Payment', 'Next due', 'Status', ''].map((h) => (
                                <th key={h} scope="col" className="px-5 py-3 font-semibold">
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {businesses.map((b) => (
                            <tr key={b.id} className="border-t border-line">
                                <td className="px-5 py-3.5">
                                    <p className="font-semibold">{b.name}</p>
                                    <p className="text-xs text-muted">Joined {b.joined}</p>
                                </td>
                                <td className="px-5 py-3.5">
                                    <p>{b.admin?.name ?? '—'}</p>
                                    <p className="text-xs text-muted">{b.admin?.email}</p>
                                </td>
                                <td className="px-5 py-3.5">
                                    <p className="font-mono">
                                        {b.employees} / {b.limit}
                                    </p>
                                    <p className="text-xs text-muted">
                                        {b.planName} · £{b.price}
                                    </p>
                                </td>
                                <td className="px-5 py-3.5">{b.payment ?? '—'}</td>
                                <td className="px-5 py-3.5">{b.next_payment ?? '—'}</td>
                                <td className="px-5 py-3.5">
                                    <Status b={b} />
                                </td>
                                <td className="space-x-2 px-5 py-3.5 text-right whitespace-nowrap">
                                    {b.status !== 'pending' && (
                                        <button onClick={() => setPlanFor(b)} className="min-h-9 rounded-lg border border-line-strong bg-surface px-3 text-sm font-semibold text-ink-2 hover:bg-canvas">
                                            Set plan
                                        </button>
                                    )}
                                    {b.status !== 'pending' && (
                                        <button
                                            onClick={() => toggle(b)}
                                            className={
                                                b.status === 'active'
                                                    ? 'min-h-9 rounded-lg border border-red-200 bg-surface px-3 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/60'
                                                    : 'min-h-9 rounded-lg bg-accent-fill px-3 text-sm font-semibold text-white hover:bg-accent-fill-hover'
                                            }
                                        >
                                            {b.status === 'active' ? 'Suspend' : 'Activate'}
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
            <p className="mt-4 text-[13px] text-muted">A business suspended for non-payment reopens by itself when payment arrives. One you suspend by hand stays suspended until you activate it.</p>
            <SetPlan base={base} business={planFor} tiers={tiers} onClose={() => setPlanFor(null)} />
        </OpsLayout>
    );
}
