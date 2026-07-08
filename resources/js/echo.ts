import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

function createEcho() {
    window.Pusher = Pusher;

    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

// Echo touches `window`/WebSocket, which don't exist during SSR or Vite's
// module pre-evaluation. Construct it only in the browser; all chat usage runs
// client-side (onMount), so the SSR placeholder is never dereferenced.
export const echo =
    typeof window !== 'undefined'
        ? createEcho()
        : (undefined as unknown as ReturnType<typeof createEcho>);
