import WebsiteLayout from '@/layouts/website-layout';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface Company {
    name: string;
    number: string | null;
    address: string | null;
    ico: string | null;
    email: string;
    updated: string;
    product: string;
}

interface Props {
    page: 'privacy' | 'terms';
    company: Company;
    plans: { tiers: { key: string; name: string; price: string; limit: number; from: number }[]; training: string; corporateFrom: number };
    graceDays: number;
}

type Section = { id: string; title: string; body: ReactNode };

const P = ({ children }: { children: ReactNode }) => <p className="text-[16px] leading-relaxed text-ink-2">{children}</p>;
const List = ({ items }: { items: ReactNode[] }) => (
    <ul className="list-disc space-y-1.5 pl-5 text-[16px] leading-relaxed text-ink-2">
        {items.map((item, i) => (
            <li key={i}>{item}</li>
        ))}
    </ul>
);
const Mail = ({ to }: { to: string }) => (
    <a href={`mailto:${to}`} className="font-semibold text-accent hover:underline">
        {to}
    </a>
);

/** "Enovtec Ltd (company number 01234567), Southampton, United Kingdom" — only the parts that are set. */
function who(c: Company) {
    return [c.name + (c.number ? ` (company number ${c.number})` : ''), c.address].filter(Boolean).join(', ');
}

