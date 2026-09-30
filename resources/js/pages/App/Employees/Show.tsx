import { Alert } from '@/components/ui/alert';
import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Field, Input, Select } from '@/components/ui/field';
import { PageHeader } from '@/components/ui/page-header';
import { Tabs } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { AbsenceTab } from '@/components/employee/absence-tab';
import { DocumentsTab, type DocumentCategoryRow } from '@/components/employee/documents-tab';
import type { AbsenceRow } from '@/components/absence';
import { ReportDialog, reopenTask, type TaskRow } from '@/components/report-task';
import { router, useForm } from '@inertiajs/react';
import { Download, FileCheck2, History, Mail, Pencil, UserX } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type BadgeData = { text: string; tone: Tone } | null;

interface Props {
    employee: {
        id: number;
        name: string;
        jobTitle: string;
        site: string | null;
        start: string;
        status: string;
        sponsored: boolean;
        expiry: { text: string; tone: Tone };
        portal: 'none' | 'invited' | 'active';
        email: string;
        left: boolean;
        leftText: string | null;
        startIso: string;
        documents: { have: number; need: number };
        homeOffice: { text: string; tone: Tone };
    };
    tasks: TaskRow[];
    compliance: {
        summary: { text: string; tone: Tone };
        rows: { key: string; label: string; detail: string; status: string; badge: { text: string; tone: Tone } }[];
    };
    endReasons: string[];
    reporter: string;
    today: string;
    documents: DocumentCategoryRow[];
    absence: {
        year: string;
        unpaid: { used: number; limit: string };
        annual: { allowance: string; taken: number; left: string };
        rows: AbsenceRow[];
    };
    upload: { maxMb: number; categories: { value: string; label: string }[] };
    sections: { title: string; fields: { label: string; value: string; badge: BadgeData }[] }[];
    history: { id: number; date: string; label: string; from: string | null; to: string | null; by: string; homeOffice: { text: string; tone: Tone }; taskId: number | null }[];
    waitingFor: { label: string; since: string }[];
    changeTypes: { value: string; label: string; field: string; reportable: boolean; current: string | null }[];
    personal: {
        values: Record<'full_name' | 'date_of_birth' | 'nationality' | 'ni_number' | 'passport_number' | 'passport_expiry', string>;
        masked: { ni_number: string | null; passport_number: string | null };
        nationalities: string[];
    };
    reportDeadlineDays: number;
}

const portalBadge: Record<Props['employee']['portal'], { text: string; tone: Tone }> = {
    active: { text: 'Portal: active', tone: 'green' },
    invited: { text: 'Portal: invited', tone: 'blue' },
    none: { text: 'Portal: no access', tone: 'grey' },
};

