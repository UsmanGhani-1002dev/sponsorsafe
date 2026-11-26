import { cn } from '@/lib/cn';
import type { Setup } from '@/types';
import { Link } from '@inertiajs/react';
import { Check, ChevronDown, ListChecks } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';

const KEY = 'sponsorsafe.setupPill';

/** Open by default on a laptop or desktop; once minimised it stays minimised for the rest of the browser session. */
function initiallyOpen(): boolean {
    try {
        if (sessionStorage.getItem(KEY) === 'closed') return false;
    } catch {
        // storage blocked: just open it
    }
    return window.matchMedia('(min-width: 640px)').matches;
}

function remember(open: boolean) {
    try {
        sessionStorage.setItem(KEY, open ? 'open' : 'closed');
    } catch {
        // storage blocked: fine, it simply opens again on the next page
    }
}

/**
 * "Get started · 2 of 5": bottom right of every admin page except the dashboard (which shows the full card) until
 * set-up is done. Open by default; each open step links to where it is done. Toasts stack above it (--toast-offset).
 */
export function SetupPill({ setup }: { setup: Setup }) {
    const [open, setOpen] = useState(initiallyOpen);
    const box = useRef<HTMLDivElement>(null);
    const next = setup.steps.find((s) => !s.done);

    const toggle = () =>
        setOpen((v) => {
            remember(!v);
            return !v;
        });

    // Keep toasts above the pill: share its height with the toaster.
    useLayoutEffect(() => {
        const el = box.current;
        if (!el) return;
        const root = document.documentElement;
        const set = () => root.style.setProperty('--toast-offset', `${el.offsetHeight + 12}px`);
        set();
        const ro = new ResizeObserver(set);
        ro.observe(el);
        return () => {
            ro.disconnect();
            root.style.removeProperty('--toast-offset');
        };
    }, []);

    return (
        <div ref={box} className="fixed right-4 bottom-4 z-40 flex max-w-[calc(100vw-2rem)] flex-col items-end gap-2 sm:right-6 sm:bottom-6 print:hidden">
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
                onClick={toggle}
                aria-expanded={open}
                aria-controls="setup-pill-panel"
                aria-label={open ? `Minimise Get started (${setup.done} of ${setup.total} done)` : `Open Get started (${setup.done} of ${setup.total} done)`}
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
