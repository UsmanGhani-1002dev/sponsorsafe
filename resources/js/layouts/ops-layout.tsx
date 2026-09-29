import { Flash } from '@/components/flash';
import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function OpsLayout({ title, base, children }: { title: string; base: string; children: ReactNode }) {
    return (
        <div className="min-h-screen">
            <Head title={title} />
            <header className="flex h-16 items-center justify-between bg-[#101828] px-6 text-white">
                <span className="inline-flex items-center gap-3">
                    <span aria-hidden className="inline-flex size-7 items-center justify-center rounded-lg bg-accent-fill text-sm font-bold">S</span>
                    <span className="font-semibold">Super admin</span>
                    <span className="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/70">2FA verified</span>
                </span>
                <button onClick={() => router.post(`${base}/logout`)} className="min-h-10 rounded-lg border border-white/20 px-3 text-sm font-semibold hover:bg-white/10">
                    Log out
                </button>
            </header>
            <main className="mx-auto max-w-6xl px-6 py-8">
                <Flash />
                {children}
            </main>
        </div>
    );
}
