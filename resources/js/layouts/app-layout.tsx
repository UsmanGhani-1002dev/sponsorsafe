import { Flash } from '@/components/flash';
import { Logo } from '@/components/logo';
import { ThemeToggle } from '@/components/theme-toggle';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ClipboardCheck, FileWarning, Inbox, LayoutDashboard, LogOut, Settings, Users } from 'lucide-react';
import type { ReactNode } from 'react';

// Items not built yet are shown so the structure is clear; they switch on stage by stage.
const nav = [
    { label: 'Dashboard', href: '/app', icon: LayoutDashboard, ready: true },
    { label: 'Employees', href: '/app/employees', icon: Users, ready: true },
    { label: 'Absence', href: '/app/absence', icon: ClipboardCheck, ready: true },
    { label: 'Home Office reports', href: '/app/reports', icon: FileWarning, ready: true },
    { label: 'Requests', href: '/app/requests', icon: Inbox, ready: false },
    { label: 'Settings', href: '/app/settings', icon: Settings, ready: true },
];

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0];
    const isActive = (href: string) => (href === '/app' ? path === '/app' : path === href || path.startsWith(href + '/'));

    return (
        <div className="flex min-h-screen flex-col">
            <Head title={title} />
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">
                Skip to content
            </a>
            <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-line bg-surface px-4 sm:px-6">
                <div className="flex min-w-0 items-center gap-4">
                    <Logo />
                    <span className="hidden truncate text-sm text-muted sm:inline">{auth.user?.business}</span>
                </div>
                <div className="flex items-center gap-2 sm:gap-3">
                    <span className="hidden text-sm text-ink-2 sm:inline">{auth.user?.name}</span>
                    <ThemeToggle />
                    <button
                        onClick={() => router.post('/logout')}
                        className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 text-sm font-semibold text-ink-2 hover:bg-canvas"
                    >
                        <LogOut size={16} aria-hidden /> Log out
                    </button>
                </div>
            </header>

            {/* Phone and small tablet: the same menu as a scrolling strip. */}
            <nav aria-label="Main" className="flex gap-1 overflow-x-auto border-b border-line bg-surface px-3 py-2 md:hidden">
                {nav
                    .filter((i) => i.ready)
                    .map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            prefetch
                            aria-current={isActive(item.href) ? 'page' : undefined}
                            className={cn('flex min-h-11 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium', isActive(item.href) ? 'bg-accent-soft font-semibold text-accent-strong' : 'text-ink-2')}
                        >
                            <item.icon size={16} aria-hidden /> {item.label}
                        </Link>
                    ))}
            </nav>

            <div className="flex min-h-0 flex-1">
                <nav aria-label="Main" className="hidden w-60 shrink-0 flex-col gap-0.5 border-r border-line bg-surface p-3 md:flex">
                    {nav.map((item) =>
                        item.ready ? (
                            <Link
                                key={item.href}
                                href={item.href}
                                prefetch
                                aria-current={isActive(item.href) ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-11 items-center gap-2.5 rounded-md px-3 text-sm font-medium',
                                    isActive(item.href) ? 'bg-accent-soft font-semibold text-accent-strong' : 'text-ink-2 hover:bg-canvas',
                                )}
                            >
                                <item.icon size={18} aria-hidden /> {item.label}
                            </Link>
                        ) : (
                            <span key={item.href} aria-disabled className="flex min-h-11 cursor-default items-center justify-between gap-2.5 rounded-md px-3 text-sm font-medium text-muted">
                                <span className="flex items-center gap-2.5">
                                    <item.icon size={18} aria-hidden /> {item.label}
                                </span>
                                <Badge tone="grey">Soon</Badge>
                            </span>
                        ),
                    )}
                </nav>
                <main id="main" className="min-w-0 flex-1 px-4 py-6 sm:px-10 sm:py-8">
                    <div className="mx-auto max-w-6xl">
                        <Flash />
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
