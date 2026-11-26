import { AbsenceViews } from '@/components/absence-views';
import { Badge, type Tone } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { PageHeader } from '@/components/ui/page-header';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/cn';
import { Link } from '@inertiajs/react';
import { CalendarX2, ChevronLeft, ChevronRight, Plus } from 'lucide-react';

type Kind = 'leave' | 'sick' | 'unpaid' | 'unauthorised';

type Cell = { state: 'out' } | { state: 'work' } | { state: 'off' } | { state: 'absence'; kind: Kind; scheduled: boolean; title: string };

interface Props {
    month: { value: string; label: string; prev: string; next: string; isCurrent: boolean };
    days: { date: string; day: number; weekday: string; weekend: boolean; bankHoliday: string | null; today: boolean; label: string }[];
    people: { id: number; name: string; sponsored: boolean; current: boolean; unpaid: { text: string; tone: Tone } | null; counts: Partial<Record<Kind, number>>; cells: Cell[] }[];
    kinds: Record<Kind, string>;
}

const fill: Record<Kind, string> = {
    leave: 'bg-blue-500',
    sick: 'bg-amber-400',
    unpaid: 'bg-slate-400 dark:bg-slate-500',
    unauthorised: 'bg-red-500',
};

// Not employed on that day (before they started, after they left).
const hatch = { backgroundImage: 'repeating-linear-gradient(135deg, var(--color-line-strong) 0 1px, transparent 1px 6px)' };

const navLink = 'inline-flex min-h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-lg border border-line-strong bg-surface text-sm font-semibold text-ink-2 hover:bg-canvas';

