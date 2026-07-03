import { createInertiaApp } from '@inertiajs/svelte';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: 'var(--color-accent)',
        delay: 100,
        showSpinner: false,
    },
});
