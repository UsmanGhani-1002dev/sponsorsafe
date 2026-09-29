import { cn } from '@/lib/cn';
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

export function Field({ label, error, hint, id, children, className }: { label: ReactNode; error?: string; hint?: ReactNode; id: string; children: ReactNode; className?: string }) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <label htmlFor={id} className="text-sm font-medium text-ink-2">
                {label}
            </label>
            {children}
            {hint && !error && (
                <p id={`${id}-hint`} className="text-[13px] text-muted">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${id}-error`} className="text-[13px] text-red-700 dark:text-red-300">
                    {error}
                </p>
            )}
        </div>
    );
}

const control = 'min-h-11 w-full rounded-lg border bg-surface px-3 text-[15px] text-ink shadow-[0_1px_2px_rgba(16,24,40,0.05)] outline-none transition placeholder:text-muted focus:border-indigo-300 focus:ring-4 focus:ring-accent-ring';
const state = (invalid?: boolean) => (invalid ? 'border-red-300 dark:border-red-800' : 'border-line-strong');

export function Input({ className, invalid, ...props }: InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }) {
    return <input aria-invalid={invalid || undefined} aria-describedby={invalid && props.id ? `${props.id}-error` : undefined} className={cn(control, state(invalid), className)} {...props} />;
}

export function Select({ className, invalid, children, ...props }: SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
    return (
        <select aria-invalid={invalid || undefined} aria-describedby={invalid && props.id ? `${props.id}-error` : undefined} className={cn(control, 'pr-8', state(invalid), className)} {...props}>
            {children}
        </select>
    );
}

export function Textarea({ className, invalid, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement> & { invalid?: boolean }) {
    return <textarea aria-invalid={invalid || undefined} aria-describedby={invalid && props.id ? `${props.id}-error` : undefined} className={cn(control, 'py-2.5', state(invalid), className)} {...props} />;
}
