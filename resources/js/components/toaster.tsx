import { cn } from '@/lib/cn';
import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useSyncExternalStore } from 'react';

/**
 * Toasts: the server's flash messages ("Saved…", "Could not…") pop up in the corner instead of a banner.
 * Success fades after 5 seconds; errors stay until closed (they usually need reading). One listener
 * for every Inertia visit (see listenForFlash in app.tsx); sign-in pages keep their own inline messages.
 */
type Toast = { id: number; tone: 'success' | 'error'; text: string };

let toasts: Toast[] = [];
let nextId = 1;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((l) => l());

export function toast(tone: Toast['tone'], text: string) {
    // The same message twice in a row (e.g. a double click) shows once.
    if (toasts.some((t) => t.text === text && t.tone === tone)) return;
    const id = nextId++;
    toasts = [...toasts.slice(-2), { id, tone, text }];
    emit();
    if (tone === 'success') setTimeout(() => dismiss(id), 5000);
}

export function dismiss(id: number) {
    toasts = toasts.filter((t) => t.id !== id);
    emit();
}

type Flash = { success?: string | null; error?: string | null } | undefined;

function fromPage(page: Page) {
    if (page.component.startsWith('Auth/')) return;
    const flash = (page.props as { flash?: Flash }).flash;
    if (flash?.success) toast('success', flash.success);
    if (flash?.error) toast('error', flash.error);
}

/** Show the flash of the first page, then of every visit. Partial reloads do not include flash, so they never repeat a toast. */
export function listenForFlash(initialPage: Page) {
    fromPage(initialPage);
    router.on('success', (event) => fromPage(event.detail.page));
}

export function Toaster() {
    const items = useSyncExternalStore(
        (l) => {
            listeners.add(l);
            return () => listeners.delete(l);
        },
        () => toasts,
        () => toasts,
    );

    // Escape closes the newest toast.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape' && toasts.length && !document.querySelector('dialog[open]')) dismiss(toasts[toasts.length - 1].id);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <div aria-live="polite" className="pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6 sm:bottom-6">
            {items.map((t) => (
                <div
                    key={t.id}
                    role={t.tone === 'error' ? 'alert' : 'status'}
                    className={cn(
                        'toast-in pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-surface p-4 text-sm shadow-[0_12px_24px_-8px_rgba(16,24,40,0.25)]',
                        t.tone === 'error' ? 'border-red-200 dark:border-red-900' : 'border-line',
                    )}
                >
                    {t.tone === 'error' ? (
                        <XCircle size={20} aria-hidden className="mt-px shrink-0 text-red-600 dark:text-red-400" />
                    ) : (
                        <CheckCircle2 size={20} aria-hidden className="mt-px shrink-0 text-green-600 dark:text-green-400" />
                    )}
                    <p className="flex-1 leading-relaxed text-ink">{t.text}</p>
                    <button type="button" onClick={() => dismiss(t.id)} aria-label="Close message" className="-m-1.5 inline-flex size-8 shrink-0 items-center justify-center rounded-md text-muted hover:bg-canvas hover:text-ink">
                        <X size={16} aria-hidden />
                    </button>
                </div>
            ))}
        </div>
    );
}
