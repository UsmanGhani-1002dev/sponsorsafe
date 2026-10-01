import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/cn';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, Loader2 } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

interface Props {
    employees: { id: number; name: string; sponsored: boolean }[];
    types: { value: string; label: string; pay: string }[];
    preselect: number | null;
    maxUploadMb: number;
    classify: { employeeId: number; date: string; note: string } | null;
}

interface Check {
    status: 'invalid' | 'none' | 'not_yet' | 'report';
    title: string;
    detail: string;
    days: number;
    trigger: string | null;
    deadline: string | null;
    warnings: string[];
}

const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const fmt = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

export default function RecordAbsence({ employees, types, preselect, maxUploadMb, classify }: Props) {
    const form = useForm<{ employee_id: string; type: string; start_date: string; end_date: string; reason: string; fit_note: File | null; return: string }>({
        employee_id: String(classify?.employeeId ?? preselect ?? employees[0]?.id ?? ''),
        type: classify ? 'unauthorised' : 'annual',
        start_date: classify?.date ?? today(),
        end_date: classify?.date ?? today(),
        reason: classify ? 'No clock-in and no contact' : '',
        fit_note: null,
        return: preselect ? 'profile' : '',
    });
    const { data, setData, errors } = form;
    const [check, setCheck] = useState<Check | null>(null);
    const [checking, setChecking] = useState(false);
    const type = types.find((t) => t.value === data.type) ?? types[0];
    const employee = employees.find((e) => String(e.id) === data.employee_id);

    // Live Home Office check: the same PHP rules that run when saving.
    useEffect(() => {
        if (!data.employee_id || !data.start_date || !data.end_date) return setCheck(null);
        const controller = new AbortController();
        const t = setTimeout(async () => {
            setChecking(true);
            try {
                const params = new URLSearchParams({ employee_id: data.employee_id, type: data.type, start_date: data.start_date, end_date: data.end_date });
                const res = await fetch(`/app/absence/check?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                setCheck(res.ok ? await res.json() : { status: 'invalid', title: 'Check the dates', detail: 'The last day must be on or after the first day.', days: 0, trigger: null, deadline: null, warnings: [] });
            } catch {
                // Superseded by a newer check.
            } finally {
                if (!controller.signal.aborted) setChecking(false);
            }
        }, 250);
        return () => {
            clearTimeout(t);
            controller.abort();
        };
    }, [data.employee_id, data.type, data.start_date, data.end_date]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/app/absence', { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppLayout title="Record absence">
            <PageHeader title="Record absence" back={classify ? { href: '/app', label: 'Dashboard' } : preselect ?{ href: `/app/employees/${preselect}?tab=absence`, label: 'Back to employee' } : { href: '/app/absence', label: 'Absence log' }} />

            {classify && (
                <div className="mb-5">
                    <Alert tone="info">{classify.note}</Alert>
                </div>
            )}
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                <Card className="p-5 sm:p-6">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <Field id="employee_id" label="Employee" error={errors.employee_id}>
                            <Select id="employee_id" value={data.employee_id} onChange={(e) => setData('employee_id', e.target.value)} invalid={!!errors.employee_id}>
                                {employees.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.name}
                                        {e.sponsored ? ' (sponsored)' : ''}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field id="type" label="Absence type" error={errors.type}>
                            <Select id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                {types.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="start_date" label="First day" error={errors.start_date}>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) => setData((d) => ({ ...d, start_date: e.target.value, end_date: d.end_date < e.target.value ? e.target.value : d.end_date }))}
                                    invalid={!!errors.start_date}
                                />
                            </Field>
                            <Field id="end_date" label="Last day" error={errors.end_date}>
                                <Input id="end_date" type="date" min={data.start_date} value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} invalid={!!errors.end_date} />
                            </Field>
                        </div>
                        <Field id="reason" label="Reason (short, no medical detail)" error={errors.reason} hint="For example: Family holiday. Do not write symptoms or diagnoses.">
                            <Input id="reason" maxLength={150} value={data.reason} onChange={(e) => setData('reason', e.target.value)} invalid={!!errors.reason} placeholder="e.g. Family holiday" />
                        </Field>
                        {data.type === 'sick_fit' && (
                            <Field id="fit_note" label="Fit note (optional now, you can add it later)" error={errors.fit_note} hint={`PDF, JPG or PNG, up to ${maxUploadMb} MB. File only: no medical details are stored.`}>
                                <input
                                    id="fit_note"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={(e) => setData('fit_note', e.target.files?.[0] ?? null)}
                                    className="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink-2 file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:font-semibold file:text-accent-strong"
                                />
                            </Field>
                        )}

                        <dl className="grid grid-cols-3 gap-3 rounded-lg bg-canvas p-3 text-sm">
                            <div>
                                <dt className="text-muted">Working days</dt>
                                <dd className="font-mono text-lg font-semibold">{check && check.status !== 'invalid' ? check.days : '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Pay</dt>
                                <dd className="font-medium">{type.pay}</dd>
                            </div>
                            <div>
                                <dt className="text-muted">Authorised</dt>
                                <dd className="font-medium">{data.type === 'unauthorised' ? 'No' : 'Yes'}</dd>
                            </div>
                        </dl>

                        <Button type="submit" disabled={form.processing || check?.status === 'invalid' || employees.length === 0}>
                            Save absence
                        </Button>
                    </form>
                </Card>

                <HomeOfficeCheck check={check} checking={checking} sponsored={employee?.sponsored ?? false} />
            </div>
        </AppLayout>
    );
}

function HomeOfficeCheck({ check, checking, sponsored }: { check: Check | null; checking: boolean; sponsored: boolean }) {
    const tone = !check
        ? 'border-line'
        : check.status === 'report' || check.status === 'invalid'
          ? 'border-red-300 dark:border-red-900'
          : check.status === 'not_yet'
            ? 'border-indigo-300 dark:border-indigo-900'
            : 'border-emerald-300 dark:border-emerald-900';
    const Icon = !check ? Clock : check.status === 'report' || check.status === 'invalid' ? AlertTriangle : check.status === 'not_yet' ? Clock : CheckCircle2;

    return (
        <Card className={cn('flex flex-col gap-3 border-2 p-5 sm:p-6 lg:sticky lg:top-6', tone)} aria-live="polite">
            <div className="flex items-center justify-between">
                <h2 className="text-[13px] font-semibold tracking-wide text-muted uppercase">Home Office check</h2>
                {checking && <Loader2 size={16} className="animate-spin text-muted" aria-label="Checking" />}
            </div>
            {!check ? (
                <p className="text-sm text-ink-2">Choose an employee and dates.</p>
            ) : (
                <>
                    <p className="flex items-center gap-2 text-lg font-semibold">
                        <Icon size={20} aria-hidden className={check.status === 'report' || check.status === 'invalid' ? 'text-red-600' : check.status === 'not_yet' ? 'text-accent' : 'text-emerald-600'} />
                        {check.title}
                    </p>
                    <p className="text-[15px] leading-relaxed text-ink-2">{check.detail}</p>
                    {check.status === 'report' && check.deadline && (
                        <div className="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/60 dark:text-red-200">
                            <p className="font-semibold">Deadline to report on the SMS: {fmt(check.deadline)}</p>
                            {check.trigger && <p>Counted in working days from {fmt(check.trigger)}, the day the threshold is reached.</p>}
                        </div>
                    )}
                    {check.warnings.map((w) => (
                        <Alert key={w} tone="warning">
                            {w}
                        </Alert>
                    ))}
                    {!sponsored && check.status !== 'invalid' && <p className="text-[13px] text-muted">Reporting duties apply to sponsored workers only.</p>}
                </>
            )}
        </Card>
    );
}
