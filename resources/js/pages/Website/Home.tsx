import { Button } from '@/components/ui/button';
import { Field, Input, Select, Textarea } from '@/components/ui/field';
import WebsiteLayout from '@/layouts/website-layout';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Check, Mail, MapPin, Minus } from 'lucide-react';
import type { FormEvent } from 'react';

interface Props {
    plan: { price: string; limit: number; training: string };
    topics: string[];
    topic: string;
    formToken: string;
    signedIn: string | null;
}

const features = [
    { title: 'Right-to-work checks', text: 'Record every check before day one, store the result, and get reminded before each follow-up check is due.' },
    { title: 'Absence rules built in', text: 'Log leave and sickness. We flag unpaid leave over 4 weeks and 10 days of unauthorised absence automatically.' },
    { title: 'Home Office deadlines', text: 'Job, salary, hours, site and leaver changes create a reporting task with its 10 or 20 working-day deadline.' },
    { title: 'Document vault', text: 'Passports, CoS, contracts, payslips from your accountant and recruitment evidence, encrypted and organised.' },
    { title: 'Employee app', text: 'Staff upload documents, request leave, report sickness and update their address from their phone.' },
    { title: 'Audit-ready in one click', text: 'Download a compliance pack per employee for a Home Office visit, with a checklist of anything missing.' },
];

const forList = ['Sponsored or not, every employee in one place', 'Payroll and accounts handled by your accountant', 'No HR department and no technical knowledge needed', 'Works on phone, tablet and computer'];

const faqs = [
    { q: 'Does it report to the Home Office for me?', a: 'No. It tells you exactly what to report and by when; you report on the Sponsor Management System and tick it off here.' },
    { q: 'Do I need payroll software?', a: 'No. Your accountant keeps running payroll. You upload the payslips they send you as evidence of pay.' },
    { q: 'Is our data safe?', a: 'Data is stored in the UK, encrypted, and only your admins can see documents. Every view is logged.' },
    { q: 'What if we grow past the employee limit?', a: 'Contact us and we will move you to a larger plan.' },
];

const primary = 'inline-flex min-h-12 items-center justify-center rounded-lg bg-accent-fill px-5 text-base font-semibold text-white hover:bg-accent-fill-hover';
const secondary = 'inline-flex min-h-12 items-center justify-center rounded-lg border border-line-strong bg-surface px-5 text-base font-semibold text-ink hover:bg-canvas';

