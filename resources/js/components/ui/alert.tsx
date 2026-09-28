import { cn } from '@/lib/cn';
import type { ReactNode } from 'react';

export function Alert({ tone = 'error', children }: { tone?: 'error' | 'info' | 'success'; children: ReactNode }) {
    const t = {
        error: 'bg-red-50 border-red-200 text-red-700',
        info: 'bg-indigo-50 border-indigo-200 text-indigo-700',
        success: 'bg-emerald-50 border-emerald-200 text-emerald-700',
    }[tone];
    return (
        <div role={tone === 'error' ? 'alert' : 'status'} className={cn('rounded-lg border px-3 py-2.5 text-sm', t)}>
            {children}
        </div>
    );
}
