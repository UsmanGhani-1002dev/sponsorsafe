import { Alert } from '@/components/ui/alert';
import type { TaskRow } from '@/components/report-task';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/cn';
import { Link, router } from '@inertiajs/react';
import { CheckCircle2, ShieldCheck } from 'lucide-react';

interface Props {
    business: { name: string; employees: number; limit: number };
    stats: { pending: number; urgent: number; requests: number; expiring: number; employees: number };
    watchlist: { id: number; name: string; status: string; expiry: { text: string; tone: Tone }; unpaid: string }[];
    year: string;
    deadlines: TaskRow[];
    retentionDue: number;
    reminders: { key: string; text: string; tone: Tone; href: string }[];
    unexplained: { enabled: boolean; items: { id: number; name: string; date: string; badge: { text: string; tone: Tone } }[] };
}

export default function Dashboard({ business, stats, watchlist, year, deadlines, retentionDue, reminders, unexplained }: Props) {
    const tiles = [
        { label: 'Home Office reports pending', value: stats.pending, hint: 'Worker and company events', href: '/app/reports?status=pending', alert: false },
        { label: 'Due within 5 working days', value: stats.urgent, hint: 'Including overdue', href: '/app/reports?status=pending', alert: stats.urgent > 0 },
        { label: 'Employee requests', value: stats.requests, hint: 'Waiting for approval', href: '/app/requests', alert: stats.requests > 0 },
        { label: 'Visas expiring in 90 days', value: stats.expiring, hint: 'Follow-up right-to-work checks', href: '/app/employees?sort=expiry', alert: stats.expiring > 0 },
    ];

    return (
        <AppLayout title="Dashboard">
            <PageHeader title="Dashboard" description={`${business.name} · ${business.employees} of ${business.limit} employees on your plan`} />
            {retentionDue > 0 && (
                <div className="mb-5">
                    <Alert tone="warning">
                        {retentionDue === 1 ? "1 leaver's records are" : `${retentionDue} leavers' records are`} due for deletion.{" "}
                        <Link href="/app/retention" className="font-semibold underline">
                            Review them
                        </Link>
                    </Alert>
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {tiles.map((t) => {
                    const body = (
                        <>
                            <p className="text-sm text-muted">{t.label}</p>
                            <p className={cn('mt-1 font-mono text-3xl font-semibold', t.alert && (t.label.startsWith('Visas') || t.label.startsWith('Employee') ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300'))}>{t.value ?? '—'}</p>
                            <p className="mt-1 text-[13px] text-muted">{t.hint}</p>
                        </>
                    );
                    return t.href ? (
                        <Link key={t.label} href={t.href} prefetch className="block rounded-xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.05)] hover:border-line-strong">
                            {body}
                        </Link>
                    ) : (
                        <Card key={t.label} className="p-5">
                            {body}
                        </Card>
                    );
                })}
            </div>

            {(unexplained.enabled || unexplained.items.length > 0) && (
                <section aria-labelledby="unexplained-title" className="mt-8">
                    <h2 id="unexplained-title" className="mb-1 text-lg font-semibold">
                        Unexplained absences
                    </h2>
                    <p className="mb-3 text-sm text-ink-2">From the clock-in system: a scheduled working day with no clock-in and no absence recorded.</p>
                    {unexplained.items.length === 0 ? (
                        <Card className="px-5 py-4 text-[15px] text-ink-2">All clear: every scheduled day is accounted for.</Card>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {unexplained.items.map((u) => (
                                <li key={u.id}>
                                    <Card className="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                                        <div className="min-w-0">
                                            <p className="text-base font-semibold">
                                                {u.name} · {u.date}
                                            </p>
                                            <p className="flex flex-wrap items-center gap-2 text-sm text-muted">
                                                No clock-in, no absence recorded <Badge tone={u.badge.tone}>{u.badge.text}</Badge>
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Link href={`/app/absence/create?unexplained=${u.id}`} className="inline-flex min-h-11 items-center rounded-lg bg-accent-fill px-4 text-sm font-semibold text-white hover:bg-accent-fill-hover">
                                                Classify absence
                                            </Link>
                                            <Button variant="secondary" className="min-h-11 text-sm" onClick={() => router.post(`/app/unexplained/${u.id}/worked`, {}, { preserveScroll: true })}>
                                                Worked – clock-in missed
                                            </Button>
                                        </div>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            )}

            {reminders.length > 0 && (
                <section aria-labelledby="coming-title" className="mt-8">
                    <h2 id="coming-title" className="mb-1 text-lg font-semibold">
                        Coming up
                    </h2>
                    <p className="mb-3 text-sm text-ink-2">Expiries and checks to deal with. You also get each reminder by email.</p>
                    <Card className="overflow-hidden">
                        <ul>
                            {reminders.map((r) => (
                                <li key={r.key} className="border-t border-line first:border-t-0">
                                    <Link href={r.href} prefetch className="flex items-center gap-3 px-5 py-3 hover:bg-canvas">
                                        <span
                                            aria-hidden
                                            className={cn('size-2.5 shrink-0 rounded-full', r.tone === 'red' ? 'bg-red-600' : r.tone === 'amber' ? 'bg-amber-500' : 'bg-line-strong')}
                                        />
                                        <span className="min-w-0 flex-1 text-[15px]">{r.text}</span>
                                        <span className="sr-only">{r.tone === 'red' ? '(urgent)' : ''}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </section>
            )}

            <div className="mt-8 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <section aria-labelledby="deadlines-title">
                    <div className="mb-3 flex items-baseline justify-between">
                        <h2 id="deadlines-title" className="text-lg font-semibold">
                            Next Home Office deadlines
                        </h2>
                        <Link href="/app/reports" className="text-sm font-semibold text-accent hover:underline">
                            All reports
                        </Link>
                    </div>
                    <Card className="overflow-hidden">
                        {deadlines.length === 0 ? (
                            <EmptyState icon={CheckCircle2} title="Nothing to report right now" />
                        ) : (
                            <ul>
                                {deadlines.map((t) => (
                                    <li key={t.id} className="border-t border-line first:border-t-0">
                                        <Link href={`/app/reports?task=${t.id}`} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 hover:bg-canvas">
                                            <div className="min-w-0">
                                                <p className="font-semibold">{t.event}</p>
                                                <p className="text-[13px] text-muted">
                                                    {t.who} · deadline {t.deadline}
                                                </p>
                                            </div>
                                            <Badge tone={t.badge.tone}>{t.badge.text}</Badge>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </section>

                <section aria-labelledby="watch-title">
                    <h2 id="watch-title" className="mb-1 text-lg font-semibold">
                        Right-to-work watchlist
                    </h2>
                    <p className="mb-3 text-sm text-ink-2">Employees with time-limited permission. A follow-up check is needed before each expiry.</p>
                    <Card className="overflow-hidden">
                        {watchlist.length === 0 ? (
                            <EmptyState icon={ShieldCheck} title="Nobody has time-limited permission" />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-canvas text-[13px] font-semibold text-ink-2">
                                        <tr>
                                            <th scope="col" className="px-5 py-3">Employee</th>
                                            <th scope="col" className="px-5 py-3">Expiry</th>
                                            <th scope="col" className="px-5 py-3 whitespace-nowrap">Unpaid days {year}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {watchlist.map((w) => (
                                            <tr key={w.id} className="border-t border-line">
                                                <td className="px-5 py-3">
                                                    <Link href={`/app/employees/${w.id}`} prefetch className="font-semibold hover:text-accent hover:underline">
                                                        {w.name}
                                                    </Link>
                                                    <span className="block text-[13px] text-muted">{w.status}</span>
                                                </td>
                                                <td className="px-5 py-3">
                                                    <Badge tone={w.expiry.tone}>{w.expiry.text}</Badge>
                                                </td>
                                                <td className="px-5 py-3 font-mono">{w.unpaid}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </section>
            </div>
        </AppLayout>
    );
}