export default function Home({ plan, topics, topic, formToken, signedIn }: Props) {
    const planIncludes = [`Up to ${plan.limit} employees`, 'Right-to-work checks and expiry reminders', 'Absence tracking with Home Office rules', 'Reporting tasks and deadlines', 'Employee app for your staff', 'Email support'];

    return (
        <WebsiteLayout title="UKVI compliance for sponsor licence holders" signedIn={signedIn}>
            {/* Hero */}
            <section className="mx-auto grid max-w-6xl items-center gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:py-20">
                <div>
                    <p className="text-sm font-semibold tracking-wide text-accent uppercase">For UK sponsor licence holders</p>
                    <h1 className="mt-3 text-4xl leading-tight font-semibold sm:text-5xl">Keep your sponsor licence safe, without the paperwork.</h1>
                    <p className="mt-5 max-w-xl text-lg leading-relaxed text-ink-2">
                        Right-to-work checks, absence rules, Home Office reporting deadlines and every document a compliance officer asks for, in one simple place. Built for small employers.
                    </p>
                    <div className="mt-7 flex flex-wrap gap-3">
                        <Link href="/signup" className={primary}>
                            Start for £{plan.price} a month
                        </Link>
                        <a href="/?topic=Book%20a%20free%20demo#contact" className={secondary}>
                            Book a free demo
                        </a>
                    </div>
                    <ul className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-ink-2">
                        {[`Up to ${plan.limit} employees`, 'No setup fee', 'Cancel any time'].map((t) => (
                            <li key={t} className="inline-flex items-center gap-1.5">
                                <Check size={16} aria-hidden className="text-accent" /> {t}
                            </li>
                        ))}
                    </ul>
                </div>
                <DashboardPreview />
            </section>

            {/* Features */}
            <section id="features" className="scroll-mt-20 border-t border-line bg-canvas">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <SectionHeading eyebrow="What it does" title="Everything your sponsor duties need. Nothing they don't." />
                    <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {features.map((f) => (
                            <div key={f.title} className="rounded-xl border border-line bg-surface p-6">
                                <span aria-hidden className="inline-flex size-10 items-center justify-center rounded-lg bg-accent-soft text-accent">
                                    <Check size={20} />
                                </span>
                                <h3 className="mt-4 text-lg font-semibold">{f.title}</h3>
                                <p className="mt-1.5 text-[15px] leading-relaxed text-ink-2">{f.text}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Who it's for */}
            <section className="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2">
                <div>
                    <SectionHeading eyebrow="Who it's for" title="Small sponsors whose accountant runs payroll" />
                    <p className="mt-4 text-[17px] leading-relaxed text-ink-2">
                        Shops, restaurants, care providers and trades with a sponsor licence and a handful of staff. Your accountant keeps doing payroll. SponsorSafe keeps the Home Office side in order: you just upload the payslips they send you.
                    </p>
                </div>
                <ul className="flex flex-col justify-center gap-3">
                    {[...forList, `Up to ${plan.limit} employees`].map((t) => (
                        <li key={t} className="flex items-center gap-3 text-[16px]">
                            <Check size={20} aria-hidden className="shrink-0 text-accent" /> {t}
                        </li>
                    ))}
                    <li className="flex items-center gap-3 text-[16px] text-ink-2">
                        <Minus size={20} aria-hidden className="shrink-0 text-muted" /> Not a payroll or accounts package. Purely UKVI compliance.
                    </li>
                </ul>
            </section>

            {/* Pricing and training */}
            <section id="pricing" className="scroll-mt-20 border-t border-line bg-canvas">
                <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <SectionHeading eyebrow="Pricing" title="One simple plan" intro="No setup fee, no contract. Cancel whenever you like." />
                    <div id="training" className="mt-10 grid scroll-mt-24 gap-6 lg:grid-cols-[1.2fr_1fr]">
                        <div className="rounded-2xl border-2 border-accent bg-surface p-7">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-lg font-semibold">Sponsor plan</p>
                                <span className="rounded-full bg-accent-soft px-3 py-1 text-[13px] font-semibold text-accent-strong">Most small sponsors</span>
                            </div>
                            <p className="mt-4">
                                <span className="text-5xl font-semibold tracking-tight">£{plan.price}</span>
                                <span className="ml-2 text-ink-2">per month · up to {plan.limit} employees</span>
                            </p>
                            <ul className="mt-6 grid gap-2.5 sm:grid-cols-2">
                                {planIncludes.map((t) => (
                                    <li key={t} className="flex items-start gap-2 text-[15px]">
                                        <Check size={18} aria-hidden className="mt-0.5 shrink-0 text-accent" /> {t}
                                    </li>
                                ))}
                            </ul>
                            <Link href="/signup" className={`${primary} mt-7 w-full`}>
                                Start subscription
                            </Link>
                        </div>
                        <div className="flex flex-col rounded-2xl border border-line bg-surface p-7">
                            <p className="text-lg font-semibold">1-to-1 training (optional)</p>
                            <p className="mt-4">
                                <span className="text-4xl font-semibold tracking-tight">£{plan.training}</span>
                                <span className="ml-2 text-ink-2">per person</span>
                            </p>
                            <p className="mt-4 flex-1 text-[15px] leading-relaxed text-ink-2">
                                A friendly one-to-one session online. We set up your business with you, add your first employees and show you exactly what to do each month. No technical knowledge needed.
                            </p>
                            <a href="/?topic=1-to-1%20training#contact" className={`${secondary} mt-6 w-full`}>
                                Ask about training
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {/* FAQ */}
            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <h2 className="text-3xl font-semibold">Questions</h2>
                <dl className="mt-8 grid gap-6 md:grid-cols-2">
                    {faqs.map((f) => (
                        <div key={f.q} className="rounded-xl border border-line p-6">
                            <dt className="text-lg font-semibold">{f.q}</dt>
                            <dd className="mt-2 text-[15px] leading-relaxed text-ink-2">{f.a}</dd>
                        </div>
                    ))}
                </dl>
            </section>

            {/* Contact */}
            <section id="contact" className="scroll-mt-20 border-t border-line bg-canvas">
                <div className="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_1.2fr]">
                    <div>
                        <SectionHeading eyebrow="Contact" title="Talk to us" intro="Questions, a free demo, or 1-to-1 training: send a message and we'll reply within one working day." />
                        <ul className="mt-6 flex flex-col gap-2 text-[15px] text-ink-2">
                            <li className="inline-flex items-center gap-2">
                                <Mail size={18} aria-hidden className="text-accent" /> Reply by email within one working day
                            </li>
                            <li className="inline-flex items-center gap-2">
                                <MapPin size={18} aria-hidden className="text-accent" /> Southampton, United Kingdom
                            </li>
                        </ul>
                    </div>
                    <ContactForm topics={topics} topic={topic} formToken={formToken} />
                </div>
            </section>
        </WebsiteLayout>
    );
}

function SectionHeading({ eyebrow, title, intro }: { eyebrow: string; title: string; intro?: string }) {
    return (
        <div className="max-w-2xl">
            <p className="text-sm font-semibold tracking-wide text-accent uppercase">{eyebrow}</p>
            <h2 className="mt-2 text-3xl leading-tight font-semibold">{title}</h2>
            {intro && <p className="mt-3 text-[17px] text-ink-2">{intro}</p>}
        </div>
    );
}

function ContactForm({ topics, topic, formToken }: { topics: string[]; topic: string; formToken: string }) {
    const { flash } = usePage<SharedProps>().props;
    const form = useForm({ name: '', email: '', phone: '', topic, message: '', form_token: formToken, website: '' });

    if (flash.contactSent) {
        return (
            <div className="rounded-2xl border border-line bg-surface p-7" role="status">
                <h3 className="text-xl font-semibold">Thanks, {flash.contactSent.first} – message sent</h3>
                <p className="mt-2 text-ink-2">We'll reply to {flash.contactSent.email} within one working day.</p>
            </div>
        );
    }

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/contact', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4 rounded-2xl border border-line bg-surface p-6 sm:p-7">
            {/* Hidden from people; bots fill it in. */}
            <div aria-hidden className="absolute -left-[9999px]">
                <label htmlFor="website">Website</label>
                <input id="website" tabIndex={-1} autoComplete="off" value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="c-name" label="Name" error={form.errors.name}>
                    <Input id="c-name" autoComplete="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} invalid={!!form.errors.name} />
                </Field>
                <Field id="c-email" label="Email" error={form.errors.email}>
                    <Input id="c-email" type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} invalid={!!form.errors.email} />
                </Field>
                <Field id="c-phone" label="Phone (optional)" error={form.errors.phone}>
                    <Input id="c-phone" type="tel" autoComplete="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </Field>
                <Field id="c-topic" label="Topic" error={form.errors.topic}>
                    <Select id="c-topic" value={form.data.topic} onChange={(e) => form.setData('topic', e.target.value)}>
                        {topics.map((t) => (
                            <option key={t}>{t}</option>
                        ))}
                    </Select>
                </Field>
            </div>
            <Field id="c-message" label="Message" error={form.errors.message}>
                <Textarea id="c-message" rows={5} maxLength={3000} value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} invalid={!!form.errors.message} />
            </Field>
            <Button type="submit" disabled={form.processing} className="min-h-12 text-base">
                Send message
            </Button>
            <p className="text-[13px] text-muted">
                We use your details only to reply. See our{' '}
                <Link href="/privacy" className="underline">
                    privacy policy
                </Link>
                .
            </p>
        </form>
    );
}

