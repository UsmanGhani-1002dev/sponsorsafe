import { cn } from '@/lib/cn';
import { router } from '@inertiajs/react';
import { ClipboardCheck, CornerDownLeft, FileWarning, Inbox, LayoutDashboard, Plus, Search, Settings, Trash2, User, Users, type LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';

/**
 * Ctrl+K (⌘K on a Mac) in the admin area: type to jump to an employee, a screen or an action.
 * Employees come from /app/palette the first time it opens (then refreshed in the background).
 * A native <dialog>: focus stays inside, Escape closes it, focus returns to where it was.
 */
type Item = { id: string; group: 'Actions' | 'Screens' | 'Employees'; label: string; detail?: string; href: string; icon: LucideIcon; muted?: boolean };
type Person = { id: number; name: string; detail: string; leaver: boolean };

const actions: Item[] = [
    { id: 'a-employee', group: 'Actions', label: 'Add employee', href: '/app/employees/create', icon: Plus },
    { id: 'a-absence', group: 'Actions', label: 'Record absence', href: '/app/absence/create', icon: Plus },
    { id: 'a-report', group: 'Actions', label: 'Create Home Office report', href: '/app/reports?create=1', icon: Plus },
];

const screens: Item[] = [
    { id: 's-dashboard', group: 'Screens', label: 'Dashboard', href: '/app', icon: LayoutDashboard },
    { id: 's-employees', group: 'Screens', label: 'Employees', href: '/app/employees', icon: Users },
    { id: 's-absence', group: 'Screens', label: 'Absence', href: '/app/absence', icon: ClipboardCheck },
    { id: 's-reports', group: 'Screens', label: 'Home Office reports', href: '/app/reports', icon: FileWarning },
    { id: 's-requests', group: 'Screens', label: 'Requests', href: '/app/requests', icon: Inbox },
    { id: 's-settings', group: 'Screens', label: 'Settings', detail: 'Business, plan, key personnel, work sites, rules', href: '/app/settings', icon: Settings },
    { id: 's-retention', group: 'Screens', label: "Leavers' records due for deletion", href: '/app/retention', icon: Trash2 },
];

let peopleCache: Person[] | null = null;

export const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

/** All words typed must appear somewhere in the label or detail ("rah main" finds "Rahul Mehta · Main shop"). */
function matches(item: Item, words: string[]) {
    const text = `${item.label} ${item.detail ?? ''}`.toLowerCase();
    return words.every((w) => text.includes(w));
}

export function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
    const dialog = useRef<HTMLDialogElement>(null);
    const input = useRef<HTMLInputElement>(null);
    const list = useRef<HTMLUListElement>(null);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const [people, setPeople] = useState<Person[] | null>(peopleCache);

    useEffect(() => {
        const d = dialog.current;
        if (!d) return;
        if (open && !d.open) {
            setQuery('');
            setActive(0);
            d.showModal();
            input.current?.focus();
            fetch('/app/palette', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    if (data) {
                        peopleCache = data.employees;
                        setPeople(data.employees);
                    }
                })
                .catch(() => {});
        }
        if (!open && d.open) d.close();
    }, [open]);

    const items = useMemo(() => {
        const words = query.toLowerCase().split(/\s+/).filter(Boolean);
        const employees: Item[] = (people ?? []).map((p) => ({
            id: `e-${p.id}`, group: 'Employees', label: p.name, detail: p.detail, href: `/app/employees/${p.id}`, icon: User, muted: p.leaver,
        }));
        if (!words.length) return [...actions, ...screens, ...employees.filter((e) => !e.muted).slice(0, 5)];

        return [...employees.filter((e) => matches(e, words)).slice(0, 8), ...actions.filter((a) => matches(a, words)), ...screens.filter((s) => matches(s, words))];
    }, [query, people]);

    useEffect(() => setActive(0), [query]);
    useEffect(() => {
        list.current?.querySelector(`[data-index="${active}"]`)?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    const go = useCallback(
        (item: Item | undefined) => {
            if (!item) return;
            onClose();
            router.visit(item.href);
        },
        [onClose],
    );

    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => Math.min(i + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            go(items[active]);
        }
    };

    let lastGroup = '';

    return (
        <dialog
            ref={dialog}
            onClose={onClose}
            onClick={(e) => e.target === dialog.current && onClose()}
            aria-label="Search and jump"
            className="mx-auto mt-[12vh] w-[calc(100%-2rem)] max-w-xl rounded-2xl border border-line bg-surface p-0 text-ink shadow-2xl backdrop:bg-black/40"
        >
            <div className="flex items-center gap-3 border-b border-line px-4">
                <Search size={18} aria-hidden className="shrink-0 text-muted" />
                <input
                    ref={input}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder="Search employees, screens and actions…"
                    role="combobox"
                    aria-expanded="true"
                    aria-controls="palette-list"
                    aria-activedescendant={items[active] ? `palette-${items[active].id}` : undefined}
                    aria-label="Search employees, screens and actions"
                    className="min-h-14 flex-1 bg-transparent text-base outline-none placeholder:text-muted focus-visible:shadow-none"
                />
                <kbd className="hidden rounded border border-line px-1.5 py-0.5 text-xs text-muted sm:inline">Esc</kbd>
            </div>
            <ul ref={list} id="palette-list" role="listbox" aria-label="Results" className="max-h-[50vh] overflow-y-auto p-2">
                {items.length === 0 && <li className="px-3 py-8 text-center text-sm text-muted">{people === null ? 'Loading…' : `Nothing matches "${query}".`}</li>}
                {items.map((item, i) => {
                    const heading = item.group !== lastGroup ? item.group : null;
                    lastGroup = item.group;
                    return (
                        <li key={item.id} role="presentation">
                            {heading && <p className="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">{heading}</p>}
                            <div
                                id={`palette-${item.id}`}
                                data-index={i}
                                role="option"
                                aria-selected={i === active}
                                onMouseMove={() => setActive(i)}
                                onClick={() => go(item)}
                                className={cn('flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 py-2', i === active ? 'bg-accent-soft text-accent-strong' : 'text-ink')}
                            >
                                <item.icon size={18} aria-hidden className={cn('shrink-0', i === active ? 'text-accent' : 'text-muted')} />
                                <span className="min-w-0 flex-1">
                                    <span className={cn('block truncate text-[15px] font-medium', item.muted && 'text-muted')}>{item.label}</span>
                                    {item.detail && <span className="block truncate text-[13px] text-muted">{item.detail}</span>}
                                </span>
                                {i === active && <CornerDownLeft size={16} aria-hidden className="shrink-0 text-accent" />}
                            </div>
                        </li>
                    );
                })}
            </ul>
            <p className="flex flex-wrap gap-x-4 gap-y-1 border-t border-line px-4 py-2.5 text-xs text-muted">
                <span>↑ ↓ to move</span>
                <span>Enter to open</span>
                <span>{isMac ? '⌘' : 'Ctrl'} K to open from anywhere</span>
            </p>
        </dialog>
    );
}

/** Opens the palette with Ctrl+K / ⌘K anywhere in the admin area. */
export function usePaletteShortcut(setOpen: (fn: (open: boolean) => boolean) => void) {
    useEffect(() => {
        const onKey = (e: globalThis.KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((o) => !o);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [setOpen]);
}
