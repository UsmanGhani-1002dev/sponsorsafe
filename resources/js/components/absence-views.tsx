import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { CalendarDays, List } from 'lucide-react';

/** Absence: switch between the log (table) and the month calendar. */
export function AbsenceViews({ current }: { current: 'list' | 'calendar' }) {
    const tab = (key: 'list' | 'calendar', href: string, label: string, Icon: typeof List) => (
        <Link
            href={href}
            prefetch
            aria-current={current === key ? 'page' : undefined}
            className={cn(
                'inline-flex min-h-10 items-center gap-2 rounded-md px-3.5 text-sm font-semibold',
                current === key ? 'bg-surface text-ink shadow-[0_1px_2px_rgba(16,24,40,0.08)]' : 'text-muted hover:text-ink',
            )}
        >
            <Icon size={16} aria-hidden /> {label}
        </Link>
    );

    return (
        <nav aria-label="Absence views" className="mb-5 inline-flex gap-1 rounded-lg border border-line bg-canvas p-1">
            {tab('list', '/app/absence', 'List', List)}
            {tab('calendar', '/app/absence/calendar', 'Calendar', CalendarDays)}
        </nav>
    );
}
