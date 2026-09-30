import WebsiteLayout from '@/layouts/website-layout';
import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';

/** Until online sign-up with card or PayPal payment opens (Stage 7b), new customers are set up by the team. */
export default function Signup({ plan }: { plan: { price: string; limit: number; training: string } }) {
    return (
        <WebsiteLayout title="Start your subscription">
            <section className="mx-auto max-w-2xl px-4 py-16 sm:px-6">
                <p className="text-sm font-semibold tracking-wide text-accent uppercase">Sponsor plan</p>
                <h1 className="mt-2 text-3xl font-semibold">Start your subscription</h1>
                <p className="mt-3 text-[17px] text-ink-2">
                    £{plan.price} per month for up to {plan.limit} employees. No setup fee, no contract, cancel any time.
                </p>
                <div className="mt-8 rounded-2xl border border-line bg-canvas p-6">
                    <h2 className="text-lg font-semibold">Online sign-up opens soon</h2>
                    <p className="mt-2 text-[15px] leading-relaxed text-ink-2">
                        In the meantime we set new customers up personally. Send us a message with your business name and how many people you employ, and we'll have your account ready within one working day.
                    </p>
                    <ul className="mt-4 flex flex-col gap-2 text-[15px]">
                        {['Your account set up for you', 'A link to set your password', 'Help adding your first employees'].map((t) => (
                            <li key={t} className="flex items-center gap-2">
                                <Check size={18} aria-hidden className="text-accent" /> {t}
                            </li>
                        ))}
                    </ul>
                    <a href="/?topic=General%20question#contact" className="mt-6 inline-flex min-h-12 items-center rounded-lg bg-accent-fill px-5 font-semibold text-white hover:bg-accent-fill-hover">
                        Contact us to get started
                    </a>
                </div>
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
