import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';

/** Page title, optional one-line description, optional "back" link and actions on the right. */
export function PageHeader({ title, description, actions, back }: { title: ReactNode; description?: ReactNode; actions?: ReactNode; back?: { href: string; label: string } }) {
    return (
        <div className="mb-6 flex flex-col gap-3">
            {back && (
                <Link href={back.href} prefetch className="inline-flex min-h-10 w-fit items-center gap-1.5 rounded-md text-[15px] font-semibold text-accent hover:underline">
                    <ArrowLeft size={16} aria-hidden /> {back.label}
                </Link>
            )}
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="text-[26px] font-semibold">{title}</h1>
                    {description && <p className="mt-1 max-w-3xl text-[15px] text-ink-2">{description}</p>}
                </div>
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </div>
        </div>
    );
}
