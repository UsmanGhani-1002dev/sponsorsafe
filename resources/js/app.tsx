import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title ? `${title} · SponsorSafe` : 'SponsorSafe'),
    // Each page is its own chunk, so employees never download the admin screens (and vice versa).
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx');
        return pages[`./pages/${name}.tsx`]() as never;
    },
    setup({ el, App, props }) {
        createRoot(el!).render(<App {...props} />);
    },
    progress: { color: '#4F46E5', delay: 150 },
});
