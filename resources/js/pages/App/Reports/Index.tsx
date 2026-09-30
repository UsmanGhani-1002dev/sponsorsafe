import { DataTable, type Column, type Page, type TableState } from '@/components/data-table';
import { ReportDialog, reopenTask, type TaskRow } from '@/components/report-task';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Field, Input, Select } from '@/components/ui/field';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { Link, useForm } from '@inertiajs/react';
import { FileCheck2, Plus } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

interface Props {
    tasks: Page<TaskRow>;
    table: TableState;
    employees: { value: string; label: string }[];
    events: { worker: string[]; company: string[] };
    reporter: string;
    today: string;
    openTask: number | null;
}

export default function ReportsIndex({ tasks, table, employees, events, reporter, today, openTask }: Props) {
    const [marking, setMarking] = useState<TaskRow | null>(() => tasks.data.find((t) => t.id === openTask && t.pending) ?? null);
    const [creating, setCreating] = useState(false);
    const filtered = table.q !== '' || Object.values(table.filters).some((v) => v !== null);

    const columns: Column<TaskRow>[] = [
        {
            key: 'event',
            label: 'Event',
            render: (t) => (
                <div className="flex min-w-[220px] flex-col">
                    <span className="font-semibold">{t.event}</span>
                    <span className="text-[13px] text-muted">From: {t.source}</span>
                </div>
            ),
        },
        {
            key: 'who',
            label: 'Who',
            render: (t) =>
                t.employeeId ? (
                    <Link href={`/app/employees/${t.employeeId}?tab=reports`} prefetch className="font-medium hover:text-accent hover:underline">
                        {t.who}
                    </Link>
                ) : (
                    <span className="text-ink-2">{t.who}</span>
                ),
        },
        { key: 'trigger', label: 'Triggered', sortable: true, className: 'whitespace-nowrap', render: (t) => t.trigger },
        { key: 'deadline', label: 'Deadline', sortable: true, className: 'whitespace-nowrap', render: (t) => <span className="font-medium">{t.deadline}</span> },
        {
            key: 'status',
            label: 'Status',
            render: (t) => (
                <div className="flex flex-col gap-1">
                    <Badge tone={t.badge.tone} className="w-fit">
                        {t.badge.text}
                    </Badge>
                    {t.done && <span className="text-[13px] text-ink-2">{t.done}</span>}
                </div>
            ),
        },
        {
            key: 'action',
            label: 'Action',
            render: (t) =>
                t.pending ? (
                    <Button className="min-h-10 px-3 text-sm whitespace-nowrap" onClick={() => setMarking(t)}>
                        Mark reported
                    </Button>
                ) : (
                    <Button variant="ghost" className="min-h-10 px-3 text-sm" onClick={() => reopenTask(t.id)}>
                        Reopen
                    </Button>
                ),
        },
    ];

    return (
        <AppLayout title="Home Office reports">
            <PageHeader
                title="Home Office reports"
                description="Created automatically by absences, employee changes, leavers, work sites and key personnel. Report on the Sponsor Management System, then tick it here."
                actions={
                    <Button onClick={() => setCreating(true)}>
                        <Plus size={18} aria-hidden /> Create Home Office report
                    </Button>
                }
            />
            <DataTable
                url="/app/reports"
                only={['tasks', 'table']}
                page={tasks}
                state={table}
                columns={columns}
                searchLabel="Search by event or employee"
                filters={[
                    {
                        name: 'status',
                        label: 'Show',
                        fallback: 'all',
                        options: [
                            { value: 'all', label: 'All (pending first)' },
                            { value: 'pending', label: 'Pending' },
                            { value: 'done', label: 'Reported or not required' },
                        ],
                    },
                    {
                        name: 'level',
                        label: 'Level',
                        allLabel: 'Worker and company',
                        options: [
                            { value: 'worker', label: 'Worker' },
                            { value: 'company', label: 'Company' },
                        ],
                    },
                ]}
                empty={
                    <EmptyState icon={FileCheck2} title={filtered ? 'No reports match' : 'Nothing to report'}>
                        {filtered ? 'Try a different search or filter.' : 'Tasks appear here when an absence, change or new work site needs reporting.'}
                    </EmptyState>
                }
            />

            <ReportDialog task={marking} reporter={reporter} today={today} onClose={() => setMarking(null)} />
            <CreateDialog open={creating} employees={employees} events={events} today={today} onClose={() => setCreating(false)} />
        </AppLayout>
    );
}

