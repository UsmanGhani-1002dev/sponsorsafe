import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import PortalLayout from '@/layouts/portal-layout';
import { useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';

interface Props {
    types: { value: string; label: string }[];
    preselect: string;
    leave: { year: string; allowance: string; taken: number; pending: number; left: string };
    absences: { id: number; type: string; dates: string; days: number }[];
    maxMb: number;
    selfCertDays: number;
}

const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export default function LeaveAndSickness({ types, preselect, leave, absences, maxMb }: Props) {
    const form = useForm<{ type: string; start_date: string; end_date: string; note: string; fit_note: File | null }>({
        type: preselect,
        start_date: today(),
        end_date: today(),
        note: '',
        fit_note: null,
    });
    const { data, setData, errors } = form;
    const [check, setCheck] = useState<{ valid: boolean; info: string; notes: string[]; needsFitNote?: boolean } | null>(null);
    const sick = data.type === 'sick';

    // Live information: working days, leave left afterwards and warnings (worked out on the server).
    useEffect(() => {
        if (!data.start_date || !data.end_date) return setCheck(null);
        const controller = new AbortController();
        const t = setTimeout(async () => {
            try {
                const params = new URLSearchParams({ type: data.type, start_date: data.start_date, end_date: data.end_date });
                const res = await fetch(`/me/leave/check?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                setCheck(res.ok ? await res.json() : { valid: false, info: 'Check the dates.', notes: [] });
            } catch {
                // Superseded by a newer check.
            }
        }, 250);
        return () => {
            clearTimeout(t);
            controller.abort();
        };
    }, [data.type, data.start_date, data.end_date]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/me/leave', { forceFormData: true, preserveScroll: true });
    };

    return (
        <PortalLayout title="Leave and sickness">
            <h1 className="text-[24px] font-semibold">{sick ? 'Report sickness' : 'Request leave or report sickness'}</h1>
            <p className="mt-1 text-[15px] text-ink-2">
                Annual leave left in {leave.year}: <strong>{leave.left}</strong> of {leave.allowance} days
                {leave.pending ? ` (${leave.pending} waiting for HR)` : ''}.
            </p>

            <Card className="mt-5 p-4 sm:p-6">
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <Field id="type" label="Type" error={errors.type}>
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
                        <Field id="end_date" label={sick ? 'Last day off (or expected)' : 'Last day'} error={errors.end_date}>
                            <Input id="end_date" type="date" min={data.start_date} value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} invalid={!!errors.end_date} />
                        </Field>
                    </div>
                    <Field id="note" label="Note for HR (optional)" error={errors.note} hint={sick ? 'Please do not include medical details.' : undefined}>
                        <Input id="note" maxLength={300} value={data.note} onChange={(e) => setData('note', e.target.value)} />
                    </Field>
                    {sick && check?.needsFitNote && (
                        <Field id="fit_note" label="Fit note (optional now)" error={errors.fit_note} hint={`PDF, JPG or PNG, up to ${maxMb} MB. You can also send it later under My documents.`}>
                            <input
                                id="fit_note"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => setData('fit_note', e.target.files?.[0] ?? null)}
                                className="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink-2 file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:font-semibold file:text-accent-strong"
                            />
                        </Field>
                    )}

                    {check && (
                        <div aria-live="polite" className="flex flex-col gap-2">
                            <p className={check.valid ? 'text-sm text-ink-2' : 'text-sm font-medium text-red-700 dark:text-red-300'}>{check.info}</p>
                            {check.notes.map((n) => (
                                <Alert key={n} tone="warning">
                                    {n}
                                </Alert>
                            ))}
                        </div>
                    )}

                    <Button type="submit" disabled={form.processing || check?.valid === false}>
                        Send to HR
                    </Button>
                </form>
            </Card>

            <h2 className="mt-8 mb-3 text-lg font-semibold">My absence this year</h2>
            <Card className="overflow-hidden">
                {absences.length === 0 ? (
                    <p className="p-5 text-sm text-ink-2">No absences recorded.</p>
                ) : (
                    <ul>
                        {absences.map((a) => (
                            <li key={a.id} className="flex items-center justify-between gap-3 border-t border-line px-4 py-3 first:border-t-0 sm:px-5">
                                <span className="font-medium">{a.type}</span>
                                <span className="text-right text-sm text-ink-2">
                                    {a.dates} · {a.days} {a.days === 1 ? 'day' : 'days'}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </PortalLayout>
    );
}
