import { cn } from '@/lib/cn';
import type { InputHTMLAttributes, ReactNode } from 'react';

export function Field({ label, error, hint, id, children }: { label: ReactNode; error?: string; hint?: ReactNode; id: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="text-sm font-medium text-ink-2">
                {label}
            </label>
            {children}
            {hint && !error && <p className="text-[13px] text-muted">{hint}</p>}
            {error && (
                <p id={`${id}-error`} className="text-[13px] text-red-700">
                    {error}
                </p>
            )}
        </div>
    );
}

export function Input({ className, invalid, ...props }: InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }) {
    return (
        <input
            aria-invalid={invalid || undefined}
            className={cn(
                'min-h-11 w-full rounded-lg border bg-white px-3 text-[15px] text-ink shadow-[0_1px_2px_rgba(16,24,40,0.05)] outline-none transition placeholder:text-muted focus:border-indigo-300 focus:ring-4 focus:ring-accent-ring',
                invalid ? 'border-red-300' : 'border-line-strong',
                className,
            )}
            {...props}
        />
    );
}
