import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Link } from '@inertiajs/react';

interface Props {
    business: { name: string; licence: string | null; employees: number; limit: number };
}

export default function Dashboard({ business }: Props) {
    const pct = Math.min(100, Math.round((business.employees / business.limit) * 100));
    return (
        <AppLayout title="Dashboard">
            <h1 className="mb-6 text-[26px] font-semibold">Dashboard</h1>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card className="p-5">
                    <p className="text-sm text-muted">Employees on your plan</p>
                    <p className="mt-1 font-mono text-3xl font-semibold">
                        {business.employees}
                        <span className="text-lg text-muted"> / {business.limit}</span>
                    </p>
                    <div className="mt-3 h-2 rounded bg-subtle" role="progressbar" aria-valuenow={business.employees} aria-valuemin={0} aria-valuemax={business.limit} aria-label="Employees used">
                        <div className="h-2 rounded bg-accent" style={{ width: `${pct}%` }} />
                    </div>
                </Card>
                <Card className="p-5">
                    <p className="text-sm text-muted">Sponsor licence number</p>
                    <p className="mt-1 text-lg font-semibold">{business.licence ?? 'Not added yet'}</p>
                </Card>
                <Card className="p-5">
                    <p className="text-sm text-muted">Home Office reports due</p>
                    <p className="mt-1 font-mono text-3xl font-semibold">0</p>
                    <p className="mt-1 text-[13px] text-muted">Report tracking is coming soon.</p>
                </Card>
            </div>
            <Card className="mt-6 p-6">
                <h2 className="text-lg font-semibold">{business.employees === 0 ? 'Next: add your employees' : 'Your employees'}</h2>
                <p className="mt-1 max-w-2xl text-[15px] text-ink-2">
                    {business.employees === 0
                        ? 'Add a work site under Settings, then add each person you employ. The form asks only for what their right-to-work basis needs.'
                        : 'Open an employee to see their right-to-work details and change history, or record a change.'}
                </p>
                <Link href="/app/employees" prefetch className="mt-4 inline-flex min-h-11 items-center rounded-lg bg-accent-fill px-4 text-[15px] font-semibold text-white hover:bg-accent-fill-hover">
                    {business.employees === 0 ? 'Add employees' : 'Go to Employees'}
                </Link>
            </Card>
        </AppLayout>
    );
}
