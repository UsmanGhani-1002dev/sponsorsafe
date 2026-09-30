import { Alert } from '@/components/ui/alert';
import WebsiteLayout from '@/layouts/website-layout';

/**
 * Privacy policy and terms. These are outlines only: the final wording must come from Enovtec
 * (and ideally a solicitor) before go-live.
 */
const pages = {
    privacy: {
        title: 'Privacy policy',
        sections: [
            ['Who we are', 'SponsorSafe is a service provided by Enovtec, Southampton, United Kingdom.'],
            ['What we collect', 'Details you give us through the contact form (name, email, phone and your message), and, for customers, the business and employee records they keep in SponsorSafe.'],
            ['How we use it', 'To reply to enquiries, provide the service, and meet our legal obligations. Customers decide what employee information they keep; we process it on their behalf.'],
            ['Where it is kept', 'On servers in the UK or EEA. Documents and identity numbers are encrypted, and every view of a document is logged.'],
            ['How long we keep it', 'Enquiries: as long as needed to reply and follow up. Employee records: deleted after the retention period the customer sets once employment ends.'],
            ['AI chat assistant', 'If you use the chat on this website, your messages are sent to our AI provider to produce a reply and the conversation is kept for 90 days.'],
            ['Your rights', 'You can ask to see, correct or delete your information, and you can complain to the Information Commissioner’s Office (ICO).'],
        ],
    },
    terms: {
        title: 'Terms of service',
        sections: [
            ['The service', 'SponsorSafe helps UK sponsor licence holders keep records and meet Home Office deadlines. It does not report to the Home Office for you.'],
            ['Not legal advice', 'SponsorSafe is not legal advice. Always check the current Home Office sponsor guidance and take advice from a regulated adviser on individual cases.'],
            ['Subscription', 'Monthly, paid in advance by card or PayPal. No setup fee and no minimum term; cancel any time and the subscription ends at the end of the paid month.'],
            ['Price changes', 'We will email existing subscribers at least 30 days before any price change.'],
            ['Your data', 'You own your business and employee records. You are responsible for the information you enter and for reporting on the Sponsor Management System.'],
            ['Suspension', 'If a payment fails and is not resolved, access is paused. Your data is kept while the account is paused.'],
        ],
    },
} as const;

export default function Legal({ page }: { page: keyof typeof pages }) {
    const p = pages[page];

    return (
        <WebsiteLayout title={p.title}>
            <article className="mx-auto max-w-3xl px-4 py-14 sm:px-6">
                <h1 className="text-3xl font-semibold">{p.title}</h1>
                <div className="mt-5">
                    <Alert tone="warning">Draft outline. The final wording will be published before SponsorSafe opens to customers.</Alert>
                </div>
                {p.sections.map(([heading, text]) => (
                    <section key={heading} className="mt-8">
                        <h2 className="text-lg font-semibold">{heading}</h2>
                        <p className="mt-2 text-[16px] leading-relaxed text-ink-2">{text}</p>
                    </section>
                ))}
            </article>
        </WebsiteLayout>
    );
}
