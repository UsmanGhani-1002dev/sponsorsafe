import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/cn';
import { useRef, type KeyboardEvent } from 'react';

export interface Tab {
    id: string;
    label: string;
    soon?: boolean;
}

/** Underlined tabs (prototype style). Arrow keys move between tabs; unbuilt tabs show "Soon". */
export function Tabs({ tabs, active, onChange, label }: { tabs: Tab[]; active: string; onChange: (id: string) => void; label: string }) {
    const refs = useRef<(HTMLButtonElement | null)[]>([]);
    const ready = tabs.filter((t) => !t.soon);

    const onKey = (e: KeyboardEvent) => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        const i = ready.findIndex((t) => t.id === active);
        const next = ready[(i + (e.key === 'ArrowRight' ? 1 : ready.length - 1)) % ready.length];
        onChange(next.id);
        refs.current[tabs.indexOf(next)]?.focus();
    };

    return (
        <div role="tablist" aria-label={label} onKeyDown={onKey} className="mb-6 flex gap-1 overflow-x-auto border-b border-line">
            {tabs.map((t, i) =>
                t.soon ? (
                    <span key={t.id} aria-disabled className="flex min-h-11 shrink-0 items-center gap-2 px-3 text-[15px] font-medium text-muted">
                        {t.label} <Badge tone="grey">Soon</Badge>
                    </span>
                ) : (
                    <button
                        key={t.id}
                        ref={(el) => {
                            refs.current[i] = el;
                        }}
                        type="button"
                        role="tab"
                        id={`tab-${t.id}`}
                        aria-selected={active === t.id}
                        aria-controls={`panel-${t.id}`}
                        tabIndex={active === t.id ? 0 : -1}
                        onClick={() => onChange(t.id)}
                        className={cn(
                            '-mb-px min-h-11 shrink-0 border-b-[3px] px-3 text-[15px]',
                            active === t.id ? 'border-accent font-semibold text-accent' : 'border-transparent font-medium text-ink-2 hover:text-ink',
                        )}
                    >
                        {t.label}
                    </button>
                ),
            )}
        </div>
    );
}
