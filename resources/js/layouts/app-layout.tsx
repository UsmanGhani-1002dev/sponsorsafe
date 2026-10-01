import { CommandPalette, isMac, usePaletteShortcut } from '@/components/command-palette';
import { Logo } from '@/components/logo';
import { ThemeToggle } from '@/components/theme-toggle';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ClipboardCheck, FileWarning, Inbox, LayoutDashboard, LogOut, Search, Settings, Users } from 'lucide-react';
import { useState, type ReactNode } from 'react';

// Items not built yet are shown so the structure is clear; they switch on stage by stage.
const nav = [
    { label: 'Dashboard', href: '/app', icon: LayoutDashboard, ready: true },
    { label: 'Employees', href: '/app/employees', icon: Users, ready: true },
    { label: 'Absence', href: '/app/absence', icon: ClipboardCheck, ready: true },
    { label: 'Home Office reports', href: '/app/reports', icon: FileWarning, ready: true },
    { label: 'Requests', href: '/app/requests', icon: Inbox, ready: true },
    { label: 'Settings', href: '/app/settings', icon: Settings, ready: true },
];

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth, billing } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0];
    const isActive = (href: string) => (href === '/app' ? path === '/app' : path === href || path.startsWith(href + '/'));
    const [palette, setPalette] = useState(false);
    usePaletteShortcut(setPalette);

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
                    <button
                        type="button"
                        onClick={() => setPalette(true)}
                        aria-label="Search employees, screens and actions"
                        aria-keyshortcuts={isMac ? 'Meta+K' : 'Control+K'}
                        className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 text-sm text-muted hover:bg-canvas lg:w-56"
                    >
                        <Search size={16} aria-hidden />
                        <span className="hidden lg:inline">Search…</span>
                        <kbd className="ml-auto hidden rounded border border-line px-1.5 text-xs lg:inline">{isMac ? '⌘K' : 'Ctrl K'}</kbd>
                    </button>
                    <span className="hidden text-sm text-ink-2 xl:inline">{auth.user?.name}</span>
                    <ThemeToggle />
                    <button
                        onClick={() => router.post('/logout')}
                        className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 text-sm font-semibold text-ink-2 hover:bg-canvas"
                    >
                        <LogOut size={16} aria-hidden /> Log out
                    </button>
                </div>
            </header>

            <CommandPalette open={palette} onClose={() => setPalette(false)} />

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
                        {billing && (
                            <div role="alert" className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/60 dark:text-red-200">
                                <span>
                                    <strong>Your last payment didn't go through.</strong> Please update your payment details by {billing.graceEnds} to keep access. Your records are safe.
                                </span>
                                <Link href="/app/settings" className="font-semibold underline">
                                    Manage billing
                                </Link>
                            </div>
                        )}
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
