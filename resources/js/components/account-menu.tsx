import { cn } from '@/lib/cn';
import type { AuthUser } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, type LucideIcon } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

export interface AccountLink {
    label: string;
    href: string;
    icon: LucideIcon;
}

/**
 * Header account menu (admin area and employee portal): initials + name; opens who is signed in, a few account links
 * and Log out. Light/dark stays its own button in the header.
 */
export function AccountMenu({ user, links }: { user: AuthUser; links: AccountLink[] }) {
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);
    const button = useRef<HTMLButtonElement>(null);
    const menuId = useId();
    const url = usePage().url;

    useEffect(() => setOpen(false), [url]);
    useEffect(() => {
        if (!open) return;
        const key = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setOpen(false);
                button.current?.focus();
            }
        };
        const click = (e: MouseEvent) => box.current && !box.current.contains(e.target as Node) && setOpen(false);
        document.addEventListener('keydown', key);
        document.addEventListener('mousedown', click);
        return () => {
            document.removeEventListener('keydown', key);
            document.removeEventListener('mousedown', click);
        };
    }, [open]);

    const item = 'flex min-h-11 w-full items-center gap-2.5 px-4 text-sm font-medium text-ink-2 hover:bg-canvas hover:text-ink';

    return (
        <div ref={box} className="relative">
            <button
                ref={button}
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-controls={menuId}
                aria-label={`Account: ${user.name}`}
                className="inline-flex min-h-10 items-center gap-2 rounded-full border border-transparent py-1 pr-2 pl-1 text-sm font-semibold text-ink-2 hover:border-line hover:bg-canvas"
            >
                <span aria-hidden className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent-strong">
                    {user.initials}
                </span>
                <span className="hidden max-w-40 truncate lg:inline">{user.name}</span>
                <ChevronDown size={16} aria-hidden className={cn('text-muted transition-transform', open && 'rotate-180')} />
            </button>

            {open && (
                <div id={menuId} className="absolute top-full right-0 z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-line bg-surface shadow-lg">
                    <div className="flex items-center gap-3 border-b border-line px-4 py-3">
                        <span aria-hidden className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-strong">
                            {user.initials}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-semibold">{user.name}</p>
                            <p className="truncate text-[13px] text-muted">{user.email}</p>
                            {user.business && (
                                <p className="truncate text-[13px] text-muted">
                                    {user.business} · {user.role === 'admin' ? 'Admin' : 'Employee'}
                                </p>
                            )}
                        </div>
                    </div>
                    {links.length > 0 && (
                        <ul className="py-1">
                            {links.map((l) => (
                                <li key={l.href}>
                                    <Link href={l.href} className={item} onClick={() => setOpen(false)}>
                                        <l.icon size={17} aria-hidden className="text-muted" /> {l.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="border-t border-line py-1">
                        <button type="button" onClick={() => router.post('/logout')} className={item}>
                            <LogOut size={17} aria-hidden className="text-muted" /> Log out
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
