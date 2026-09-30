import { Badge, type Tone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { RulesForm, type RulesProps } from '@/components/settings/rules-form';
import { EmptyState } from '@/components/ui/empty-state';
import { Field, Input, Select } from '@/components/ui/field';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { Link, router, useForm } from '@inertiajs/react';
import { Building2, UserRound } from 'lucide-react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';

interface Person {
    id: number;
    role: string;
    roleLabel: string;
    name: string;
    email: string | null;
    phone: string | null;
}
interface Site {
    id: number;
    name: string;
    address: string;
    closed: string | null;
    staff: string[];
    sms: { text: string; tone: Tone; taskId: number | null };
}
interface Props {
    business: { name: string; licence: string | null; admins: string[] };
    people: Person[];
    roles: { value: string; label: string; single: boolean }[];
    plan: { price: string; limit: number; used: number; nextPayment: string | null; method: string | null; graceEnds: string | null; canManage: boolean; training: string };
    sites: Site[];
    employees: { id: number; name: string; siteId: number | null }[];
    rules: RulesProps;
    retentionDue: number;
}

const opts = { preserveScroll: true, preserveState: true } as const;

export default function Settings({ business, people, roles, plan, sites, employees, rules, retentionDue }: Props) {
    const pct = Math.min(100, Math.round((plan.used / plan.limit) * 100));

    return (
        <AppLayout title="Settings">
            <PageHeader title="Settings" />

            <div className="grid items-start gap-5 lg:grid-cols-2">
                <Card className="p-5 sm:p-6">
                    <h2 className="mb-2 text-[17px] font-semibold">Business</h2>
                    <dl>
                        <Row label="Business name">{business.name}</Row>
                        <Row label="Sponsor licence number">{business.licence ?? 'Not added yet'}</Row>
                        <Row label="Admin logins">{business.admins.join(', ')}</Row>
                    </dl>
                </Card>

                <Card className="flex flex-col gap-4 p-5 sm:p-6">
                    <div className="flex items-center justify-between">
                        <h2 className="text-[17px] font-semibold">Subscription</h2>
                        {plan.graceEnds ? <Badge tone="red">Payment failed</Badge> : <Badge tone="green">Active</Badge>}
                    </div>
                    <p>
                        <span className="text-3xl font-semibold tracking-tight">£{plan.price}</span> <span className="text-sm text-muted">per month · up to {plan.limit} employees</span>
                    </p>
                    <div>
                        <div className="mb-1.5 flex justify-between text-sm">
                            <span className="text-ink-2">Employees</span>
                            <span className="font-semibold">
                                {plan.used} of {plan.limit}
                            </span>
                        </div>
                        <div className="h-2 rounded bg-subtle" role="progressbar" aria-valuenow={plan.used} aria-valuemin={0} aria-valuemax={plan.limit} aria-label="Employees used">
                            <div className="h-2 rounded bg-accent-fill" style={{ width: `${pct}%` }} />
                        </div>
                    </div>
                    {(plan.nextPayment || plan.method) && (
                        <p className="text-sm text-ink-2">
                            Next payment {plan.nextPayment ?? '—'} · {plan.method ?? '—'}
                        </p>
                    )}
                    {plan.graceEnds && <p className="text-sm text-red-700 dark:text-red-300">Update your payment details by {plan.graceEnds} to keep access.</p>}
                    <div className="flex flex-wrap items-center gap-2">
                        {plan.canManage ? (
                            <Button variant="secondary" className="min-h-10 text-sm" onClick={() => router.post('/app/settings/billing')}>
                                Manage billing
                            </Button>
                        ) : (
                            <a href="/?topic=Existing%20customer%20support#contact" className="inline-flex min-h-10 items-center rounded-lg border border-line-strong bg-surface px-3.5 text-sm font-semibold text-ink-2 hover:bg-canvas">
                                Contact us about billing
                            </a>
                        )}
                        <a href="/?topic=1-to-1%20training#contact" className="inline-flex min-h-10 items-center rounded-lg px-3.5 text-sm font-semibold text-accent hover:bg-accent-soft">
                            Book 1-to-1 training (£{plan.training} per person)
                        </a>
                    </div>
                </Card>
            </div>

            <KeyPersonnel people={people} roles={roles} />
            <WorkSites sites={sites} employees={employees} />

            <SectionTitle title="Leavers' records" description="Records are deleted when the retention period after employment ends has passed, after you review them." />
            <Card className="flex flex-wrap items-center justify-between gap-3 p-5">
                <p className="text-sm text-ink-2">{retentionDue ? `${retentionDue} due for deletion now.` : "Nothing is due for deletion."}</p>
                <Link href="/app/retention" className="inline-flex min-h-11 items-center rounded-lg border border-line-strong bg-surface px-4 text-[15px] font-semibold text-ink-2 hover:bg-canvas">
                    Review records due for deletion
                </Link>
            </Card>

            <SectionTitle title="Compliance rules" description="The thresholds and deadlines behind every Home Office check. Changes apply to checks from now on; past records keep their result." />
            <RulesForm {...rules} />
        </AppLayout>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 border-t border-line py-2.5 text-sm sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-3">
            <dt className="text-muted">{label}</dt>
            <dd className="font-medium">{children}</dd>
        </div>
    );
}

function SectionTitle({ title, description }: { title: string; description: string }) {
    return (
        <div className="mt-10 mb-4">
            <h2 className="text-lg font-semibold">{title}</h2>
            <p className="mt-0.5 text-sm text-ink-2">{description}</p>
        </div>
    );
}

/* ---------------- Key personnel ---------------- */

function KeyPersonnel({ people, roles }: Pick<Props, 'people' | 'roles'>) {
    const [editing, setEditing] = useState<number | null>(null);
    const [removing, setRemoving] = useState<Person | null>(null);
    const free = roles.filter((r) => !r.single || !people.some((p) => p.role === r.value));
    const add = useForm({ role: free[0]?.value ?? 'level1_user', name: '', email: '', phone: '' });

    // Once the Authorising Officer or Key Contact is filled, move the role picker on to a free role.
    useEffect(() => {
        if (!free.some((r) => r.value === add.data.role)) add.setData('role', free[0]?.value ?? 'level1_user');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [people]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        add.post('/app/settings/people', { ...opts, onSuccess: () => add.reset('name', 'email', 'phone') });
    };

    return (
        <>
            <SectionTitle title="Key personnel" description="The people named on your Sponsor Management System. When they change, update the SMS promptly." />
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <Card className="overflow-hidden">
                    {people.length === 0 ? (
                        <EmptyState icon={UserRound} title="No key personnel added">
                            Add your Authorising Officer, Key Contact and Level 1 Users.
                        </EmptyState>
                    ) : (
                        <ul>
                            {people.map((p) =>
                                editing === p.id ? (
                                    <li key={p.id} className="border-t border-line p-4 first:border-t-0">
                                        <PersonForm person={p} onDone={() => setEditing(null)} />
                                    </li>
                                ) : (
                                    <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3.5 first:border-t-0">
                                        <div className="min-w-0">
                                            <p className="text-[13px] font-medium text-muted">{p.roleLabel}</p>
                                            <p className="font-semibold">{p.name}</p>
                                            {(p.email || p.phone) && <p className="text-[13px] text-ink-2">{[p.email, p.phone].filter(Boolean).join(' · ')}</p>}
                                        </div>
                                        <div className="flex gap-1">
                                            <Button variant="ghost" className="min-h-10 px-3 text-sm" onClick={() => setEditing(p.id)}>
                                                Change
                                            </Button>
                                            <Button variant="ghost" className="min-h-10 px-3 text-sm text-red-700 dark:text-red-300" onClick={() => setRemoving(p)}>
                                                Remove
                                            </Button>
                                        </div>
                                    </li>
                                ),
                            )}
                        </ul>
                    )}
                </Card>

                <Card className="p-5 sm:p-6">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <h3 className="text-[17px] font-semibold">Add a person</h3>
                        <Field id="p-role" label="Role" error={add.errors.role}>
                            <Select id="p-role" value={add.data.role} onChange={(e) => add.setData('role', e.target.value)} invalid={!!add.errors.role}>
                                {free.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field id="p-name" label="Full name" error={add.errors.name}>
                            <Input id="p-name" value={add.data.name} onChange={(e) => add.setData('name', e.target.value)} invalid={!!add.errors.name} />
                        </Field>
                        <Field id="p-email" label="Email (optional)" error={add.errors.email}>
                            <Input id="p-email" type="email" value={add.data.email} onChange={(e) => add.setData('email', e.target.value)} invalid={!!add.errors.email} />
                        </Field>
                        <Field id="p-phone" label="Phone (optional)" error={add.errors.phone}>
                            <Input id="p-phone" type="tel" value={add.data.phone} onChange={(e) => add.setData('phone', e.target.value)} />
                        </Field>
                        <Button type="submit" disabled={add.processing}>
                            Add person
                        </Button>
                    </form>
                </Card>
            </div>

            <ConfirmDialog
                open={removing !== null}
                title={`Remove ${removing?.name ?? ''}?`}
                confirmLabel="Remove"
                danger
                onClose={() => setRemoving(null)}
                onConfirm={() => removing && router.delete(`/app/settings/people/${removing.id}`, { ...opts, onFinish: () => setRemoving(null) })}
            >
                They will no longer be listed as {removing?.roleLabel}. Remember to update the Sponsor Management System.
            </ConfirmDialog>
        </>
    );
}

function PersonForm({ person, onDone }: { person: Person; onDone: () => void }) {
    const form = useForm({ role: person.role, name: person.name, email: person.email ?? '', phone: person.phone ?? '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/app/settings/people/${person.id}`, { ...opts, onSuccess: onDone });
    };
    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-3">
            <p className="text-[13px] font-medium text-muted">{person.roleLabel}</p>
            <div className="grid gap-3 sm:grid-cols-3">
                <Field id={`pe-name-${person.id}`} label="Full name" error={form.errors.name}>
                    <Input id={`pe-name-${person.id}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} invalid={!!form.errors.name} />
                </Field>
                <Field id={`pe-email-${person.id}`} label="Email" error={form.errors.email}>
                    <Input id={`pe-email-${person.id}`} type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} invalid={!!form.errors.email} />
                </Field>
                <Field id={`pe-phone-${person.id}`} label="Phone">
                    <Input id={`pe-phone-${person.id}`} type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Field>
            </div>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    Save
                </Button>
                <Button type="button" variant="secondary" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

/* ---------------- Work sites ---------------- */

function WorkSites({ sites, employees }: Pick<Props, 'sites' | 'employees'>) {
    const open = sites.filter((s) => !s.closed);
    const [renaming, setRenaming] = useState<number | null>(null);
    const [closing, setClosing] = useState<Site | null>(null);
    const add = useForm({ name: '', address: '' });
    const move = useForm({ employee_id: employees[0] ? String(employees[0].id) : '', work_site_id: open[0] ? String(open[0].id) : '' });

    return (
        <>
            <SectionTitle title="Work sites" description="Every address where your employees work. New or closed work addresses must be updated on the Sponsor Management System." />
            <Card className="overflow-hidden">
                {sites.length === 0 ? (
                    <EmptyState icon={Building2} title="No work sites yet">
                        Add the address where your employees work. You need at least one before adding employees.
                    </EmptyState>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas text-[13px] font-semibold text-ink-2">
                                <tr>
                                    <th scope="col" className="px-5 py-3">Site</th>
                                    <th scope="col" className="px-5 py-3">Address</th>
                                    <th scope="col" className="px-5 py-3">Employees</th>
                                    <th scope="col" className="px-5 py-3">Sponsor Management System</th>
                                    <th scope="col" className="px-5 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {sites.map((s) =>
                                    renaming === s.id ? (
                                        <tr key={s.id} className="border-t border-line">
                                            <td colSpan={5} className="p-4">
                                                <SiteForm site={s} onDone={() => setRenaming(null)} />
                                            </td>
                                        </tr>
                                    ) : (
                                        <tr key={s.id} className="border-t border-line align-top">
                                            <td className="px-5 py-3.5 font-semibold">
                                                {s.name} {s.closed && <Badge tone="grey">Closed {s.closed}</Badge>}
                                            </td>
                                            <td className="px-5 py-3.5">{s.address}</td>
                                            <td className="px-5 py-3.5 text-ink-2">{s.staff.length ? s.staff.join(', ') : 'None'}</td>
                                            <td className="px-5 py-3.5">
                                                {s.sms.taskId ? (
                                                    <Link href={`/app/reports?task=${s.sms.taskId}`} className="hover:opacity-80">
                                                        <Badge tone={s.sms.tone}>{s.sms.text}</Badge>
                                                    </Link>
                                                ) : (
                                                    <Badge tone={s.sms.tone}>{s.sms.text}</Badge>
                                                )}
                                            </td>
                                            <td className="px-5 py-2 text-right whitespace-nowrap">
                                                {!s.closed && (
                                                    <>
                                                        <Button variant="ghost" className="min-h-10 px-3 text-sm" onClick={() => setRenaming(s.id)}>
                                                            Change
                                                        </Button>
                                                        <Button variant="ghost" className="min-h-10 px-3 text-sm text-red-700 dark:text-red-300" onClick={() => setClosing(s)}>
                                                            Close
                                                        </Button>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <div className="mt-5 grid items-start gap-5 lg:grid-cols-2">
                <Card className="p-5 sm:p-6">
                    <form
                        noValidate
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            add.post('/app/settings/sites', { ...opts, onSuccess: () => add.reset() });
                        }}
                    >
                        <h3 className="text-[17px] font-semibold">Add new work address</h3>
                        <Field id="s-name" label="Site name" error={add.errors.name}>
                            <Input id="s-name" value={add.data.name} onChange={(e) => add.setData('name', e.target.value)} placeholder="e.g. Third shop" invalid={!!add.errors.name} />
                        </Field>
                        <Field id="s-address" label="Full address and postcode" error={add.errors.address}>
                            <Input id="s-address" value={add.data.address} onChange={(e) => add.setData('address', e.target.value)} placeholder="Street, town, postcode" invalid={!!add.errors.address} />
                        </Field>
                        <Button type="submit" disabled={add.processing}>
                            Add site
                        </Button>
                    </form>
                </Card>

                <Card className="p-5 sm:p-6">
                    <form
                        noValidate
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            move.post('/app/settings/move', opts);
                        }}
                    >
                        <h3 className="text-[17px] font-semibold">Move an employee to another site</h3>
                        {employees.length === 0 || open.length < 2 ? (
                            <p className="text-sm text-ink-2">You need at least two open sites and one employee to move someone.</p>
                        ) : (
                            <>
                                <Field id="m-employee" label="Employee" error={move.errors.employee_id}>
                                    <Select id="m-employee" value={move.data.employee_id} onChange={(e) => move.setData('employee_id', e.target.value)}>
                                        {employees.map((e) => (
                                            <option key={e.id} value={e.id}>
                                                {e.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field id="m-site" label="New work site" error={move.errors.work_site_id}>
                                    <Select id="m-site" value={move.data.work_site_id} onChange={(e) => move.setData('work_site_id', e.target.value)} invalid={!!move.errors.work_site_id}>
                                        {open.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Button type="submit" disabled={move.processing}>
                                    Move employee
                                </Button>
                            </>
                        )}
                    </form>
                </Card>
            </div>

            <ConfirmDialog
                open={closing !== null}
                title={`Close ${closing?.name ?? ''}?`}
                confirmLabel="Close site"
                danger
                onClose={() => setClosing(null)}
                onConfirm={() => closing && router.post(`/app/settings/sites/${closing.id}/close`, {}, { ...opts, onFinish: () => setClosing(null) })}
            >
                {closing && closing.staff.length > 0
                    ? `Move ${closing.staff.join(', ')} to another site first.`
                    : 'Nobody can be added to this site after it closes. Remember to remove the work address on the Sponsor Management System.'}
            </ConfirmDialog>
        </>
    );
}

function SiteForm({ site, onDone }: { site: Site; onDone: () => void }) {
    const form = useForm({ name: site.name, address: site.address });
    return (
        <form
            noValidate
            className="flex flex-col gap-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.put(`/app/settings/sites/${site.id}`, { ...opts, onSuccess: onDone });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <Field id={`se-name-${site.id}`} label="Site name" error={form.errors.name}>
                    <Input id={`se-name-${site.id}`} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} invalid={!!form.errors.name} />
                </Field>
                <Field id={`se-address-${site.id}`} label="Full address and postcode" error={form.errors.address}>
                    <Input id={`se-address-${site.id}`} value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} invalid={!!form.errors.address} />
                </Field>
            </div>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    Save
                </Button>
                <Button type="button" variant="secondary" onClick={onDone}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
