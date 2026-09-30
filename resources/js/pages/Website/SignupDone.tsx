import WebsiteLayout from '@/layouts/website-layout';
import { Link } from '@inertiajs/react';
import { CheckCircle2, Clock } from 'lucide-react';

/** Back from Stripe: "You're all set" once the payment is confirmed with Stripe. */
export default function SignupDone({ confirmed, first, business, email }: { confirmed: boolean; first: string | null; business: string | null; email: string | null }) {
    return (
        <WebsiteLayout title={confirmed ? "You're all set" : 'Confirming your payment'}>
            <section className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
                {confirmed ? (
                    <div className="flex flex-col items-start gap-3 rounded-2xl border border-green-200 bg-green-50 p-7 sm:p-8 dark:border-green-900 dark:bg-green-950/40" role="status">
                        <CheckCircle2 size={28} aria-hidden className="text-green-700 dark:text-green-300" />
                        <h1 className="text-[22px] font-semibold text-green-800 dark:text-green-200">You're all set, {first ?? 'there'}</h1>
                        <p className="text-base leading-relaxed text-ink-2">
                            {business} is now subscribed. We've emailed {email} a link to set your password. Then add your first employees in a few minutes.
                        </p>
                        <Link href="/login" className="mt-2 inline-flex min-h-11 items-center rounded-lg bg-accent-fill px-5 font-semibold text-white hover:bg-accent-fill-hover">
                            Go to login
                        </Link>
                    </div>
                ) : (
                    <div className="flex flex-col items-start gap-3 rounded-2xl border border-line bg-canvas p-7 sm:p-8" role="status">
                        <Clock size={28} aria-hidden className="text-accent" />
                        <h1 className="text-[22px] font-semibold">We're confirming your payment</h1>
                        <p className="text-base leading-relaxed text-ink-2">
                            This usually takes a moment. As soon as it's confirmed we'll email {email ?? 'you'} a link to set your password. If nothing arrives within an hour, please{' '}
                            <a href="/?topic=Existing%20customer%20support#contact" className="font-semibold text-accent hover:underline">
                                contact us
                            </a>
                            .
                        </p>
                    </div>
                )}
            </section>
        </WebsiteLayout>
    );
}