/** "Create Home Office report" for events the rules cannot see. */
function CreateDialog({ open, employees, events, today, onClose }: { open: boolean; employees: Props['employees']; events: Props['events']; today: string; onClose: () => void }) {
    const ref = useRef<HTMLDialogElement>(null);
    const form = useForm({ level: 'worker' as 'worker' | 'company', employee_id: employees[0]?.value ?? '', event: events.worker[0], details: '', trigger_on: today });

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    const setLevel = (level: 'worker' | 'company') => form.setData((d) => ({ ...d, level, event: events[level][0] }));
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/reports', { preserveScroll: true, preserveState: true, onSuccess: () => (form.reset('details'), onClose()) });
    };

    return (
        <dialog ref={ref} onClose={onClose} aria-labelledby="create-title" className="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-line bg-surface p-0 text-ink shadow-xl backdrop:bg-black/40">
            <form onSubmit={submit} noValidate className="flex flex-col gap-4 p-6">
                <div>
                    <h2 id="create-title" className="text-lg font-semibold">
                        Create Home Office report
                    </h2>
                    <p className="mt-1 text-sm text-ink-2">For events the automatic checks cannot see. The deadline is worked out for you.</p>
                </div>
                <fieldset className="flex gap-4">
                    <legend className="mb-1.5 text-sm font-medium text-ink-2">About</legend>
                    {(['worker', 'company'] as const).map((l) => (
                        <label key={l} className="flex min-h-10 items-center gap-2 text-sm">
                            <input type="radio" name="level" className="size-4 accent-[#4F46E5]" checked={form.data.level === l} onChange={() => setLevel(l)} />
                            {l === 'worker' ? 'A sponsored worker' : 'The business'}
                        </label>
                    ))}
                </fieldset>
                {form.data.level === 'worker' && (
                    <Field id="c-employee" label="Sponsored worker" error={form.errors.employee_id}>
                        {employees.length ? (
                            <Select id="c-employee" value={form.data.employee_id} onChange={(e) => form.setData('employee_id', e.target.value)} invalid={!!form.errors.employee_id}>
                                {employees.map((e) => (
                                    <option key={e.value} value={e.value}>
                                        {e.label}
                                    </option>
                                ))}
                            </Select>
                        ) : (
                            <p className="text-sm text-muted">You have no sponsored workers.</p>
                        )}
                    </Field>
                )}
                <Field id="c-event" label="What happened" error={form.errors.event}>
                    <Select id="c-event" value={form.data.event} onChange={(e) => form.setData('event', e.target.value)}>
                        {events[form.data.level].map((ev) => (
                            <option key={ev}>{ev}</option>
                        ))}
                    </Select>
                </Field>
                <Field id="c-details" label="Details (optional)" error={form.errors.details} hint="Short; no personal or medical detail.">
                    <Input id="c-details" maxLength={120} value={form.data.details} onChange={(e) => form.setData('details', e.target.value)} />
                </Field>
                <Field id="c-date" label="Date it happened" error={form.errors.trigger_on}>
                    <Input id="c-date" type="date" max={today} value={form.data.trigger_on} onChange={(e) => form.setData('trigger_on', e.target.value)} invalid={!!form.errors.trigger_on} />
                </Field>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Create task
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                </div>
            </form>
        </dialog>
    );
}