export default function ShowEmployee(props: Props) {
    const { employee } = props;
    const [tab, setTab] = useState(() => {
        const wanted = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
        return wanted && ['check', 'docs', 'absence', 'reports', 'history'].includes(wanted) ? wanted : 'details';
    });
    const [confirmInvite, setConfirmInvite] = useState(false);
    const [ending, setEnding] = useState(false);
    const [sending, setSending] = useState(false);

    const changeTab = (id: string) => {
        setTab(id);
        const url = new URL(window.location.href);
        if (id === 'details') url.searchParams.delete('tab');
        else url.searchParams.set('tab', id);
        window.history.replaceState(window.history.state, '', url);
    };

    const invite = () => {
        setSending(true);
        router.post(`/app/employees/${employee.id}/invite`, {}, { preserveScroll: true, preserveState: true, onFinish: () => (setSending(false), setConfirmInvite(false)) });
    };
    const inviteLabel = employee.portal === 'none' ? 'Give portal access' : employee.portal === 'invited' ? 'Resend portal invite' : 'Send set-password link';

    return (
        <AppLayout title={employee.name}>
            <PageHeader title={employee.name} back={{ href: '/app/employees', label: 'All employees' }} />

            <Card className="-mt-2 mb-6 flex flex-wrap items-center justify-between gap-5 p-5 sm:p-6">
                <div className="flex min-w-0 flex-col gap-1">
                    <p className="text-[15px] text-ink-2">
                        {employee.jobTitle} · {employee.site ?? 'No site'} · Started {employee.start}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <Badge tone={employee.sponsored ? 'blue' : 'grey'}>{employee.status}</Badge>
                        <Badge tone={employee.documents.have === employee.documents.need ? 'green' : 'amber'}>
                            Documents {employee.documents.have} of {employee.documents.need}
                        </Badge>
                        {!employee.left && <Badge tone={employee.expiry.tone}>Expiry: {employee.expiry.text}</Badge>}
                        <Badge tone={employee.homeOffice.tone}>{employee.homeOffice.text}</Badge>
                        <Badge tone={props.compliance.summary.tone}>{props.compliance.summary.text}</Badge>
                        {employee.left ? <Badge tone="grey">{employee.leftText}</Badge> : <Badge tone={portalBadge[employee.portal].tone}>{portalBadge[employee.portal].text}</Badge>}
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a
                        href={`/app/employees/${employee.id}/compliance-pack`}
                        className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-accent bg-surface px-4 text-[15px] font-semibold text-accent hover:bg-accent-soft"
                    >
                        <Download size={16} aria-hidden /> Export compliance pack (PDF)
                    </a>
                    {!employee.left && !ending && (
                        <>
                            <Button variant="secondary" onClick={() => setConfirmInvite(true)}>
                                <Mail size={16} aria-hidden /> {inviteLabel}
                            </Button>
                            <Button variant="danger" onClick={() => setEnding(true)}>
                                <UserX size={16} aria-hidden /> End employment
                            </Button>
                        </>
                    )}
                </div>
            </Card>

            {ending && <EndEmployment employee={employee} reasons={props.endReasons} today={props.today} onClose={() => setEnding(false)} />}

            <ConfirmDialog open={confirmInvite} title={inviteLabel} confirmLabel="Send email" processing={sending} onConfirm={invite} onClose={() => setConfirmInvite(false)}>
                We will email {employee.email} a link to set their password. It works once and expires in 7 days. Any earlier link stops working.
            </ConfirmDialog>

            <Tabs
                label="Employee record"
                active={tab}
                onChange={changeTab}
                tabs={[
                    { id: 'details', label: 'Details' },
                    { id: 'check', label: 'Compliance check' },
                    { id: 'docs', label: 'Documents' },
                    { id: 'absence', label: 'Absence' },
                    { id: 'reports', label: 'Home Office' },
                    { id: 'history', label: 'History' },
                ]}
            />

            <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
                {tab === 'details' && <Details {...props} />}
                {tab === 'check' && <ComplianceTab rows={props.compliance.rows} />}
                {tab === 'docs' && <DocumentsTab employeeId={employee.id} employeeName={employee.name} hasPortal={employee.portal !== 'none'} categories={props.documents} upload={props.upload} />}
                {tab === 'absence' && <AbsenceTab employeeId={employee.id} left={employee.left} absence={props.absence} />}
                {tab === 'reports' && <HomeOfficeTab tasks={props.tasks} reporter={props.reporter} today={props.today} />}
                {tab === 'history' && <HistoryTab {...props} />}
            </div>
        </AppLayout>
    );
}

