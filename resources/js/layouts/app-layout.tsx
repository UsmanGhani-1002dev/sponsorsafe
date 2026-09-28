import { Flash } from '@/components/flash';
import { Logo } from '@/components/logo';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/cn';
import type { SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ClipboardCheck, FileWarning, Inbox, LayoutDashboard, LogOut, Settings, Users } from 'lucide-react';
import type { ReactNode } from 'react';

// Items not built yet are shown so the structure is clear; they switch on stage by stage.
const nav = [
    { label: 'Dashboard', href: '/app', icon: LayoutDashboard, ready: true },
    { label: 'Employees', href: '/app/employees', icon: Users, ready: false },
    { label: 'Absence', href: '/app/absence', icon: ClipboardCheck, ready: false },
    { label: 'Home Office reports', href: '/app/reports', icon: FileWarning, ready: false },
    { label: 'Requests', href: '/app/requests', icon: Inbox, ready: false },
    { label: 'Settings', href: '/app/settings', icon: Settings, ready: false },
];

export default function AppLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth } = usePage<SharedProps>().props;
    const path = typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <div className="flex min-h-screen flex-col">
            <Head title={title} />
            <header className="flex h-16 shrink-0 items-center justify-between border-b border-line bg-white px-6">
                <div className="flex items-center gap-4">
                    <Logo />
                    <span className="hidden text-sm text-muted sm:inline">{auth.user?.business}</span>
                </div>
                <div className="flex items-center gap-3">
                    <span className="hidden text-sm text-ink-2 sm:inline">{auth.user?.name}</span>
                    <button
                        onClick={() => router.post('/logout')}
                        className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-line-strong bg-white px-3 text-sm font-semibold text-ink-2 hover:bg-canvas"
                    >
                        <LogOut size={16} aria-hidden /> Log out
                    </button>
                </div>
            </header>
            <div className="flex min-h-0 flex-1">
                <nav aria-label="Main" className="hidden w-60 shrink-0 flex-col gap-0.5 border-r border-line bg-white p-3 md:flex">
                    {nav.map((item) =>
                        item.ready ? (
                            <Link
                                key={item.href}
                                href={item.href}
                                prefetch
                                className={cn(
                                    'flex min-h-10 items-center gap-2.5 rounded-md px-3 text-sm font-medium',
                                    path === item.href ? 'bg-accent-soft font-semibold text-accent-strong' : 'text-ink-2 hover:bg-canvas',
                                )}
                            >
                                <item.icon size={18} aria-hidden /> {item.label}
                            </Link>
                        ) : (
                            <span key={item.href} aria-disabled className="flex min-h-10 cursor-default items-center justify-between gap-2.5 rounded-md px-3 text-sm font-medium text-muted">
                                <span className="flex items-center gap-2.5">
                                    <item.icon size={18} aria-hidden /> {item.label}
                                </span>
                                <Badge tone="grey">Soon</Badge>
                            </span>
                        ),
                    )}
                </nav>
                <main className="min-w-0 flex-1 px-5 py-8 sm:px-10">
                    <Flash />
                    {children}
                </main>
            </div>
        </div>
    );
}
