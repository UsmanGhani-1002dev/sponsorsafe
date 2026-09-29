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
import { router, useForm } from '@inertiajs/react';
import { History, Mail, Pencil } from 'lucide-react';
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
    };
    sections: { title: string; fields: { label: string; value: string; badge: BadgeData }[] }[];
    history: { id: number; date: string; label: string; from: string | null; to: string | null; by: string; reportable: boolean }[];
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
    const [tab, setTab] = useState(() => (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'history' ? 'history' : 'details'));
    const [confirmInvite, setConfirmInvite] = useState(false);
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
                        <Badge tone={employee.expiry.tone}>Expiry: {employee.expiry.text}</Badge>
                        <Badge tone={portalBadge[employee.portal].tone}>{portalBadge[employee.portal].text}</Badge>
                    </div>
                </div>
                {!employee.left && (
                    <Button variant="secondary" onClick={() => setConfirmInvite(true)}>
                        <Mail size={16} aria-hidden /> {inviteLabel}
                    </Button>
                )}
            </Card>

            <ConfirmDialog open={confirmInvite} title={inviteLabel} confirmLabel="Send email" processing={sending} onConfirm={invite} onClose={() => setConfirmInvite(false)}>
                We will email {employee.email} a link to set their password. It works once and expires in 7 days. Any earlier link stops working.
            </ConfirmDialog>

            <Tabs
                label="Employee record"
                active={tab}
                onChange={changeTab}
                tabs={[
                    { id: 'details', label: 'Details' },
                    { id: 'check', label: 'Compliance check', soon: true },
                    { id: 'docs', label: 'Documents', soon: true },
                    { id: 'absence', label: 'Absence', soon: true },
                    { id: 'reports', label: 'Home Office', soon: true },
                    { id: 'history', label: 'History' },
                ]}
            />

            <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
                {tab === 'details' ? <Details {...props} /> : <HistoryTab {...props} />}
            </div>
        </AppLayout>
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
    if (type.reportable) hint = { text: `Sponsored worker: this change must be reported on the Sponsor Management System within ${reportDeadlineDays} working days.`, tone: 'warning' };
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
                                        <td className="px-5 py-3.5">{h.reportable ? <Badge tone="amber">Report on SMS</Badge> : <Badge tone="grey">Not reportable</Badge>}</td>
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