/** End of employment (§10): last working day and reason. A sponsored worker gets a Home Office task. */
function EndEmployment({ employee, reasons, today, onClose }: { employee: Props['employee']; reasons: string[]; today: string; onClose: () => void }) {
    const form = useForm({ last_day: today, reason: reasons[0] });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/app/employees/${employee.id}/end`, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Card className="mb-6 border-2 border-red-300 p-5 sm:p-6 dark:border-red-900">
            <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                <h2 className="text-lg font-semibold">End employment for {employee.name}</h2>
                <div className="grid gap-4 sm:grid-cols-2 lg:max-w-2xl">
                    <Field id="end-day" label={form.data.reason === 'Did not start' ? 'Date they were due to start' : 'Last working day'} error={form.errors.last_day}>
                        <Input id="end-day" type="date" value={form.data.last_day} onChange={(e) => form.setData('last_day', e.target.value)} invalid={!!form.errors.last_day} />
                    </Field>
                    <Field id="end-reason" label="Reason" error={form.errors.reason}>
                        <Select id="end-reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)}>
                            {reasons.map((r) => (
                                <option key={r}>{r}</option>
                            ))}
                        </Select>
                    </Field>
                </div>
                <p className="max-w-3xl text-sm leading-relaxed text-ink-2">
                    {employee.sponsored
                        ? 'Sponsored worker: a Home Office report task is created, due 10 working days after this date. '
                        : 'Not a sponsored worker, so nothing is reported to the Home Office. '}
                    Their portal access is turned off, the records are kept for the retention period, and you will be reminded to give them their P45 and final payslip.
                </p>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" variant="danger" disabled={form.processing}>
                        Confirm end of employment
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                </div>
            </form>
        </Card>
    );
}

/** Compliance check tab (compliance-rules §8). */
function ComplianceTab({ rows }: { rows: Props['compliance']['rows'] }) {
    return (
        <div className="flex flex-col gap-3">
            <p className="text-[15px] text-ink-2">Everything the law and the sponsor duties expect for this employee. Fix anything marked Missing or Check.</p>
            <Card className="overflow-hidden">
                <ul>
                    {rows.map((r) => (
                        <li key={r.key} className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3.5 first:border-t-0">
                            <div className="min-w-0">
                                <p className="font-semibold">{r.label}</p>
                                <p className="text-[13px] text-ink-2">{r.detail}</p>
                            </div>
                            <Badge tone={r.badge.tone}>{r.badge.text}</Badge>
                        </li>
                    ))}
                </ul>
            </Card>
        </div>
    );
}

/** Home Office tab: this person's report tasks. */
function HomeOfficeTab({ tasks, reporter, today }: { tasks: TaskRow[]; reporter: string; today: string }) {
    const [marking, setMarking] = useState<TaskRow | null>(null);

    return (
        <>
            {tasks.length === 0 ? (
                <Card>
                    <EmptyState icon={FileCheck2} title="No Home Office reports for this employee" />
                </Card>
            ) : (
                <ul className="flex flex-col gap-3">
                    {tasks.map((t) => (
                        <li key={t.id}>
                            <Card className="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                                <div className="min-w-0">
                                    <p className="text-[15px] font-semibold">{t.event}</p>
                                    <p className="text-[13px] text-muted">
                                        Triggered {t.trigger} · deadline {t.deadline} · from {t.source}
                                    </p>
                                    {t.done && <p className="text-[13px] text-ink-2">{t.done}</p>}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge tone={t.badge.tone}>{t.badge.text}</Badge>
                                    {t.pending ? (
                                        <Button className="min-h-10 px-3 text-sm" onClick={() => setMarking(t)}>
                                            Mark reported
                                        </Button>
                                    ) : (
                                        <Button variant="ghost" className="min-h-10 px-3 text-sm" onClick={() => reopenTask(t.id)}>
                                            Reopen
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
            <ReportDialog task={marking} reporter={reporter} today={today} onClose={() => setMarking(null)} />
        </>
    );
}

function Details({ employee, sections, waitingFor, personal }: Props) {
    const [correcting, setCorrecting] = useState(false);

    return (
        <div className="flex flex-col gap-4">
            {waitingFor.length > 0 && (
                <Alert tone="warning">
                    Waiting for {employee.name.split(' ')[0]} to upload: {waitingFor.map((w) => `${w.label} (requested ${w.since})`).join(', ')}.
                </Alert>
            )}
            <div className="grid items-start gap-4 lg:grid-cols-2">
                {sections.map((s) => (
                    <Card key={s.title} className="flex flex-col p-5 sm:p-6">
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <h2 className="text-[17px] font-semibold">{s.title}</h2>
                            {s.title === 'Personal and contact' && !correcting && (
                                <Button variant="ghost" className="min-h-10 px-2 text-sm" onClick={() => setCorrecting(true)}>
                                    <Pencil size={15} aria-hidden /> Correct details
                                </Button>
                            )}
                        </div>
                        {s.title === 'Personal and contact' && correcting ? (
                            <CorrectForm employeeId={employee.id} personal={personal} onDone={() => setCorrecting(false)} />
                        ) : (
                            <dl>
                                {s.fields.map((f) => (
                                    <div key={f.label} className="grid gap-1 border-t border-line py-2.5 text-sm sm:grid-cols-[190px_minmax(0,1fr)] sm:gap-3">
                                        <dt className="text-muted">{f.label}</dt>
                                        <dd className="flex flex-wrap items-center gap-2 font-medium">
                                            {f.value}
                                            {f.badge && <Badge tone={f.badge.tone}>{f.badge.text}</Badge>}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </Card>
                ))}
            </div>
        </div>
    );
}

/** Fix a typo in personal details. Logged in the history; never reportable. */
function CorrectForm({ employeeId, personal, onDone }: { employeeId: number; personal: Props['personal']; onDone: () => void }) {
    const form = useForm(personal.values);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/app/employees/${employeeId}/personal`, { preserveScroll: true, preserveState: true, onSuccess: onDone });
    };
    const text = (name: keyof typeof personal.values, label: string, type = 'text', hint?: string) => (
        <Field id={`c-${name}`} label={label} error={form.errors[name]} hint={hint}>
            <Input id={`c-${name}`} type={type} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} invalid={!!form.errors[name]} />
        </Field>
    );

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4 border-t border-line pt-4">
            <p className="text-sm text-ink-2">For correcting mistakes. Address, phone and email changes go under History → Record a change.</p>
            {text('full_name', 'Full legal name')}
            <div className="grid gap-4 sm:grid-cols-2">
                {text('date_of_birth', 'Date of birth', 'date')}
                <Field id="c-nationality" label="Nationality" error={form.errors.nationality}>
                    <Select id="c-nationality" value={form.data.nationality} onChange={(e) => form.setData('nationality', e.target.value)}>
                        <option value="">Not recorded</option>
                        {personal.nationalities.map((n) => (
                            <option key={n}>{n}</option>
                        ))}
                    </Select>
                </Field>
                {text('ni_number', 'National Insurance number', 'text', personal.masked.ni_number ? `On file: ${personal.masked.ni_number}. Leave blank to keep it.` : undefined)}
                {text('passport_number', 'Passport number', 'text', personal.masked.passport_number ? `On file: ${personal.masked.passport_number}. Leave blank to keep it.` : undefined)}
                {text('passport_expiry', 'Passport expiry', 'date')}
            </div>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing}>
                    Save corrections
                </Button>
                <Button type="button" variant="secondary" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

