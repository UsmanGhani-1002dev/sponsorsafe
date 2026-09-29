import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({ icon: Icon, title, children, action }: { icon: LucideIcon; title: string; children?: ReactNode; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
            <span aria-hidden className="mb-1 inline-flex size-11 items-center justify-center rounded-full bg-accent-soft text-accent">
                <Icon size={20} />
            </span>
            <p className="text-[15px] font-semibold">{title}</p>
            {children && <div className="max-w-md text-sm text-ink-2">{children}</div>}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
