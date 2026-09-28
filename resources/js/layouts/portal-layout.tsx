import { Flash } from '@/components/flash';
import { Logo } from '@/components/logo';
import type { SharedProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function PortalLayout({ title, children }: { title: string; children: ReactNode }) {
    const { auth } = usePage<SharedProps>().props;
    return (
        <div className="min-h-screen">
            <Head title={title} />
            <header className="flex h-16 items-center justify-between border-b border-line bg-white px-4 sm:px-6">
                <Logo />
                <div className="flex items-center gap-3">
                    <span className="hidden text-sm text-ink-2 sm:inline">{auth.user?.name}</span>
                    <button onClick={() => router.post('/logout')} className="min-h-10 rounded-lg border border-line-strong bg-white px-3 text-sm font-semibold text-ink-2 hover:bg-canvas">
                        Log out
                    </button>
                </div>
            </header>
            <main className="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-10">
                <Flash />
                {children}
            </main>
        </div>
    );
}
