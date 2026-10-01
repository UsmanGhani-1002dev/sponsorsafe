import { cn } from '@/lib/cn';

export const WEEK = [
    ['mon', 'Mon'],
    ['tue', 'Tue'],
    ['wed', 'Wed'],
    ['thu', 'Thu'],
    ['fri', 'Fri'],
    ['sat', 'Sat'],
    ['sun', 'Sun'],
] as const;

export const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

/** Mon–Sun toggle buttons for someone's usual working days (used by the clock-in check). */
export function WorkDaysPicker({ value, onChange, error, legend = 'Usual working days' }: { value: string[]; onChange: (days: string[]) => void; error?: string; legend?: string }) {
    const toggle = (day: string) => onChange(value.includes(day) ? value.filter((d) => d !== day) : WEEK.map(([d]) => d).filter((d) => d === day || value.includes(d)));

    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-1.5 text-sm font-medium text-ink-2">{legend}</legend>
            <div className="flex flex-wrap gap-2">
                {WEEK.map(([day, label]) => {
                    const on = value.includes(day);
                    return (
                        <button
                            key={day}
                            type="button"
                            role="checkbox"
                            aria-checked={on}
                            onClick={() => toggle(day)}
                            className={cn(
                                'min-h-11 min-w-12 rounded-lg px-3 text-sm font-semibold',
                                on ? 'border-2 border-accent bg-accent-soft text-accent-strong' : 'border border-line-strong bg-surface text-ink-2 hover:bg-canvas',
                            )}
                        >
                            {label}
                        </button>
                    );
                })}
            </div>
            {error && <p className="text-sm text-red-700 dark:text-red-300">{error}</p>}
        </fieldset>
    );
}
