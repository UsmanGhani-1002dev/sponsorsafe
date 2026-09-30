import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Field, Input, Select } from '@/components/ui/field';
import WebsiteLayout from '@/layouts/website-layout';
import { cn } from '@/lib/cn';
import { Link, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { FormEvent } from 'react';

interface Props {
    plan: { price: string; limit: number; training: string };
    bands: { value: string; label: string }[];
    formToken: string;
    gateways: { card: boolean; paypal: boolean };
    cancelled: boolean;
    previous: { business: string; licence: string | null; name: string | null; email: string | null; phone: string | null; employees: string | null } | null;
}

/** "Create your account" from the prototype: business details, then Stripe's own secure payment page. */
export default function Signup({ plan, bands, formToken, gateways, cancelled, previous }: Props) {
    const form = useForm({
        business: previous?.business ?? '',
        licence: previous?.licence ?? '',
        name: previous?.name ?? '',
        email: previous?.email ?? '',
        phone: previous?.phone ?? '',
        employees: previous?.employees ?? '1-5',
        pay: 'card',
        agree: false,
        form_token: formToken,
        website: '',
    });
    const online = gateways.card || gateways.paypal;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/signup', { preserveScroll: true });
    };

    return (
        <WebsiteLayout title="Start your subscription">
            <section className="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
                <p className="text-sm font-semibold tracking-wide text-accent uppercase">Sponsor plan</p>
                <h1 className="mt-2 text-3xl font-semibold">Start your subscription</h1>
                <p className="mt-3 text-[17px] text-ink-2">
                    £{plan.price} per month for up to {plan.limit} employees. No setup fee, no contract, cancel any time.
                </p>

                <form onSubmit={submit} noValidate className="mt-8 flex flex-col gap-5 rounded-2xl border border-line bg-surface p-6 shadow-[0_12px_16px_-4px_rgba(16,24,40,0.08)] sm:p-8">
                    <div aria-hidden className="absolute -left-[9999px]">
                        <label htmlFor="website">Website</label>
                        <input id="website" tabIndex={-1} autoComplete="off" value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} />
                    </div>
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-[22px] font-semibold">Create your account</h2>
                        <span className="text-[15px] text-muted">
                            £{plan.price}/month · up to {plan.limit} employees
                        </span>
                    </div>

                    {cancelled && <Alert tone="info">Payment cancelled, nothing was charged. Your details are below if you'd like to try again.</Alert>}
                    {!online && (
                        <Alert tone="warning">
                            Online payment is not switched on yet.{' '}
                            <a href="/?topic=General%20question#contact" className="font-semibold underline">
                                Contact us
                            </a>{' '}
                            and we'll set your account up within one working day.
                        </Alert>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field id="su-biz" label="Business name" error={form.errors.business}>
                            <Input id="su-biz" autoComplete="organization" value={form.data.business} onChange={(e) => form.setData('business', e.target.value)} invalid={!!form.errors.business} />
                        </Field>
                        <Field id="su-lic" label="Sponsor licence number" error={form.errors.licence}>
                            <Input id="su-lic" value={form.data.licence} onChange={(e) => form.setData('licence', e.target.value)} invalid={!!form.errors.licence} />
                        </Field>
                        <Field id="su-name" label="Your name" error={form.errors.name}>
                            <Input id="su-name" autoComplete="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} invalid={!!form.errors.name} />
                        </Field>
                        <Field id="su-email" label="Your email (this is your login)" error={form.errors.email}>
                            <Input id="su-email" type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} invalid={!!form.errors.email} />
                        </Field>
                        <Field id="su-phone" label="Phone (optional)" error={form.errors.phone}>
                            <Input id="su-phone" type="tel" autoComplete="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field id="su-staff" label="Number of employees" error={form.errors.employees}>
                            <Select id="su-staff" value={form.data.employees} onChange={(e) => form.setData('employees', e.target.value)} invalid={!!form.errors.employees}>
                                {bands.map((b) => (
                                    <option key={b.value} value={b.value}>
                                        {b.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>

                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium text-ink-2">Pay with</legend>
                        <div className="grid grid-cols-2 gap-3">
                            {[
                                { value: 'card', label: 'Card (Stripe)', ready: gateways.card },
                                { value: 'paypal', label: 'PayPal', ready: gateways.paypal },
                            ].map((o) => (
                                <button
                                    key={o.value}
                                    type="button"
                                    aria-pressed={form.data.pay === o.value}
                                    disabled={!o.ready}
                                    onClick={() => form.setData('pay', o.value)}
                                    className={cn(
                                        'min-h-12 rounded-lg text-[15px] font-semibold disabled:cursor-not-allowed disabled:opacity-60',
                                        form.data.pay === o.value && o.ready ? 'border-2 border-accent bg-accent-soft text-accent-strong' : 'border border-line-strong bg-surface text-ink-2 hover:bg-canvas',
                                    )}
                                >
                                    {o.label}
                                    {!o.ready && <span className="block text-xs font-normal text-muted">{o.value === 'paypal' ? 'Coming soon' : 'Not available yet'}</span>}
                                </button>
                            ))}
                        </div>
                        {form.errors.pay && <p className="text-sm text-red-700 dark:text-red-300">{form.errors.pay}</p>}
                    </fieldset>

                    <div>
                        <label className="flex items-center gap-2.5 text-[15px]">
                            <input type="checkbox" className="size-5 accent-accent-fill" checked={form.data.agree} onChange={(e) => form.setData('agree', e.target.checked)} />
                            <span>
                                I agree to the{' '}
                                <Link href="/terms" className="font-semibold text-accent hover:underline">
                                    terms
                                </Link>{' '}
                                and{' '}
                                <Link href="/privacy" className="font-semibold text-accent hover:underline">
                                    privacy policy
                                </Link>
                            </span>
                        </label>
                        {form.errors.agree && <p className="mt-1 text-sm text-red-700 dark:text-red-300">{form.errors.agree}</p>}
                    </div>

                    {(form.errors as Record<string, string>).form && <Alert>{(form.errors as Record<string, string>).form}</Alert>}

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button type="submit" disabled={form.processing || !online} className="min-h-12 flex-1 text-base">
                            {form.processing ? 'Opening secure payment…' : 'Continue to secure payment'}
                        </Button>
                        <Link href="/#pricing" className="inline-flex min-h-12 items-center justify-center rounded-lg border border-line-strong bg-surface px-5 font-semibold text-ink-2 hover:bg-canvas">
                            Back
                        </Link>
                    </div>
                    <p className="inline-flex items-center gap-2 text-[13px] text-muted">
                        <Lock size={14} aria-hidden /> You'll be taken to {form.data.pay === 'paypal' ? 'PayPal' : 'Stripe'} to pay £{plan.price}. Card details never touch our servers.
                    </p>
                </form>

                <p className="mt-6 text-sm text-ink-2">
                    Already a customer?{' '}
                    <Link href="/login" className="font-semibold text-accent hover:underline">
                        Log in
                    </Link>
                </p>
            </section>
        </WebsiteLayout>
    );
}