/** Absence → Calendar: who is off each day of the month. The absence log stays the compliance record. */
export default function AbsenceCalendar({ month, days, people, kinds }: Props) {
    const summary = (counts: Props['people'][number]['counts']) =>
        (Object.keys(kinds) as Kind[])
            .filter((k) => counts[k])
            .map((k) => `${kinds[k].toLowerCase()} ${counts[k]} ${counts[k] === 1 ? 'day' : 'days'}`)
            .join(', ');

    return (
        <AppLayout title="Absence calendar">
            <PageHeader
                title="Absence calendar"
                description="Who is off each day. Click an empty day to record an absence for that person."
                actions={
                    <Link href="/app/absence/create" prefetch className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-accent-fill px-4 text-[15px] font-semibold text-white hover:bg-accent-fill-hover">
                        <Plus size={18} aria-hidden /> Record absence
                    </Link>
                }
            />
            <AbsenceViews current="calendar" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Link href={`/app/absence/calendar?month=${month.prev}`} preserveScroll className={cn(navLink, 'w-11')} aria-label="Previous month">
                        <ChevronLeft size={18} aria-hidden />
                    </Link>
                    <h2 className="min-w-32 text-center text-lg font-semibold sm:min-w-40" aria-live="polite">
                        {month.label}
                    </h2>
                    <Link href={`/app/absence/calendar?month=${month.next}`} preserveScroll className={cn(navLink, 'w-11')} aria-label="Next month">
                        <ChevronRight size={18} aria-hidden />
                    </Link>
                    {!month.isCurrent && (
                        <Link href="/app/absence/calendar" preserveScroll className={cn(navLink, 'px-3.5')}>
                            This month
                        </Link>
                    )}
                </div>
                <ul className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-ink-2" aria-label="Key">
                    {(Object.keys(kinds) as Kind[]).map((k) => (
                        <li key={k} className="flex items-center gap-1.5">
                            <span aria-hidden className={cn('size-3 rounded-sm', fill[k])} /> {kinds[k]}
                        </li>
                    ))}
                    <li className="flex items-center gap-1.5">
                        <span aria-hidden className="size-3 rounded-sm border border-line bg-subtle" /> Day off
                    </li>
                </ul>
            </div>

            {people.length === 0 ? (
                <EmptyState icon={CalendarX2} title="Nobody employed this month">
                    Add your employees, and their absences show here month by month.
                </EmptyState>
            ) : (
                <Card className="relative overflow-x-auto">
                    {/* relative: the screen-reader labels inside scroll with the table instead of widening the page. */}
                    <table className="w-full table-fixed border-separate border-spacing-0 text-sm" style={{ minWidth: `${160 + days.length * 28}px` }}>
                        <caption className="sr-only">Absences in {month.label}, one row per employee</caption>
                        <thead>
                            <tr>
                                <th scope="col" className="sticky left-0 z-10 w-40 min-w-40 border-b border-line bg-surface px-4 py-2 text-left font-semibold text-muted">
                                    Employee
                                </th>
                                {days.map((d) => (
                                    <th
                                        key={d.date}
                                        scope="col"
                                        title={d.bankHoliday ? `${d.label}: ${d.bankHoliday}` : d.label}
                                        className={cn('w-7 min-w-7 border-b border-line px-0 py-2 text-center font-normal', (d.weekend || d.bankHoliday) && 'bg-canvas')}
                                    >
                                        <span className="sr-only">{d.label}{d.bankHoliday ? `, ${d.bankHoliday}` : ''}</span>
                                        <span aria-hidden className="block text-[11px] text-muted">
                                            {d.weekday.slice(0, 2)}
                                        </span>
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'mx-auto mt-0.5 flex size-6 items-center justify-center rounded-full font-mono text-[12px]',
                                                d.today ? 'bg-accent-fill font-semibold text-white' : 'text-ink-2',
                                            )}
                                        >
                                            {d.day}
                                        </span>
                                        {d.bankHoliday && (
                                            <span aria-hidden className="block text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                                                BH
                                            </span>
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {people.map((p) => (
                                <tr key={p.id} className="group">
                                    <th scope="row" className="sticky left-0 z-10 max-w-40 border-b border-line bg-surface px-4 py-2 text-left font-normal group-last:border-b-0">
                                        <Link href={`/app/employees/${p.id}?tab=absence`} prefetch className="block truncate font-semibold text-ink hover:text-accent hover:underline">
                                            {p.name}
                                        </Link>
                                        <span className="mt-0.5 flex flex-wrap items-center gap-1.5">
                                            {p.unpaid && (
                                                <Badge tone={p.unpaid.tone} className="text-[11px]">
                                                    {p.unpaid.text}
                                                </Badge>
                                            )}
                                            {!p.current && (
                                                <Badge tone="grey" className="text-[11px]">
                                                    Left
                                                </Badge>
                                            )}
                                        </span>
                                        <span className="sr-only">{summary(p.counts) ? `This month: ${summary(p.counts)}.` : 'No absences this month.'}</span>
                                    </th>
                                    {p.cells.map((c, i) => {
                                        const d = days[i];
                                        const shaded = d.weekend || !!d.bankHoliday;
                                        return (
                                            <td
                                                key={d.date}
                                                className={cn('h-12 border-b border-line px-px py-0.5 group-last:border-b-0', shaded && 'bg-canvas', c.state === 'off' && 'bg-subtle')}
                                                style={c.state === 'out' ? hatch : undefined}
                                            >
                                                {c.state === 'absence' && (
                                                    <Link href={`/app/employees/${p.id}?tab=absence`} title={c.title} className="block h-full">
                                                        <span aria-hidden className={cn('block h-full rounded', fill[c.kind], !c.scheduled && 'opacity-40')} />
                                                        <span className="sr-only">
                                                            {d.label}: {c.title}
                                                        </span>
                                                    </Link>
                                                )}
                                                {c.state === 'work' && p.current && (
                                                    <Link
                                                        href={`/app/absence/create?employee=${p.id}&date=${d.date}&from=calendar`}
                                                        tabIndex={-1}
                                                        title={`Record an absence for ${p.name} on ${d.label}`}
                                                        className="flex h-full items-center justify-center rounded text-transparent hover:bg-accent-soft hover:text-accent"
                                                    >
                                                        <Plus size={14} aria-hidden />
                                                        <span className="sr-only">Record an absence for {p.name} on {d.label}</span>
                                                    </Link>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            )}

            <p className="mt-3 text-[13px] text-muted">
                Grey cells are each person's usual days off, and paler colours fall on those days. Striped cells are before they started or after they left. "Unpaid N of M" counts
                unpaid and unauthorised days in the leave year for sponsored workers; going over the limit must be reported.
            </p>
        </AppLayout>
    );
}
