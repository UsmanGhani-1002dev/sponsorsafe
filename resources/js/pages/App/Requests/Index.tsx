import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/field';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/cn';
import { Link, useForm } from '@inertiajs/react';
import { FileText, Inbox } from 'lucide-react';

interface Row {
    id: number;
    employeeId: number;
    who: string;
    kind: string;
    summary: string;
    sent: string;
    note: string | null;
    documentId: number | null;
    status: { text: string; tone: Tone };
    hrNote: string | null;
}

interface Props {
    waiting: (Row & { preview: { text: string; tone: Tone } | null })[];
    awaiting: { id: number; employeeId: number; who: string; summary: string; since: string }[];
    decided: Row[];
}

const previewTone: Record<Tone, string> = {
    red: 'bg-red-50 text-red-800 dark:bg-red-950/60 dark:text-red-200',
    amber: 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
    green: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200',
    blue: 'bg-accent-soft text-accent-strong',
    grey: 'bg-canvas text-ink-2',
};

export default function RequestsInbox({ waiting, awaiting, decided }: Props) {
    return (
        <AppLayout title="Employee requests">
            <PageHeader title="Employee requests" description="Sent from the employee portal. Approving updates the record, adds absences and creates Home Office tasks where needed." />

            <h2 className="mb-3 text-lg font-semibold">Waiting for you</h2>
            {waiting.length === 0 ? (
                <Card>
                    <EmptyState icon={Inbox} title="No requests waiting" />
                </Card>
            ) : (
                <ul className="flex flex-col gap-3">
                    {waiting.map((r) => (
                        <WaitingRequest key={r.id} request={r} />
                    ))}
                </ul>
            )}

            <h2 className="mt-10 mb-3 text-lg font-semibold">Waiting for employees</h2>
            <Card className="overflow-hidden">
                {awaiting.length === 0 ? (
                    <p className="p-5 text-sm text-ink-2">Nothing outstanding.</p>
                ) : (
                    <ul>
                        {awaiting.map((a) => (
                            <li key={a.id} className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3 first:border-t-0">
                                <div>
                                    <Link href={`/app/employees/${a.employeeId}?tab=docs`} className="font-semibold hover:text-accent hover:underline">
                                        {a.who}
                                    </Link>{' '}
                                    · {a.summary}
                                    <p className="text-[13px] text-muted">Requested {a.since}</p>
                                </div>
                                <Badge tone="amber">Waiting for upload</Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <h2 className="mt-10 mb-3 text-lg font-semibold">Recently decided</h2>
            <Card className="overflow-hidden">
                {decided.length === 0 ? (
                    <p className="p-5 text-sm text-ink-2">Nothing decided yet.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas text-[13px] font-semibold text-ink-2">
                                <tr>
                                    <th scope="col" className="px-5 py-3">Employee</th>
                                    <th scope="col" className="px-5 py-3">Request</th>
                                    <th scope="col" className="px-5 py-3">Status</th>
                                    <th scope="col" className="px-5 py-3">Note to employee</th>
                                </tr>
                            </thead>
                            <tbody>
                                {decided.map((r) => (
                                    <tr key={r.id} className="border-t border-line align-top">
                                        <td className="px-5 py-3 font-medium whitespace-nowrap">{r.who}</td>
                                        <td className="px-5 py-3">
                                            {r.kind} · {r.summary}
                                        </td>
                                        <td className="px-5 py-3">
                                            <Badge tone={r.status.tone}>{r.status.text}</Badge>
                                        </td>
                                        <td className="px-5 py-3 text-ink-2">{r.hrNote ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}

function WaitingRequest({ request: r }: { request: Props['waiting'][number] }) {
    const form = useForm({ hr_note: '' });
    const opts = { preserveScroll: true } as const;
    const blocked = r.preview?.text.startsWith('Cannot be approved');

    return (
        <li>
            <Card className="flex flex-col gap-3 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[13px] text-muted">
                            <Link href={`/app/employees/${r.employeeId}`} className="font-semibold text-ink hover:text-accent hover:underline">
                                {r.who}
                            </Link>{' '}
                            · {r.kind} · sent {r.sent}
                        </p>
                        <p className="mt-0.5 text-[15px] font-semibold">{r.summary}</p>
                        {r.note && <p className="mt-1 text-sm text-ink-2">Employee note: {r.note}</p>}
                    </div>
                    {r.documentId && (
                        <a href={`/app/documents/${r.documentId}`} target="_blank" rel="noopener" className="inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-accent hover:underline">
                            <FileText size={16} aria-hidden /> View file
                        </a>
                    )}
                </div>
                {r.preview && <p className={cn('rounded-lg px-3 py-2 text-sm', previewTone[r.preview.tone])}>{r.preview.text}</p>}
                <div className="flex flex-wrap items-end gap-2">
                    <label className="flex min-w-[240px] flex-1 flex-col gap-1 text-[13px] font-medium text-ink-2">
                        Note to the employee (optional)
                        <Input value={form.data.hr_note} maxLength={300} onChange={(e) => form.setData('hr_note', e.target.value)} placeholder="e.g. Enjoy your holiday" />
                    </label>
                    <Button disabled={form.processing || blocked} onClick={() => form.post(`/app/requests/${r.id}/approve`, opts)}>
                        Approve
                    </Button>
                    <Button variant="danger" disabled={form.processing} onClick={() => form.post(`/app/requests/${r.id}/decline`, opts)}>
                        Decline
                    </Button>
                </div>
            </Card>
        </li>
    );
}
