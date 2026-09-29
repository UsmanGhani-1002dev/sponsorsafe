import { Moon, Sun } from 'lucide-react';
import { useState } from 'react';

/** Light / dark switch. The choice is remembered on this device; until then the system setting applies. */
export function ThemeToggle() {
    const [dark, setDark] = useState(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

    const toggle = () => {
        const next = !dark;
        document.documentElement.classList.toggle('dark', next);
        try {
            localStorage.setItem('theme', next ? 'dark' : 'light');
        } catch {
            // Storage blocked: the theme still changes for this visit.
        }
        setDark(next);
    };

    return (
        <button
            type="button"
            onClick={toggle}
            aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
            title={dark ? 'Light mode' : 'Dark mode'}
            className="inline-flex size-10 items-center justify-center rounded-lg border border-line-strong bg-surface text-ink-2 hover:bg-canvas"
        >
            {dark ? <Sun size={18} aria-hidden /> : <Moon size={18} aria-hidden />}
        </button>
    );
}
