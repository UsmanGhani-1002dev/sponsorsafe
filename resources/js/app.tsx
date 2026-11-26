import '../css/app.css';
import { listenForFlash, Toaster } from '@/components/toaster';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} · SponsorSafe` : 'SponsorSafe'),
    // Each page is its own chunk, so employees never download the admin screens (and vice versa).
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx');
        return pages[`./pages/${name}.tsx`]() as never;
    },
    setup({ el, App, props }) {
        listenForFlash(props.initialPage);
        // Links prefetch on hover and Inertia keeps those pages for 30 seconds. After any save (a non-GET visit)
        // throw them away, so e.g. the dashboard never shows "Still needed: Authorising Officer" just after adding one.
        router.on('finish', (event) => {
            if (event.detail.visit.method !== 'get') router.flushAll();
        });
        createRoot(el!).render(
            <>
                <App {...props} />
                <Toaster />
            </>,
        );
    },
    progress: { color: '#4F46E5', delay: 150 },
});
