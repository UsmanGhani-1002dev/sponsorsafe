import { Flash } from '@/components/flash';
import { Logo } from '@/components/logo';
import { ThemeToggle } from '@/components/theme-toggle';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, FileText, Home, Inbox, LogOut, PencilLine, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';

const nav = [
    { label: 'Home', href: '/me', icon: Home },
    { label: 'My documents', href: '/me/documents', icon: FileText },
    { label: 'Leave and sickness', href: '/me/leave', icon: CalendarDays },
    { label: 'Update my details', href: '/me/update-details', icon: PencilLine },
    { label: 'My requests', href: '/me/requests', icon: Inbox },
    { label: 'My details', href: '/me/details', icon: UserRound },
];

/** Employee portal: mobile-first. Phones get a scrolling menu under the header; larger screens a sidebar. */
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
                    <span className="hidden text-sm text-ink-2 sm:inline">{auth.user?.name}</span>
                    <ThemeToggle />
                    <button
                        onClick={() => router.post('/logout')}
                        aria-label="Log out"
                        className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 text-sm font-semibold text-ink-2 hover:bg-canvas"
                    >
                        <LogOut size={16} aria-hidden /> <span className="hidden sm:inline">Log out</span>
                    </button>
                </div>
            </header>

            <nav aria-label="Portal" className="flex gap-1 overflow-x-auto border-b border-line bg-surface px-3 py-2 md:hidden">
                {nav.map((i) => link(i, true))}
            </nav>

            <div className="flex min-h-0 flex-1">
                <nav aria-label="Portal" className="hidden w-60 shrink-0 flex-col gap-0.5 border-r border-line bg-surface p-3 md:flex">
                    {nav.map((i) => link(i, false))}
                </nav>
                <main id="main" className="min-w-0 flex-1 px-4 py-6 sm:px-8 sm:py-8">
                    <div className="mx-auto max-w-3xl">
                        <Flash />
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
