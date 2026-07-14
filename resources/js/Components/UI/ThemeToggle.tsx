import { useEffect, useState } from 'react';

import { MoonIcon, SunIcon } from '@/Components/UI/Icon';
import { setTheme } from '@/lib/theme';

/** Compact light/dark switch. Persists the choice via lib/theme. */
export default function ThemeToggle({ className = '' }: { className?: string }) {
    const [isDark, setIsDark] = useState(true);

    useEffect(() => {
        setIsDark(document.documentElement.getAttribute('data-theme') === 'dark');
    }, []);

    function toggle() {
        const next = isDark ? 'light' : 'dark';
        setTheme(next);
        setIsDark(!isDark);
    }

    return (
        <button
            aria-label={isDark ? 'Switch to light theme' : 'Switch to dark theme'}
            className={`mf-button mf-button-secondary grid size-9 place-items-center rounded-full !p-0 ${className}`}
            onClick={toggle}
            title={isDark ? 'Light theme' : 'Dark theme'}
            type="button"
        >
            {isDark ? <SunIcon className="size-[1.05rem]" /> : <MoonIcon className="size-[1.05rem]" />}
        </button>
    );
}
