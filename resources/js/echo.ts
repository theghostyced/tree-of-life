import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

type EchoInstance = Echo<'reverb'>;

/**
 * Vite substitutes these at build time, so an environment missing them ships a
 * bundle with `undefined` baked in rather than failing the build.
 */
function reverbConfig() {
    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
    const forceTLS = scheme === 'https';
    const port = Number(
        import.meta.env.VITE_REVERB_PORT ?? (forceTLS ? 443 : 8080),
    );

    return {
        key: import.meta.env.VITE_REVERB_APP_KEY,
        host: import.meta.env.VITE_REVERB_HOST,
        port,
        forceTLS,
    };
}

function createEcho(): EchoInstance | null {
    const { key, host, port, forceTLS } = reverbConfig();

    // Pusher throws when the key is absent. Realtime is an enhancement, so a
    // misconfigured cluster must not take the application down with it.
    if (!key || !host) {
        console.warn(
            'Realtime disabled: VITE_REVERB_APP_KEY and VITE_REVERB_HOST were not set when the assets were built.',
        );

        return null;
    }

    window.Pusher = Pusher;

    try {
        return new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS,
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.warn('Realtime disabled: Echo failed to initialise.', error);

        return null;
    }
}

let instance: EchoInstance | null | undefined;

/**
 * Built on first use rather than at import time. app.ts imports this module
 * before mounting Inertia, so constructing eagerly would let a realtime
 * failure abort the whole application bootstrap.
 */
export function echo(): EchoInstance | null {
    if (instance === undefined) {
        instance = typeof window === 'undefined' ? null : createEcho();
    }

    return instance;
}
