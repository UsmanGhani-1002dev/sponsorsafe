import { cn } from '@/lib/cn';
import type { ButtonHTMLAttributes } from 'react';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

const styles: Record<Variant, string> = {
    primary: 'bg-accent text-white hover:bg-accent-strong',
    secondary: 'bg-white text-ink-2 border border-line-strong hover:bg-canvas',
    ghost: 'bg-transparent text-accent hover:bg-accent-soft',
    danger: 'bg-white text-red-700 border border-red-200 hover:bg-red-50',
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