function HistoryTab({ employee, history, changeTypes, reportDeadlineDays }: Props) {
    const form = useForm({ type: changeTypes[0]?.value ?? '', value: '' });
    const type = changeTypes.find((t) => t.value === form.data.type) ?? changeTypes[0];
    const numeric = type.field === 'salary' || type.field === 'contracted_hours';

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/app/employees/${employee.id}/changes`, { preserveScroll: true, preserveState: true, onSuccess: () => form.reset('value') });
    };

    let hint: { text: string; tone: 'warning' | 'info' };
    if (type.reportable) hint = { text: `Sponsored worker: saving creates a Home Office report task, due in ${reportDeadlineDays} working days.`, tone: 'warning' };
    else if (employee.sponsored && type.value === 'salary_increase') hint = { text: 'Salary increase: logged only, not reportable.', tone: 'info' };
    else hint = { text: 'Not reportable. It is logged in the history.', tone: 'info' };

    return (
        <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <Card className="overflow-hidden">
                {history.length === 0 ? (
                    <EmptyState icon={History} title="No changes recorded yet" />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas text-[13px] font-semibold text-ink-2">
                                <tr>
                                    <th scope="col" className="px-5 py-3">Date</th>
                                    <th scope="col" className="px-5 py-3">Change</th>
                                    <th scope="col" className="px-5 py-3">From</th>
                                    <th scope="col" className="px-5 py-3">To</th>
                                    <th scope="col" className="px-5 py-3">Home Office</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((h) => (
                                    <tr key={h.id} className="border-t border-line align-top">
                                        <td className="px-5 py-3.5 whitespace-nowrap">{h.date}</td>
                                        <td className="px-5 py-3.5">
                                            <span className="block font-semibold">{h.label}</span>
                                            <span className="text-[13px] text-muted">by {h.by}</span>
                                        </td>
                                        <td className="px-5 py-3.5 text-muted">{h.from ?? '—'}</td>
                                        <td className="px-5 py-3.5">{h.to ?? '—'}</td>
                                        <td className="px-5 py-3.5"><Badge tone={h.homeOffice.tone}>{h.homeOffice.text}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {!employee.left && (
                <Card className="p-5 sm:p-6">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <h2 className="text-[17px] font-semibold">Record a change</h2>
                        <Field id="change-type" label="What changed" error={form.errors.type}>
                            <Select id="change-type" value={form.data.type} onChange={(e) => form.setData({ type: e.target.value, value: '' })}>
                                {changeTypes.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <p className="text-[13px] text-ink-2">
                            Current: <strong>{type.current ?? 'Not recorded'}</strong>
                        </p>
                        <Field id="change-value" label="New value" error={form.errors.value}>
                            <Input id="change-value" type={type.field === 'email' ? 'email' : 'text'} inputMode={numeric ? 'decimal' : undefined} value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} invalid={!!form.errors.value} />
                        </Field>
                        <Alert tone={hint.tone}>{hint.text}</Alert>
                        <Button type="submit" disabled={form.processing}>
                            Save change
                        </Button>
                        <p className="text-[13px] text-muted">To move someone to another work site, use Settings → Work sites.</p>
                    </form>
                </Card>
            )}
        </div>
    );
}
