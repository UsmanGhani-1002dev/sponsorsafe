import { cn } from '@/lib/cn';
import type { Setup } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronDown, ListChecks } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * "Get started · 2 of 5": a small pill at the bottom left of every admin page except the dashboard (which shows the
 * full card) until set-up is done. Opens the steps; each open step links to where it is done. Bottom left, so it
 * never covers the toasts at the bottom right.
 */
export function SetupPill({ setup }: { setup: Setup }) {
    const [open, setOpen] = useState(false);
    const url = usePage().url;
    const box = useRef<HTMLDivElement>(null);
    const next = setup.steps.find((s) => !s.done);

    // Close when a step's link is followed, on Escape, and on a click elsewhere.
    useEffect(() => setOpen(false), [url]);
    useEffect(() => {
        if (!open) return;
        const key = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        const click = (e: MouseEvent) => box.current && !box.current.contains(e.target as Node) && setOpen(false);
        document.addEventListener('keydown', key);
        document.addEventListener('mousedown', click);
        return () => {
            document.removeEventListener('keydown', key);
            document.removeEventListener('mousedown', click);
        };
    }, [open]);

    return (
        <div ref={box} className="fixed bottom-4 left-4 z-40 flex max-w-[calc(100vw-2rem)] flex-col items-start gap-2 print:hidden">
            {open && (
                <div id="setup-pill-panel" role="region" aria-labelledby="setup-pill-title" className="w-80 max-w-full overflow-hidden rounded-xl border border-line bg-surface shadow-lg">
                    <div className="border-b border-line px-4 py-3">
                        <h2 id="setup-pill-title" className="font-semibold">
                            Get started
                        </h2>
                        <p className="text-[13px] text-muted">Set these up so SponsorSafe can keep your records and tell you what to report.</p>
                        <div className="mt-2 h-1.5 rounded bg-subtle" role="progressbar" aria-valuenow={setup.done} aria-valuemin={0} aria-valuemax={setup.total} aria-label="Set-up steps done">
                            <div className="h-1.5 rounded bg-accent-fill" style={{ width: `${Math.round((setup.done / setup.total) * 100)}%` }} />
                        </div>
                    </div>
                    <ol>
                        {setup.steps.map((s, i) => {
                            const marker = (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        s.done ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300' : s === next ? 'bg-accent-fill text-white' : 'border border-line-strong text-ink-2',
                                    )}
                                >
                                    {s.done ? <Check size={13} /> : i + 1}
                                </span>
                            );
                            return (
                                <li key={s.key} className="border-t border-line first:border-t-0">
                                    {s.done ? (
                                        <span className="flex min-h-11 items-center gap-3 px-4 py-2 text-sm text-muted line-through decoration-1">
                                            {marker}
                                            {s.title}
                                            <span className="sr-only"> (done)</span>
                                        </span>
                                    ) : (
                                        <Link href={s.href} className={cn('flex min-h-11 items-center gap-3 px-4 py-2 text-sm hover:bg-canvas', s === next && 'font-semibold')}>
                                            {marker}
                                            <span className="min-w-0 flex-1">{s.title}</span>
                                            <span className="shrink-0 text-xs text-accent">{s.action}</span>
                                        </Link>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                    <Link href="/app" className="block border-t border-line px-4 py-2.5 text-[13px] font-semibold text-accent hover:bg-canvas">
                        See the steps on the dashboard
                    </Link>
                </div>
            )}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-controls="setup-pill-panel"
                className="inline-flex min-h-11 items-center gap-2 rounded-full border border-line bg-surface py-1.5 pr-3 pl-2 text-sm font-semibold text-ink shadow-lg hover:border-line-strong"
            >
                <span aria-hidden className="inline-flex size-8 items-center justify-center rounded-full bg-accent-fill text-white">
                    <ListChecks size={17} />
                </span>
                Get started
                <span className="font-normal text-muted">
                    {setup.done} of {setup.total}
                </span>
                <ChevronDown size={16} aria-hidden className={cn('text-muted transition-transform', !open && 'rotate-180')} />
            </button>
        </div>
    );
}