/** A static glimpse of the dashboard, as in the prototype. */
function DashboardPreview() {
    const rows = [
        { title: 'Job title changed', who: 'Rahul Mehta · report on the SMS', badge: 'Due today', tone: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/60 dark:text-red-300 dark:border-red-900' },
        { title: 'Follow-up right-to-work check', who: 'Aisha Rahman · visa ends 10 Dec', badge: '71 days', tone: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900' },
        { title: 'Annual leave, 12–16 Oct', who: 'Kasia Nowak · from the employee app', badge: 'No report needed', tone: 'bg-canvas text-ink-2 border-line' },
    ];
    return (
        <div aria-hidden className="rounded-2xl border border-line bg-surface p-5 shadow-[0_12px_24px_-8px_rgba(16,24,40,0.15)]">
            <div className="flex items-center justify-between">
                <span className="font-semibold">Dashboard</span>
                <span className="text-sm text-muted">Demo Retail Ltd</span>
            </div>
            <div className="mt-4 grid grid-cols-3 gap-3">
                {[
                    ['Reports due', '1', true],
                    ['Visas expiring', '1', true],
                    ['Documents', '96%', false],
                ].map(([l, v, urgent]) => (
                    <div key={String(l)} className="rounded-lg border border-line p-3">
                        <p className="text-[12px] text-muted">{l}</p>
                        <p className={cn('font-mono text-xl font-semibold', urgent && 'text-[#B42318] dark:text-red-300')}>{v}</p>
                    </div>
                ))}
            </div>
            <ul className="mt-4 flex flex-col gap-2">
                {rows.map((r) => (
                    <li key={r.title} className="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2.5">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">{r.title}</p>
                            <p className="truncate text-[12px] text-muted">{r.who}</p>
                        </div>
                        <span className={`shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-medium ${r.tone}`}>{r.badge}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
