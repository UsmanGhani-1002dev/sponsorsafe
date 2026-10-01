import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Super admin: dark header (My account, Log out), sidebar with the sections (unbuilt ones show "Soon"). */
export default function OpsLayout({ title, base, children }: { title: string; base: string; children: ReactNode }) {
    const { ops } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0];
    const nav = [
        { label: 'Businesses', href: base, ready: true },
        { label: 'Plans and pricing', href: `${base}/pricing`, ready: true },
        { label: 'Payment gateways', href: `${base}/gateways`, ready: true },
        { label: 'Enquiries and training', href: `${base}/enquiries`, ready: true, count: ops?.newEnquiries ?? 0 },
        { label: 'Super admins', href: `${base}/super-admins`, ready: true },
        { label: 'AI chat assistant', href: `${base}/assistant`, ready: false },
    ];

    return (
        <div className="flex min-h-screen flex-col">
            <Head title={title} />
            <header className="flex h-16 shrink-0 items-center justify-between bg-[#101828] px-4 text-white sm:px-6">
                <span className="inline-flex items-center gap-3">
                    <span aria-hidden className="inline-flex size-7 items-center justify-center rounded-lg bg-accent-fill text-sm font-bold">
                        S
                    </span>
                    <span className="font-semibold">Super admin</span>
                    <span className="hidden rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70 sm:inline">2FA verified</span>
                </span>
                <span className="flex items-center gap-2">
                    <Link
                        href={`${base}/account`}
                        aria-current={path === `${base}/account` ? 'page' : undefined}
                        className="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-white/80 hover:bg-white/10 hover:text-white"
                    >
                        My account
                    </Link>
                    <button onClick={() => router.post(`${base}/logout`)} className="min-h-10 rounded-lg border border-white/20 px-3 text-sm font-semibold hover:bg-white/10">
                        Log out
                    </button>
                </span>
            </header>
            <div className="flex min-h-0 flex-1 flex-col md:flex-row">
                <nav aria-label="Super admin" className="flex shrink-0 gap-1 overflow-x-auto border-b border-line bg-surface p-3 md:w-60 md:flex-col md:border-r md:border-b-0">
                    {nav.map((item) =>
                        item.ready ? (
                            <Link
                                key={item.label}
                                href={item.href}
                                aria-current={path === item.href ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-11 shrink-0 items-center justify-between gap-2 rounded-md px-3 text-sm font-medium',
                                    path === item.href ? 'bg-accent-soft font-semibold text-accent-strong' : 'text-ink-2 hover:bg-canvas',
                                )}
                            >
                                {item.label}
                                {!!item.count && <span className="rounded-full bg-accent-fill px-2 py-0.5 text-xs font-semibold text-white">{item.count}</span>}
                            </Link>
                        ) : (
                            <span key={item.label} aria-disabled className="flex min-h-11 shrink-0 items-center justify-between gap-2 rounded-md px-3 text-sm font-medium text-muted">
                                {item.label} <Badge tone="grey">Soon</Badge>
                            </span>
                        ),
                    )}
                </nav>
                <main className="min-w-0 flex-1 px-4 py-8 sm:px-8">
                    <div className="mx-auto max-w-6xl">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
