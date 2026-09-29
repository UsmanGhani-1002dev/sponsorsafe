import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Field, Input, Select } from '@/components/ui/field';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/cn';
import { Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

interface Basis {
    value: string;
    label: string;
    timeLimited: boolean;
    shareCode: boolean;
    sponsored: boolean;
    hint: string;
}

interface Props {
    options: {
        bases: Basis[];
        otherVisaTypes: string[];
        checkMethods: string[];
        contractTypes: string[];
        nationalities: string[];
        sites: { id: number; name: string }[];
        requiredDocs: { standard: string[]; sponsored: string[] };
    };
    plan: { used: number; limit: number; reached: boolean };
    canFillDemo: boolean;
}

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const inDays = (n: number) => iso(new Date(Date.now() + n * 86400000));

export default function CreateEmployee({ options, plan, canFillDemo }: Props) {
    const [shareMethod, manualMethod] = [options.checkMethods[0], options.checkMethods[1]];
    const form = useForm({
        full_name: '',
        date_of_birth: '',
        nationality: 'British',
        email: '',
        phone: '',
        ni_number: '',
        address: '',
        passport_number: '',
        passport_expiry: '',
        rtw_basis: 'sponsored',
        visa_type: options.otherVisaTypes[0],
        rtw_check_method: shareMethod,
        rtw_check_date: inDays(0),
        rtw_checked_by: '',
        share_code: '',
        visa_start: '',
        visa_expiry: '',
        work_restrictions: '',
        cos_number: '',
        cos_assigned_on: '',
        soc_code: '',
        job_title: '',
        salary: '',
        start_date: '',
        work_site_id: options.sites[0] ? String(options.sites[0].id) : '',
        days_per_week: '5',
        contracted_hours: '37.5',
        contract_type: options.contractTypes[0],
        portal_invite: true,
    });
    const { data, setData, errors } = form;
    const basis = options.bases.find((b) => b.value === data.rtw_basis) ?? options.bases[0];
    const docs = basis.sponsored ? options.requiredDocs.sponsored : options.requiredDocs.standard;
    const errorCount = Object.keys(errors).length;

    const changeBasis = (value: string) => {
        const next = options.bases.find((b) => b.value === value)!;
        setData((d) => ({
            ...d,
            rtw_basis: value,
            // British and Irish citizens are checked by passport or IDVT, not a share code.
            rtw_check_method: !next.shareCode && d.rtw_check_method === shareMethod ? manualMethod : next.shareCode && d.rtw_check_method === manualMethod ? shareMethod : d.rtw_check_method,
        }));
    };

    const fillDemo = () =>
        setData((d) => ({
            ...d,
            full_name: 'Sara Ali',
            date_of_birth: '1997-05-14',
            nationality: 'Pakistani',
            email: `sara.ali.${Math.floor(Math.random() * 1000)}@example.com`,
            phone: '07700 900123',
            ni_number: 'QQ 12 34 56 C',
            address: '12 High Street, Southampton SO14 2AA',
            passport_number: 'AB1234567',
            passport_expiry: '2032-02-01',
            rtw_basis: 'sponsored',
            rtw_check_method: shareMethod,
            rtw_check_date: inDays(0),
            rtw_checked_by: 'HR admin',
            share_code: 'W7X 9KP 2QR',
            visa_start: inDays(3),
            visa_expiry: inDays(3 * 365 + 3),
            work_restrictions: 'Only the sponsored job',
            cos_number: 'C2G7K19400X',
            cos_assigned_on: inDays(-45),
            soc_code: '7132',
            job_title: 'Sales Supervisor',
            salary: '41700',
            start_date: inDays(7),
            days_per_week: '5',
            contracted_hours: '37.5',
        }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // Stay put on errors: the summary sits next to the Save button and each field shows its own message.
        form.post('/app/employees', { preserveScroll: true });
    };

    // A small helper so every field gets a real label, error text and aria wiring.
    const text = (name: keyof typeof data, label: string, props: { type?: string; placeholder?: string; hint?: ReactNode; className?: string; inputMode?: 'numeric' | 'decimal' } = {}) => (
        <Field id={name} label={label} error={errors[name]} hint={props.hint} className={props.className}>
            <Input
                id={name}
                type={props.type ?? 'text'}
                inputMode={props.inputMode}
                placeholder={props.placeholder}
                value={String(data[name])}
                onChange={(e) => setData(name, e.target.value as never)}
                invalid={!!errors[name]}
            />
        </Field>
    );
    const select = (name: keyof typeof data, label: string, items: { value: string; label: string }[], onChange?: (v: string) => void) => (
        <Field id={name} label={label} error={errors[name]}>
            <Select id={name} value={String(data[name])} onChange={(e) => (onChange ? onChange(e.target.value) : setData(name, e.target.value as never))} invalid={!!errors[name]}>
                {items.map((i) => (
                    <option key={i.value} value={i.value}>
                        {i.label}
                    </option>
                ))}
            </Select>
        </Field>
    );
    const list = (xs: string[]) => xs.map((x) => ({ value: x, label: x }));

    if (plan.reached) {
        return (
            <AppLayout title="Add employee">
                <PageHeader title="Add employee" back={{ href: '/app/employees', label: 'All employees' }} />
                <Alert tone="info">Your plan covers up to {plan.limit} employees. Contact us to add more.</Alert>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Add employee">
            <PageHeader
                title="Add employee"
                back={{ href: '/app/employees', label: 'All employees' }}
                description="Right to work must be checked before the first day of work. The fields change with the employee's right-to-work basis."
            />

            {options.sites.length === 0 && (
                <div className="mb-5">
                    <Alert tone="warning">
                        Add a work site first.{' '}
                        <Link href="/app/settings" className="font-semibold underline">
                            Go to Settings → Work sites
                        </Link>
                    </Alert>
                </div>
            )}

            <form onSubmit={submit} noValidate className="flex max-w-[1060px] flex-col gap-5">
                <Section title="1. Personal details">
                    <Grid>
                        {text('full_name', 'Full legal name', { placeholder: 'As on passport' })}
                        {text('date_of_birth', 'Date of birth', { type: 'date' })}
                        {select('nationality', 'Nationality', list(options.nationalities))}
                        {text('email', 'Email (portal login)', { type: 'email' })}
                        {text('phone', 'Phone', { type: 'tel' })}
                        {text('ni_number', 'National Insurance number', { placeholder: 'QQ 12 34 56 C' })}
                    </Grid>
                    {text('address', 'UK home address', { placeholder: 'Street, town, postcode' })}
                    <Grid>
                        {text('passport_number', 'Passport number')}
                        {text('passport_expiry', 'Passport expiry', { type: 'date' })}
                    </Grid>
                </Section>

                <Section title="2. Right to work">
                    <Grid cols={2}>
                        {select(
                            'rtw_basis',
                            'Right-to-work basis',
                            options.bases.map((b) => ({ value: b.value, label: b.label })),
                            changeBasis,
                        )}
                        {data.rtw_basis === 'other_visa' && select('visa_type', 'Visa type', list(options.otherVisaTypes))}
                    </Grid>
                    <p className="rounded-lg bg-accent-soft px-3.5 py-3 text-sm leading-relaxed text-accent-strong">{basis.hint}</p>
                    <Grid>
                        {select('rtw_check_method', 'Check method', list(options.checkMethods))}
                        {text('rtw_check_date', 'Check date', { type: 'date', hint: 'On or before the start date.' })}
                        {text('rtw_checked_by', 'Checked by', { placeholder: 'Your name' })}
                        {basis.shareCode && text('share_code', 'Share code', { placeholder: '9 characters' })}
                        {basis.timeLimited && text('visa_start', 'Visa / permission start', { type: 'date' })}
                        {basis.timeLimited && text('visa_expiry', 'Visa / permission expiry', { type: 'date', hint: 'The follow-up check is due on this date.' })}
                        {basis.timeLimited && text('work_restrictions', 'Work restrictions', { placeholder: 'e.g. 20 hrs/week in term time' })}
                    </Grid>
                </Section>

                {basis.sponsored && (
                    <Section title="3. Sponsorship (must match the Certificate of Sponsorship)" highlight>
                        <Grid>
                            {text('cos_number', 'CoS number')}
                            {text('cos_assigned_on', 'CoS assigned date', { type: 'date' })}
                            {text('soc_code', 'SOC code', { placeholder: '4-digit occupation code', inputMode: 'numeric' })}
                        </Grid>
                        <p className="text-sm text-ink-2">Job title, salary and hours below must be the same as on the CoS. Also keep the job advert and interview notes as recruitment evidence.</p>
                    </Section>
                )}

                <Section title={basis.sponsored ? '4. Job and pay (same as the CoS)' : '3. Job and pay'}>
                    <Grid>
                        {text('job_title', 'Job title')}
                        {text('salary', 'Annual salary', { placeholder: '£', inputMode: 'decimal', hint: basis.sponsored ? undefined : 'Optional' })}
                        {text('start_date', 'Start date', { type: 'date' })}
                        {select(
                            'work_site_id',
                            'Work site',
                            options.sites.map((s) => ({ value: String(s.id), label: s.name })),
                        )}
                        {text('days_per_week', 'Working days per week', { inputMode: 'decimal' })}
                        {text('contracted_hours', 'Contracted hours per week', { inputMode: 'decimal' })}
                        {select('contract_type', 'Contract type', list(options.contractTypes))}
                        <Field id="portal_invite" label="Employee portal access">
                            <Select id="portal_invite" value={data.portal_invite ? 'yes' : 'no'} onChange={(e) => setData('portal_invite', e.target.value === 'yes')}>
                                <option value="yes">Send portal invite by email</option>
                                <option value="no">No portal access</option>
                            </Select>
                        </Field>
                    </Grid>
                </Section>

                <Section title="Documents required for this employee">
                    <ul className="flex flex-wrap gap-2">
                        {docs.map((d) => (
                            <li key={d} className="rounded-full bg-subtle px-3 py-1.5 text-[13px] font-semibold text-ink-2">
                                {d}
                            </li>
                        ))}
                    </ul>
                    <p className="text-sm text-ink-2">
                        {data.portal_invite ? "After saving, a request for their passport / ID goes to the employee's portal. " : ''}Upload the rest from their profile once documents are available.
                    </p>
                </Section>

                {(errors as Record<string, string>).form && <Alert>{(errors as Record<string, string>).form}</Alert>}
                {errorCount > 0 && !(errors as Record<string, string>).form && <Alert>Please correct the {errorCount === 1 ? 'field' : `${errorCount} fields`} marked in red.</Alert>}

                <div className="flex flex-wrap gap-3">
                    <Button type="submit" disabled={form.processing || options.sites.length === 0} className="min-h-12 px-5 text-base">
                        Save employee
                    </Button>
                    {canFillDemo && (
                        <Button type="button" variant="secondary" onClick={fillDemo} className="min-h-12">
                            Fill with dummy data
                        </Button>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}

function Section({ title, highlight, children }: { title: string; highlight?: boolean; children: ReactNode }) {
    return (
        <Card className={cn('flex flex-col gap-4 p-5 sm:p-6', highlight && 'border-2 border-accent')}>
            <h2 className="text-lg font-semibold">{title}</h2>
            {children}
        </Card>
    );
}

function Grid({ cols = 3, children }: { cols?: 2 | 3; children: ReactNode }) {
    return <div className={cn('grid gap-4', cols === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2')}>{children}</div>;
}
