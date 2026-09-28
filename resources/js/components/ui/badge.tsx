import { cn } from '@/lib/cn';
import type { ReactNode } from 'react';

export type Tone = 'red' | 'amber' | 'green' | 'grey' | 'blue';

const tones: Record<Tone, string> = {
    red: 'bg-red-50 text-red-700 border-red-200',
    amber: 'bg-amber-50 text-amber-700 border-amber-200',
    green: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    grey: 'bg-canvas text-ink-2 border-line',
    blue: 'bg-indigo-50 text-indigo-700 border-indigo-200',
};

export function Badge({ tone = 'grey', children, className }: { tone?: Tone; children: ReactNode; className?: string }) {
    return <span className={cn('inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium', tones[tone], className)}>{children}</span>;
}
