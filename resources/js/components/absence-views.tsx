import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { CalendarDays, List } from 'lucide-react';

/** Absence: switch between the log (table) and the month calendar. Same underlined look as the profile tabs. */
export function AbsenceViews({ current }: { current: 'list' | 'calendar' }) {
    const tab = (key: 'list' | 'calendar', href: string, label: string, Icon: typeof List) => (
        <Link
            href={href}
            prefetch
            aria-current={current === key ? 'page' : undefined}
            className={cn(
                '-mb-px inline-flex min-h-11 shrink-0 items-center gap-2 border-b-[3px] px-3 text-[15px]',
                current === key ? 'border-accent font-semibold text-accent' : 'border-transparent font-medium text-ink-2 hover:text-ink',
            )}
        >
            <Icon size={17} aria-hidden /> {label}
        </Link>
    );

    return (
        <nav aria-label="Absence views" className="mb-6 flex gap-1 border-b border-line">
            {tab('list', '/app/absence', 'List', List)}
            {tab('calendar', '/app/absence/calendar', 'Calendar', CalendarDays)}
        </nav>
    );
}
