import { Button } from '@/components/ui/button';
import { Field, Input, Select, Textarea } from '@/components/ui/field';
import WebsiteLayout from '@/layouts/website-layout';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Building2, Check, Mail, MapPin, Minus, User, Users, type LucideIcon } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';


interface Tier {
    key: string;
    name: string;
    price: string;
    limit: number;
    from: number;
}

interface Props {
    plans: { tiers: Tier[]; training: string; corporateFrom: number };
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

const planIncludes = ['Right-to-work checks and expiry reminders', 'Absence tracking with Home Office rules', 'Reporting tasks and deadlines', 'Document vault and compliance pack', 'Employee app for your staff', 'Email support'];

const primary = 'inline-flex min-h-12 items-center justify-center rounded-lg bg-accent-fill px-5 text-base font-semibold text-white hover:bg-accent-fill-hover';
const secondary = 'inline-flex min-h-12 items-center justify-center rounded-lg border border-line-strong bg-surface px-5 text-base font-semibold text-ink hover:bg-canvas';

export default function Home({ plans, topics, topic, formToken, signedIn }: Props) {
    const largest = plans.corporateFrom - 1;
    const faqs = [
        { q: 'Does it report to the Home Office for me?', a: 'No. It tells you exactly what to report and by when; you report on the Sponsor Management System and tick it off here.' },
        { q: 'Do I need payroll software?', a: 'No. Your accountant keeps running payroll. You upload the payslips they send you as evidence of pay.' },
        { q: 'Is our data safe?', a: 'Data is stored in the UK, encrypted, and only your admins can see documents. Every view is logged.' },
        { q: 'What if we grow?', a: `Move up to the next plan yourself in Settings at any time. For more than ${largest} employees, contact us about a Corporate package.` },
    ];

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
                            Start from £{plans.tiers[0].price} a month
                        </Link>
                        <a href="/?topic=Book%20a%20free%20demo#contact" className={secondary}>
                            Book a free demo
                        </a>
                    </div>
                    <ul className="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-ink-2">
                        {[`Plans for 1 to ${largest} employees`, 'No setup fee', 'Cancel any time'].map((t) => (
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
                    {[...forList, `Up to ${largest} employees, or a Corporate package for more`].map((t) => (
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
                    <SectionHeading eyebrow="Pricing" title="Simple plans by team size" intro="No setup fee, no contract. Move up as you grow, or cancel whenever you like." />
                    <div className="mt-10 grid items-stretch gap-6 lg:grid-cols-3">
                        {plans.tiers.map((t, i) => (
                            <PlanCard
                                key={t.key}
                                featured={i === 0}
                                badge={i === 0 ? 'Most small sponsors' : undefined}
                                icon={i === 0 ? User : Users}
                                name={t.name}
                                tagline={i === 0 ? 'For small teams with a few sponsored workers' : 'For growing teams with more staff to manage'}
                                price={
                                    <>
                                        <span className="text-5xl font-semibold tracking-tight">£{t.price}</span>
                                        <span className="ml-2 text-base text-ink-2">per month</span>
                                    </>
                                }
                                employees={t.from === 1 ? `Up to ${t.limit} employees` : `${t.from} to ${t.limit} employees`}
                                features={planIncludes}
                                cta={
                                    <Link href={`/signup?plan=${t.key}`} className={cn(i === 0 ? primary : secondary, 'w-full')}>
                                        Start {t.name}
                                    </Link>
                                }
                            />
                        ))}
                        <PlanCard
                            icon={Building2}
                            name="Corporate"
                            tagline="For larger organisations"
                            price={<span className="text-4xl font-semibold tracking-tight">Let's talk</span>}
                            employees={`More than ${largest} employees`}
                            features={[`Everything in ${plans.tiers[plans.tiers.length - 1].name}`, 'A price agreed for your business', '1-to-1 training for your team', 'Email support']}
                            cta={
                                <a href="/?topic=Corporate%20package#contact" className={cn(secondary, 'w-full')}>
                                    Contact us
                                </a>
                            }
                        />
                    </div>

                    <div id="training" className="mt-6 flex scroll-mt-24 flex-col gap-5 rounded-2xl border border-line bg-surface p-7 md:flex-row md:items-center md:justify-between">
                        <div className="max-w-2xl">
                            <p className="text-lg font-semibold">
                                1-to-1 training (optional) <span className="ml-2 text-2xl font-semibold tracking-tight">£{plans.training}</span>
                                <span className="ml-1 text-ink-2">per person</span>
                            </p>
                            <p className="mt-2 text-[15px] leading-relaxed text-ink-2">
                                A friendly one-to-one session online. We set up your business with you, add your first employees and show you exactly what to do each month. No technical knowledge needed.
                            </p>
                        </div>
                        <a href="/?topic=1-to-1%20training#contact" className={`${secondary} shrink-0`}>
                            Ask about training
                        </a>
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

/** One pricing card: who it is for, the price, the team size, what is included, and the button pinned to the bottom. */
function PlanCard({ featured = false, badge, icon: Icon, name, tagline, price, employees, features, cta }: {
    featured?: boolean;
    badge?: string;
    icon: LucideIcon;
    name: string;
    tagline: string;
    price: ReactNode;
    employees: string;
    features: string[];
    cta: ReactNode;
}) {
    return (
        <div
            className={cn(
                'relative flex flex-col rounded-3xl bg-surface p-6 transition-shadow',
                featured ? 'border-2 border-accent shadow-[0_20px_40px_-12px_rgba(79,70,229,0.25)]' : 'border border-line shadow-[0_1px_3px_rgba(16,24,40,0.06)] hover:shadow-[0_12px_24px_-8px_rgba(16,24,40,0.12)]',
            )}
        >
            {badge && (
                <span className="absolute -top-3 left-6 rounded-full bg-accent-fill px-3 py-0.5 text-[13px] font-semibold text-white shadow-sm">{badge}</span>
            )}
            <div className="flex items-center gap-3">
                <span aria-hidden className={cn('inline-flex size-10 items-center justify-center rounded-xl', featured ? 'bg-accent-fill text-white' : 'bg-accent-soft text-accent')}>
                    <Icon size={20} />
                </span>
                <h3 className="text-lg font-semibold">{name}</h3>
            </div>
            <p className="mt-2 min-h-10 text-sm text-ink-2 xl:min-h-0 xl:whitespace-nowrap">{tagline}</p>
            <p className="mt-4 flex min-h-12 items-end">{price}</p>
            <p className="mt-4 inline-flex self-start rounded-full bg-canvas px-3 py-1 text-sm font-semibold text-ink ring-1 ring-line">{employees}</p>
            <ul className="mt-5 flex flex-1 flex-col gap-2.5 border-t border-line pt-5">
                {features.map((f) => (
                    <li key={f} className="flex items-start gap-2.5 text-sm">
                        <span aria-hidden className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent">
                            <Check size={13} strokeWidth={3} />
                        </span>
                        {f}
                    </li>
                ))}
            </ul>
            <div className="mt-6">{cta}</div>
        </div>
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
                <span className="text-sm text-muted">UrbanCart Ltd</span>
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
