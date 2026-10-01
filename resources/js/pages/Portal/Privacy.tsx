import { Card } from '@/components/ui/card';
import { PageHeader } from '@/components/ui/page-header';
import PortalLayout from '@/layouts/portal-layout';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface Props {
    business: string;
    contacts: string[];
    retentionYears: number;
    rtwRetentionYears: number;
    sponsored: boolean;
    company: { name: string; number: string | null; address: string | null; ico: string | null; email: string; updated: string; product: string };
}

const years = (n: number) => `${n} year${n === 1 ? '' : 's'}`;

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border-t border-line py-5 first:border-t-0 first:pt-0">
            <h2 className="text-[17px] font-semibold">{title}</h2>
            <div className="mt-2 flex flex-col gap-2 text-[15px] leading-relaxed text-ink-2">{children}</div>
        </section>
    );
}

/** Privacy notice for employees, in plain English. */
export default function Privacy({ business, contacts, retentionYears, rtwRetentionYears, sponsored, company }: Props) {
    const processor = [company.name + (company.number ? ` (company number ${company.number})` : ''), company.address].filter(Boolean).join(', ');

    return (
        <PortalLayout title="Privacy notice">
            <PageHeader title="Your information and privacy" description={`How ${business} uses the information in this portal. Last updated ${company.updated}.`} />
            <Card className="p-5 sm:p-7">
                <Section title="Who is responsible">
                    <p>
                        {business} is your employer and decides how your information is used: it is the <strong>data controller</strong>.
                    </p>
                    <p>
                        {company.product} is provided by {processor}. It stores your information securely on {business}'s behalf, only as {business} instructs, and does not use it
                        for anything else: it is the <strong>data processor</strong>.
                    </p>
                </Section>

                <Section title="What is kept">
                    <ul className="list-disc space-y-1 pl-5">
                        <li>Your name, date of birth, nationality, address, phone and email</li>
                        <li>National Insurance number, passport details and, where you have one, your visa and share code</li>
                        <li>Right-to-work check results and the documents behind them</li>
                        <li>Your job, pay, hours, work site and start date</li>
                        <li>Absences: the dates and the type of absence. No medical details are recorded; a fit note is kept only as a file.</li>
                        <li>Documents you or your employer upload, such as your contract and payslips</li>
                        <li>A history of changes to your record: what changed, when and by whom</li>
                    </ul>
                </Section>

                <Section title="Why it is kept">
                    <p>Employers in the UK must check and keep a record of every employee's right to work.</p>
                    {sponsored ? (
                        <p>
                            Because {business} sponsors your visa, it must also keep the records the Home Office lists in its sponsor guidance and tell the Home Office about certain
                            changes, such as a new job title, lower pay or a long unpaid absence.
                        </p>
                    ) : (
                        <p>Where an employer sponsors workers, the Home Office also expects records to be kept for all staff, so they are kept the same way for everyone.</p>
                    )}
                    <p>
                        So the main legal reason for keeping it is that the law requires it (legal obligation). Some of it, such as your contact details and leave requests, is kept to
                        run your employment (contract).
                    </p>
                </Section>

                <Section title="Who can see it">
                    <p>
                        {business}'s admins and you. You see your own details and documents here. A Home Office compliance officer may ask {business} to show the records during a
                        visit. {company.name} can reach the systems only where needed to keep the service running. Your information is never sold or used for marketing.
                    </p>
                </Section>

                <Section title="How it is protected">
                    <p>
                        Files and sensitive numbers are encrypted. Passport, National Insurance and share code numbers are only ever shown as the last 4 characters. Every time someone
                        opens one of your documents, it is recorded.
                    </p>
                </Section>

                <Section title="How long it is kept">
                    <p>While you work for {business}. After you leave:</p>
                    <ul className="list-disc space-y-1 pl-5">
                        <li>Most of your record is deleted {years(retentionYears)} after your last day.</li>
                        <li>Right-to-work records are deleted {years(rtwRetentionYears)} after your last day, as the law requires them to be kept for that long.</li>
                    </ul>
                    <p>{business} is reminded when each deletion is due and confirms it; deleted files cannot be recovered.</p>
                </Section>

                <Section title="Your rights">
                    <p>You can ask {business} to:</p>
                    <ul className="list-disc space-y-1 pl-5">
                        <li>give you a copy of the information held about you;</li>
                        <li>correct anything that is wrong or incomplete;</li>
                        <li>delete or restrict information it no longer needs, or that it is not allowed to keep;</li>
                        <li>stop using information where you object, unless it has to keep it by law.</li>
                    </ul>
                    <p>
                        You can update your address, phone and email yourself under{' '}
                        <Link href="/me/update-details" className="font-semibold text-accent hover:underline">
                            Update my details
                        </Link>
                        . Requests are answered within one month.
                    </p>
                    <p>If you are unhappy with how your information is handled, you can complain to the Information Commissioner's Office at ico.org.uk or on 0303 123 1113.</p>
                </Section>

                <Section title="Questions">
                    <p>Contact {business}'s admin{contacts.length === 1 ? '' : 's'}:</p>
                    <ul className="flex flex-col gap-1">
                        {contacts.map((c) => (
                            <li key={c}>
                                <a href={`mailto:${c}`} className="font-semibold text-accent break-all hover:underline">
                                    {c}
                                </a>
                            </li>
                        ))}
                    </ul>
                </Section>
            </Card>
        </PortalLayout>
    );
}
