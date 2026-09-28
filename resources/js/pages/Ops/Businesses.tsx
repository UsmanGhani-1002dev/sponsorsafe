import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import OpsLayout from '@/layouts/ops-layout';
import { router } from '@inertiajs/react';

interface Row {
    id: number;
    name: string;
    status: 'active' | 'suspended';
    admin: { name: string; email: string } | null;
    employees: number;
    limit: number;
    price: number;
    payment: string | null;
    next_payment: string | null;
    joined: string;
}

export default function Businesses({ base, businesses, stats }: { base: string; businesses: Row[]; stats: { active: number; suspended: number; revenue: number; employees: number } }) {
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
                            <tr key={b.id} className="border-t border-slate-100">
                                <td className="px-5 py-3.5">
                                    <p className="font-semibold">{b.name}</p>
                                    <p className="text-xs text-muted">Joined {b.joined}</p>
                                </td>
                                <td className="px-5 py-3.5">
                                    <p>{b.admin?.name ?? '—'}</p>
                                    <p className="text-xs text-muted">{b.admin?.email}</p>
                                </td>
                                <td className="px-5 py-3.5 font-mono">
                                    {b.employees} / {b.limit}
                                </td>
                                <td className="px-5 py-3.5">{b.payment ?? '—'}</td>
                                <td className="px-5 py-3.5">{b.next_payment ?? '—'}</td>
                                <td className="px-5 py-3.5">
                                    <Badge tone={b.status === 'active' ? 'green' : 'red'}>{b.status === 'active' ? 'Active' : 'Suspended'}</Badge>
                                </td>
                                <td className="px-5 py-3.5 text-right">
                                    <button
                                        onClick={() => toggle(b)}
                                        className={
                                            b.status === 'active'
                                                ? 'min-h-9 rounded-lg border border-red-200 bg-white px-3 text-sm font-semibold text-red-700 hover:bg-red-50'
                                                : 'min-h-9 rounded-lg bg-accent px-3 text-sm font-semibold text-white hover:bg-accent-strong'
                                        }
                                    >
                                        {b.status === 'active' ? 'Suspend' : 'Activate'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
            <p className="mt-6 text-[13px] text-muted">Plans and pricing, payment gateways, enquiries and the AI assistant settings arrive in Stage 7.</p>
        </OpsLayout>
    );
}
