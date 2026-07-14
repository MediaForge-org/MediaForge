import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initPreset } from '@/lib/preset';
import { initTheme } from '@/lib/theme';

const appName = import.meta.env.VITE_APP_NAME || 'MediaForge';

// Apply the persisted appearance + design preset before the first paint.
initTheme();
initPreset();

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#d97706', // amber accent
    },
});