function privacy(c: Company): Section[] {
    const we = c.name;
    return [
        {
            id: 'who',
            title: 'Who we are',
            body: (
                <>
                    <P>
                        {c.product} is run by {who(c)} ("we", "us"). This policy explains how we use personal information when you visit our website, contact us, or use {c.product} as a
                        customer.
                    </P>
                    {c.ico && <P>We are registered with the Information Commissioner's Office (ICO) under registration number {c.ico}.</P>}
                    <P>
                        Questions about this policy: <Mail to={c.email} />.
                    </P>
                </>
            ),
        },
        {
            id: 'roles',
            title: 'Our role, and your employer’s',
            body: (
                <>
                    <P>
                        For website visitors, people who contact us and our customers' account details, we decide how the information is used: we are the <strong>controller</strong>.
                    </P>
                    <P>
                        For the employee records our customers keep in {c.product}, the customer (the employer) is the controller and we act only on its instructions: we are its{' '}
                        <strong>processor</strong>. If you are an employee and have a question about your records, please contact your employer first. Our{' '}
                        <Link href="/terms#data-processing" className="font-semibold text-accent hover:underline">
                            data processing terms
                        </Link>{' '}
                        set out how we handle that information.
                    </P>
                </>
            ),
        },
        {
            id: 'collect',
            title: 'What we collect',
            body: (
                <List
                    items={[
                        <>
                            <strong>When you contact us:</strong> your name, email, phone number (optional), the topic and your message.
                        </>,
                        <>
                            <strong>If you use the chat assistant on our website:</strong> your messages and our replies, and your name and email if you ask us to get in touch.
                        </>,
                        <>
                            <strong>When you subscribe:</strong> your business name, sponsor licence number, phone, registered address, and the name and email of each admin login.
                        </>,
                        <>
                            <strong>Payments:</strong> handled by Stripe (card) or PayPal. Card details go straight to them and never reach us. We keep the payment method type, the
                            last 4 digits of a card, and whether payments succeeded.
                        </>,
                        <>
                            <strong>When you use {c.product}:</strong> sign-in times, IP addresses and a log of important actions (for example who viewed a document), which we keep
                            for security.
                        </>,
                    ]}
                />
            ),
        },
        {
            id: 'use',
            title: 'How we use it, and why we are allowed to',
            body: (
                <List
                    items={[
                        <>To reply to your enquiry or chat request: our legitimate interest in answering people who contact us.</>,
                        <>To provide {c.product}, take payment and send service emails (such as reminders, payment notices and price changes): to perform our contract with you.</>,
                        <>To keep the service secure and to investigate misuse: our legitimate interest in protecting our customers and their data.</>,
                        <>To keep accounting records and meet other legal duties: legal obligation.</>,
                    ]}
                />
            ),
        },
        {
            id: 'share',
            title: 'Who we share it with',
            body: (
                <>
                    <P>We never sell personal information or use it for advertising. We share it only with the services we need to run {c.product}:</P>
                    <List
                        items={[
                            <>our hosting provider, which stores the service and sends our emails;</>,
                            <>Stripe and PayPal, to take payments;</>,
                            <>our AI provider, which receives chat assistant messages to write a reply (it is not used for anything else);</>,
                            <>professional advisers, and authorities where the law requires it.</>,
                        ]}
                    />
                    <P>
                        Some of these providers may process information outside the UK. Where they do, they are bound by safeguards approved under UK data protection law, such as the UK
                        International Data Transfer Agreement.
                    </P>
                </>
            ),
        },
        {
            id: 'keep',
            title: 'How long we keep it',
            body: (
                <List
                    items={[
                        <>Enquiries: up to 2 years after our last contact.</>,
                        <>Chat assistant conversations: 90 days.</>,
                        <>Customer account details: while the subscription is running, and while a paused account can still be reactivated.</>,
                        <>Billing and accounting records: 6 years, as tax law requires.</>,
                        <>
                            Employee records: as long as the customer keeps them. When employment ends, {c.product} prompts the customer to delete them after the retention period it has
                            set (by default 1 year, and 2 years for right-to-work records).
                        </>,
                        <>If a customer asks us to delete their account, we do so within 30 days, apart from the billing records above.</>,
                    ]}
                />
            ),
        },
        {
            id: 'security',
            title: 'How we protect it',
            body: (
                <P>
                    {c.product} is served over HTTPS only. Documents and identity numbers are encrypted. Admins must use an authenticator app to sign in, and every view and download
                    of a document is logged. Only {we}'s staff who need to run the service can reach the systems behind it.
                </P>
            ),
        },
        {
            id: 'cookies',
            title: 'Cookies',
            body: (
                <P>
                    We only use cookies that the website needs to work: to keep you signed in and to protect forms against misuse. We do not use advertising or tracking cookies. Your
                    choice of light or dark mode is stored in your own browser.
                </P>
            ),
        },
        {
            id: 'rights',
            title: 'Your rights',
            body: (
                <>
                    <P>
                        You can ask to see the personal information we hold about you, to correct it, to delete it, to limit or object to how we use it, or to receive a copy in a common
                        format. Email <Mail to={c.email} />. We reply within one month.
                    </P>
                    <P>If you are unhappy with how we handle your information, you can complain to the Information Commissioner's Office at ico.org.uk or on 0303 123 1113.</P>
                </>
            ),
        },
        {
            id: 'changes',
            title: 'Changes to this policy',
            body: <P>If we make important changes, we will update this page and tell customers by email.</P>,
        },
    ];
}

function terms(c: Company, plans: Props['plans'], graceDays: number): Section[] {
    const largest = plans.corporateFrom - 1;
    return [
        {
            id: 'about',
            title: 'About these terms',
            body: (
                <P>
                    These terms are an agreement between {who(c)} ("we", "us") and the business that subscribes to {c.product} ("you"). By starting a subscription, you accept them on
                    behalf of your business. {c.product} is for businesses only; it is not offered to consumers.
                </P>
            ),
        },
        {
            id: 'service',
            title: 'What the service does, and what it does not',
            body: (
                <>
                    <P>
                        {c.product} helps UK sponsor licence holders keep employee records, track right-to-work checks and absences, and see what must be reported to the Home Office and
                        by when.
                    </P>
                    <List
                        items={[
                            <>It does not report anything to the Home Office for you. You remain responsible for reporting on the Sponsor Management System and for meeting your sponsor duties.</>,
                            <>
                                It is not legal advice. The rules and deadlines it uses reflect UK sponsor guidance as we understand it, and you can adjust them. Always check the current
                                Home Office guidance, and take advice from a regulated immigration adviser or solicitor on individual cases.
                            </>,
                            <>It is not payroll or HR software.</>,
                        ]}
                    />
                </>
            ),
        },
        {
            id: 'accounts',
            title: 'Your account',
            body: (
                <List
                    items={[
                        <>Keep the information you give us accurate, and keep sign-in details safe. Every admin must use an authenticator app.</>,
                        <>You are responsible for the people you give access to, including employees you invite to the employee portal, and for what is entered into your account.</>,
                        <>Tell us straight away if you think someone has accessed your account without permission.</>,
                    ]}
                />
            ),
        },
        {
            id: 'plans',
            title: 'Plans and payment',
            body: (
                <>
                    <List
                        items={[
                            ...plans.tiers.map((t) => (
                                <>
                                    {t.name}: £{t.price} a month for up to {t.limit} current employees.
                                </>
                            )),
                            <>Corporate: more than {largest} employees, at a price we agree with you in writing.</>,
                            <>Optional 1-to-1 training: £{plans.training} per person, arranged with you by email and paid in advance.</>,
                        ]}
                    />
                    <P>
                        Subscriptions are paid monthly in advance by card (through Stripe) or PayPal, in pounds sterling. Where VAT applies it is shown on your invoice. There is no setup
                        fee and no minimum term.
                    </P>
                    <P>
                        You can move to a larger plan at any time, and to a smaller one if your current employees fit it. The new employee limit applies straight away and the new price
                        from your next payment. You cannot add more current employees than your plan allows.
                    </P>
                    <P>We give existing subscribers at least 30 days' notice by email of any price change. The new price applies from the first payment after that date.</P>
                </>
            ),
        },
        {
            id: 'late',
            title: 'If a payment fails',
            body: (
                <P>
                    We email your admins and keep your account working for {graceDays} days while you update your payment details. If payment is still not made after that, we pause
                    access to the account. Your records are kept while the account is paused, and access returns as soon as payment is made.
                </P>
            ),
        },
        {
            id: 'cancel',
            title: 'Cancelling',
            body: (
                <P>
                    You can cancel at any time from Settings → Manage billing, or by emailing <Mail to={c.email} />. Your access continues until the end of the period you have
                    paid for. We do not refund part months. After cancelling, you can ask us to delete your account and records.
                </P>
            ),
        },
        {
            id: 'your-data',
            title: 'Your data',
            body: (
                <P>
                    You own the business and employee records you keep in {c.product}. You can export an employee's records (the compliance pack) and absence records at any time. We
                    use your records only to provide the service to you.
                </P>
            ),
        },
        {
            id: 'data-processing',
            title: 'Data processing terms',
            body: (
                <>
                    <P>
                        For the personal information about your employees that you keep in {c.product}, you are the controller and we are your processor. These terms form the
                        agreement required by Article 28 of the UK GDPR. We will:
                    </P>
                    <List
                        items={[
                            <>process the information only to provide {c.product}, following your instructions given through the service or in writing, unless the law requires otherwise;</>,
                            <>make sure anyone who can access it is bound to keep it confidential;</>,
                            <>keep it secure, including encryption of documents and identity numbers, two-step sign-in for admins and a log of every document viewed;</>,
                            <>
                                use only these sub-processors: our UK hosting provider (storage and email) and, for billing only, Stripe or PayPal. We will tell you before adding or
                                replacing one, and you may object;
                            </>,
                            <>help you respond to requests from employees exercising their data protection rights, and with any data protection impact assessment;</>,
                            <>tell you without undue delay, and within 48 hours, if we become aware of a personal data breach affecting your records;</>,
                            <>delete your records within 30 days of your request when the subscription ends, unless the law requires us to keep them;</>,
                            <>give you the information you reasonably need to show that these duties are met.</>,
                        ]}
                    />
                    <P>
                        You confirm that you have a lawful basis for the information you enter, and that you have told your employees how it is used. {c.product} includes a privacy notice
                        your employees can read in their portal.
                    </P>
                </>
            ),
        },
        {
            id: 'use',
            title: 'Fair use',
            body: (
                <P>
                    Do not use {c.product} for anything unlawful, to store information unrelated to employment and sponsor duties, or to try to access other customers' data or disrupt
                    the service. We may suspend an account that does.
                </P>
            ),
        },
        {
            id: 'availability',
            title: 'Availability and changes to the service',
            body: (
                <P>
                    We aim to keep {c.product} available at all times, but there may be short interruptions for maintenance or for reasons outside our control. We keep improving the
                    service and may change features; we will not remove a core feature without telling you first.
                </P>
            ),
        },
        {
            id: 'liability',
            title: 'Our liability',
            body: (
                <>
                    <P>
                        Nothing in these terms limits liability for death or personal injury caused by negligence, for fraud, or for anything else that cannot be limited by law.
                    </P>
                    <P>
                        Otherwise, we are not liable for loss of profits, business or goodwill, or for indirect losses. We are not responsible for decisions you make or reports you do or
                        do not make to the Home Office, or for action the Home Office takes about your sponsor licence. Our total liability in any 12 months is limited to the
                        subscription fees you paid in that period.
                    </P>
                </>
            ),
        },
        {
            id: 'changes',
            title: 'Changes to these terms',
            body: <P>We will give you at least 30 days' notice by email of any important change. If you do not agree, you can cancel before it takes effect.</P>,
        },
        {
            id: 'law',
            title: 'Law',
            body: <P>These terms are governed by the law of England and Wales, and the courts of England and Wales have exclusive jurisdiction.</P>,
        },
        {
            id: 'contact',
            title: 'Contact',
            body: (
                <P>
                    {who(c)}. Email: <Mail to={c.email} />.
                </P>
            ),
        },
    ];
}

export default function Legal({ page, company, plans, graceDays }: Props) {
    const title = page === 'privacy' ? 'Privacy policy' : 'Terms of service';
    const sections = page === 'privacy' ? privacy(company) : terms(company, plans, graceDays);

    return (
        <WebsiteLayout title={title}>
            <article className="mx-auto max-w-3xl px-4 py-14 sm:px-6">
                <h1 className="text-3xl font-semibold">{title}</h1>
                <p className="mt-2 text-sm text-muted">Last updated {company.updated}</p>

                <nav aria-label="On this page" className="mt-8 rounded-xl border border-line bg-canvas p-5">
                    <p className="text-sm font-semibold">On this page</p>
                    <ol className="mt-2 grid list-decimal gap-x-8 gap-y-1 pl-5 text-[15px] sm:grid-cols-2">
                        {sections.map((s) => (
                            <li key={s.id}>
                                <a href={`#${s.id}`} className="text-accent hover:underline">
                                    {s.title}
                                </a>
                            </li>
                        ))}
                    </ol>
                </nav>

                {sections.map((s, i) => (
                    <section key={s.id} id={s.id} className="mt-10 flex scroll-mt-24 flex-col gap-3">
                        <h2 className="text-xl font-semibold">
                            {i + 1}. {s.title}
                        </h2>
                        {s.body}
                    </section>
                ))}

                <p className="mt-12 border-t border-line pt-6 text-sm text-muted">
                    See also our {page === 'privacy' ? 'Terms of service' : 'Privacy policy'}:{' '}
                    <Link href={page === 'privacy' ? '/terms' : '/privacy'} className="font-semibold text-accent hover:underline">
                        {page === 'privacy' ? '/terms' : '/privacy'}
                    </Link>
                </p>
            </article>
        </WebsiteLayout>
    );
}
