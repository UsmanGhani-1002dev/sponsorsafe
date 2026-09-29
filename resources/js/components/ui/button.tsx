import { cn } from '@/lib/cn';
import type { ButtonHTMLAttributes } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

const styles: Record<Variant, string> = {
    primary: 'bg-accent-fill text-white hover:bg-accent-fill-hover',
    secondary: 'bg-surface text-ink-2 border border-line-strong hover:bg-canvas',
    ghost: 'bg-transparent text-accent hover:bg-accent-soft',
    danger: 'bg-surface text-red-700 border border-red-200 hover:bg-red-50 dark:text-red-300 dark:border-red-900 dark:hover:bg-red-950/60',
};

export function Button({ variant = 'primary', className, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant }) {
    return (
        <button
            className={cn(
                'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-[15px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                styles[variant],
                className,
            )}
            {...props}
        />
    );
}
