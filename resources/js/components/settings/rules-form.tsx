import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export interface RulesProps {
    values: {
        unpaid_limit_weeks: string;
        unpaid_leave_year: string;
        unauthorised_trigger_days: string;
        worker_report_deadline_days: string;
        company_report_deadline_days: string;
        self_cert_max_days: string;
        payslip_freshness_days: string;
        retention_years: string;
        rtw_retention_years: string;
        annual_leave_weeks: string;
        exempt_absence_types: string[];
        expiry_alert_days: string;
    };
    reducedPayTypes: { value: string; label: string }[];
}

type Key = Exclude<keyof RulesProps['values'], 'exempt_absence_types' | 'unpaid_leave_year'>;

/** Compliance rule settings (compliance-rules §9). The defaults reflect sponsor guidance as understood in mid-2026. */
export function RulesForm({ values, reducedPayTypes }: RulesProps) {
    const form = useForm(values);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/app/settings/rules', { preserveScroll: true, preserveState: true });
    };
    const number = (name: Key, label: string, hint?: string, step = '1') => (
        <Field id={`r-${name}`} label={label} error={form.errors[name]} hint={hint}>
            <Input id={`r-${name}`} type="number" inputMode="decimal" step={step} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} invalid={!!form.errors[name]} />
        </Field>
    );
    const toggleExempt = (value: string, on: boolean) =>
        form.setData('exempt_absence_types', on ? [...form.data.exempt_absence_types, value] : form.data.exempt_absence_types.filter((v) => v !== value));

    return (
        <Card className="p-5 sm:p-6">
            <form onSubmit={submit} noValidate className="flex flex-col gap-6">
                <Alert tone="info">These defaults follow UK sponsor guidance as understood in mid-2026. Check them against the current gov.uk guidance before changing them.</Alert>

                <fieldset className="flex flex-col gap-4">
                    <legend className="mb-3 text-[15px] font-semibold">Absence</legend>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {number('unpaid_limit_weeks', 'Unpaid limit (weeks)', 'Unpaid and unauthorised days above this many weeks a year are reported.', '0.5')}
                        <Field id="r-unpaid_leave_year" label="Unpaid limit year" error={form.errors.unpaid_leave_year}>
                            <Select id="r-unpaid_leave_year" value={form.data.unpaid_leave_year} onChange={(e) => form.setData('unpaid_leave_year', e.target.value)}>
                                <option value="calendar">Calendar year (resets 1 January)</option>
                                <option value="rolling">Rolling 12 months</option>
                            </Select>
                        </Field>
                        {number('unauthorised_trigger_days', 'Unauthorised absence trigger', 'Consecutive working days before a report is needed.')}
                        {number('self_cert_max_days', 'Self-certified sickness (days)', 'Longer sickness needs a fit note.')}
                        {number('annual_leave_weeks', 'Annual leave (weeks, pro rata)', 'Capped at 28 days.', '0.1')}
                    </div>
                    <div>
                        <p className="text-sm font-medium text-ink-2">Leave on reduced pay that is not a reportable salary change</p>
                        <div className="mt-2 flex flex-col gap-1">
                            {reducedPayTypes.map((t) => (
                                <label key={t.value} className="flex min-h-10 items-center gap-2.5 text-sm">
                                    <input type="checkbox" className="size-4 accent-[#4F46E5]" checked={form.data.exempt_absence_types.includes(t.value)} onChange={(e) => toggleExempt(t.value, e.target.checked)} />
                                    {t.label}
                                </label>
                            ))}
                        </div>
                        {form.errors['exempt_absence_types.0' as keyof typeof form.errors] && <p className="text-[13px] text-red-700">Choose from the listed types.</p>}
                    </div>
                </fieldset>

                <fieldset className="flex flex-col gap-4">
                    <legend className="mb-3 text-[15px] font-semibold">Home Office deadlines (working days)</legend>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {number('worker_report_deadline_days', 'Worker events', 'For example absence, pay or job changes.')}
                        {number('company_report_deadline_days', 'Company events', 'For example a new work address.')}
                    </div>
                </fieldset>

                <fieldset className="flex flex-col gap-4">
                    <legend className="mb-3 text-[15px] font-semibold">Reminders and records</legend>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Field id="r-expiry_alert_days" label="Expiry alerts (days before)" error={form.errors.expiry_alert_days} hint="Separate with commas, e.g. 90, 60, 30.">
                            <Input id="r-expiry_alert_days" value={form.data.expiry_alert_days} onChange={(e) => form.setData('expiry_alert_days', e.target.value)} invalid={!!form.errors.expiry_alert_days} />
                        </Field>
                        {number('payslip_freshness_days', 'Payslip freshness (days)', 'A sponsored worker needs a payslip uploaded within this many days.')}
                        {number('retention_years', 'Keep records after employment ends (years)')}
                        {number('rtw_retention_years', 'Keep right-to-work evidence (years)')}
                    </div>
                </fieldset>

                <div>
                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        Save rules
                    </Button>
                </div>
            </form>
        </Card>
    );
}
