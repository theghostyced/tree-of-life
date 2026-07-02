import { createInertiaApp } from '@inertiajs/svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        // Resolves against the --color-accent token defined in app.css.
        color: 'var(--color-accent)',
    },
});
