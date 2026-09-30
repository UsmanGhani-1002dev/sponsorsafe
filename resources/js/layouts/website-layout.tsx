import { Logo } from '@/components/logo';
import { Head, Link } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';

const nav = [
    { label: 'Features', href: '/#features' },
    { label: 'Pricing', href: '/#pricing' },
    { label: 'Training', href: '/#training' },
    { label: 'Contact', href: '/#contact' },
];

/** Public website: header with sign-in and "Start subscription", dark footer with the not-legal-advice line. */
export default function WebsiteLayout({ title, signedIn, children }: { title: string; signedIn?: string | null; children: ReactNode }) {
    const [open, setOpen] = useState(false);

    return (
        <div data-website className="flex min-h-screen flex-col bg-surface">
            <Head title={title} />
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">
                Skip to content
            </a>
            <header className="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                    <Link href="/" aria-label="SponsorSafe home">
                        <Logo />
                    </Link>
                    <nav aria-label="Main" className="hidden items-center gap-7 text-[15px] font-medium text-ink-2 md:flex">
                        {nav.map((n) => (
                            <a key={n.href} href={n.href} className="hover:text-accent">
                                {n.label}
                            </a>
                        ))}
                    </nav>
                    <div className="hidden items-center gap-3 md:flex">
                        {signedIn ? (
                            <a href={signedIn} className="text-[15px] font-semibold text-accent hover:underline">
                                Go to your account
                            </a>
                        ) : (
                            <Link href="/login" className="text-[15px] font-semibold text-ink-2 hover:text-accent">
                                Log in
                            </Link>
                        )}
                        <Link href="/signup" className="inline-flex min-h-11 items-center rounded-lg bg-accent-fill px-4 text-[15px] font-semibold text-white hover:bg-accent-fill-hover">
                            Start subscription
                        </Link>
                    </div>
                    <button type="button" className="inline-flex size-11 items-center justify-center rounded-lg text-ink-2 md:hidden" aria-expanded={open} aria-controls="mobile-menu" aria-label={open ? 'Close menu' : 'Open menu'} onClick={() => setOpen(!open)}>
                        {open ? <X size={22} aria-hidden /> : <Menu size={22} aria-hidden />}
                    </button>
                </div>
                {open && (
                    <nav id="mobile-menu" aria-label="Main" className="flex flex-col gap-1 border-t border-line px-4 py-3 md:hidden">
                        {nav.map((n) => (
                            <a key={n.href} href={n.href} onClick={() => setOpen(false)} className="flex min-h-11 items-center rounded-md px-2 font-medium text-ink-2 hover:bg-canvas">
                                {n.label}
                            </a>
                        ))}
                        <Link href={signedIn ?? '/login'} className="flex min-h-11 items-center rounded-md px-2 font-semibold text-accent">
                            {signedIn ? 'Go to your account' : 'Log in'}
                        </Link>
                        <Link href="/signup" className="mt-1 inline-flex min-h-11 items-center justify-center rounded-lg bg-accent-fill px-4 font-semibold text-white">
                            Start subscription
                        </Link>
                    </nav>
                )}
            </header>

            <main id="main" className="flex-1">
                {children}
            </main>

            <footer className="bg-[#101828] text-[#D0D5DD]">
                <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-10 sm:px-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <span className="inline-flex items-center gap-2.5 text-white">
                            <span aria-hidden className="inline-flex size-7 items-center justify-center rounded-lg bg-accent-fill text-sm font-bold">
                                S
                            </span>
                            <span className="font-semibold">SponsorSafe</span>
                        </span>
                        <nav aria-label="Footer" className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <a href="/#pricing" className="hover:text-white">Pricing</a>
                            <a href="/#contact" className="hover:text-white">Contact</a>
                            <Link href="/login" className="hover:text-white">Log in</Link>
                            <Link href="/privacy" className="hover:text-white">Privacy</Link>
                            <Link href="/terms" className="hover:text-white">Terms</Link>
                        </nav>
                    </div>
                    <p className="max-w-3xl text-[13px] leading-relaxed text-[#98A2B3]">
                        SponsorSafe helps you keep records and meet deadlines. It is not legal advice. Always check the current Home Office sponsor guidance. A service by Enovtec, Southampton.
                    </p>
                </div>
            </footer>
        </div>
    );
}
