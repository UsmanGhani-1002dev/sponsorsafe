import { cn } from '@/lib/cn';
import type { HTMLAttributes } from 'react';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('rounded-xl border border-line bg-white shadow-[0_1px_2px_rgba(16,24,40,0.05)]', className)} {...props} />;
}
