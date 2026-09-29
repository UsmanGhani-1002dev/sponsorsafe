import { Logo } from '@/components/logo';
import { Alert } from '@/components/ui/alert';
import type { SharedProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Centred card used by sign-in, authenticator code and set-password pages. */
export default function AuthLayout({ title, heading, subheading, footer, children }: { title: string; heading: string; subheading?: ReactNode; footer?: ReactNode; children: ReactNode }) {
    const { flash } = usePage<SharedProps>().props;
    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-6 px-4 py-12">
            <Head title={title} />
            <Logo size={34} />
            <main className="w-full max-w-[420px] rounded-2xl border border-line bg-surface p-6 shadow-[0_4px_8px_-2px_rgba(16,24,40,0.08)] sm:p-8">
                <div className="mb-6">
                    <h1 className="text-[22px] font-semibold">{heading}</h1>
                    {subheading && <p className="text-sm text-muted">{subheading}</p>}
                </div>
                {(flash.error || flash.success) && (
                    <div className="mb-4">
                        <Alert tone={flash.error ? 'error' : 'success'}>{flash.error ?? flash.success}</Alert>
                    </div>
                )}
                {children}
            </main>
            {footer && <p className="max-w-[420px] text-center text-[13px] text-muted">{footer}</p>}
        </div>
    );
}
