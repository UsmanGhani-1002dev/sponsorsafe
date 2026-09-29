import { cn } from '@/lib/cn';
import type { ReactNode } from 'react';

export function Alert({ tone = 'error', children }: { tone?: 'error' | 'info' | 'success' | 'warning'; children: ReactNode }) {
    const t = {
        error: 'bg-red-50 border-red-200 text-red-700 dark:bg-red-950/60 dark:border-red-900 dark:text-red-300',
        info: 'bg-indigo-50 border-indigo-200 text-indigo-700 dark:bg-indigo-950/60 dark:border-indigo-900 dark:text-indigo-300',
        success: 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-950/60 dark:border-emerald-900 dark:text-emerald-300',
        warning: 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-950/60 dark:border-amber-900 dark:text-amber-300',
    }[tone];
    return (
        <div role={tone === 'error' ? 'alert' : 'status'} className={cn('rounded-lg border px-3 py-2.5 text-sm', t)}>
            {children}
        </div>
    );
}
