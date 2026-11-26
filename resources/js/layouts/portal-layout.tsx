import { AccountMenu } from '@/components/account-menu';
import { Logo } from '@/components/logo';
import { ThemeToggle } from '@/components/theme-toggle';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, FileText, Home, Inbox, KeyRound, PencilLine, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';

const nav = [
    { label: 'Home', href: '/me', icon: Home },
    { label: 'My documents', href: '/me/documents', icon: FileText },
    { label: 'Leave and sickness', href: '/me/leave', icon: CalendarDays },
    { label: 'Update my details', href: '/me/update-details', icon: PencilLine },
    { label: 'My requests', href: '/me/requests', icon: Inbox },
    { label: 'My details', href: '/me/details', icon: UserRound },
];

// Phone tab bar: five short labels (the sidebar keeps the full list).
const tabs = [
    { label: 'Home', href: '/me', icon: Home },
    { label: 'Documents', href: '/me/documents', icon: FileText },
    { label: 'Leave', href: '/me/leave', icon: CalendarDays },
    { label: 'Requests', href: '/me/requests', icon: Inbox },
    { label: 'Me', href: '/me/details', icon: UserRound, also: ['/me/update-details', '/me/privacy'] },
];

/** Employee portal: mobile-first. Phones get a tab bar at the bottom; larger screens a sidebar. */
export default function PortalLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0];
    const isActive = (href: string) => (href === '/me' ? path === '/me' : path === href || path.startsWith(href + '/'));

    const link = (item: (typeof nav)[number], compact: boolean) => (
        <Link
            key={item.href}
            href={item.href}
            prefetch
            aria-current={isActive(item.href) ? 'page' : undefined}
            className={cn(
                'flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-medium',
                compact && 'shrink-0',
                isActive(item.href) ? 'bg-accent-soft font-semibold text-accent-strong' : 'text-ink-2 hover:bg-canvas',
            )}
        >
            <item.icon size={compact ? 16 : 18} aria-hidden /> {item.label}
        </Link>
    );

    return (
        <div className="flex min-h-screen flex-col">
            <Head title={title} />
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">
                Skip to content
            </a>
            <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-line bg-surface px-4 sm:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    <Logo label={false} />
                    <div className="min-w-0">
                        <p className="text-[15px] leading-tight font-semibold">Employee portal</p>
                        <p className="truncate text-[13px] leading-tight text-muted">{auth.user?.business}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <ThemeToggle />
                    {auth.user && (
                        <AccountMenu
                            user={auth.user}
                            links={[
                                { label: 'My details', href: '/me/details', icon: UserRound },
                                { label: 'Update my details', href: '/me/update-details', icon: PencilLine },
                                { label: 'Change password', href: '/me/details#password', icon: KeyRound },
                            ]}
                        />
                    )}
                </div>
            </header>


            <div className="flex min-h-0 flex-1">
                <nav aria-label="Portal" className="hidden w-60 shrink-0 flex-col gap-0.5 border-r border-line bg-surface p-3 md:flex">
                    {nav.map((i) => link(i, false))}
                </nav>
                <main id="main" className="min-w-0 flex-1 px-4 pt-6 pb-28 sm:px-8 sm:pt-8 md:pb-8">
                    <div className="mx-auto max-w-3xl">
                        {children}
                        <p className="mt-10 border-t border-line pt-4 text-[13px] text-muted">
                            <Link href="/me/privacy" className="inline-flex min-h-11 items-center hover:text-ink hover:underline">
                                How your information is used (privacy notice)
                            </Link>
                        </p>
                    </div>
                </main>
            </div>

            {/* Phones: an app-style tab bar fixed at the bottom. "Update my details" is on Home and in the account menu. */}
            <nav aria-label="Portal" className="portal-tabbar fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] md:hidden print:hidden">
                <ul className="grid grid-cols-5">
                    {tabs.map((t) => {
                        const active = isActive(t.href) || (t.also ?? []).some(isActive);
                        return (
                            <li key={t.href}>
                                <Link
                                    href={t.href}
                                    prefetch
                                    aria-current={active ? 'page' : undefined}
                                    className={cn('flex min-h-16 flex-col items-center justify-center gap-1 text-[11px] font-medium', active ? 'text-accent-strong' : 'text-muted hover:text-ink')}
                                >
                                    <span className={cn('inline-flex h-7 w-12 items-center justify-center rounded-full', active && 'bg-accent-soft')}>
                                        <t.icon size={20} aria-hidden />
                                    </span>
                                    {t.label}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </div>
    );
}
