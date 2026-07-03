import { spawn } from 'node:child_process';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, type PluginOption } from 'vite';

const isSvelteCheck = process.argv.some((argument) =>
    argument.includes('svelte-check'),
);

if (isSvelteCheck) {
    process.env.LARAVEL_BYPASS_ENV_CHECK ??= '1';
}

/**
 * Regenerates `resources/js/types/generated.d.ts` from the PHP enums (and any
 * `#[TypeScript]` classes) via spatie/laravel-typescript-transformer — once when
 * the dev server or a build starts, then again whenever a file under `app/Enums`
 * changes during dev. Keeps the frontend enum types in sync with `app/Enums`
 * without a manual `php artisan typescript:transform`.
 */
function laravelTypescriptTransform(): PluginOption {
    const php = process.env.PHP_BINARY || 'php';
    const run = () =>
        new Promise<void>((resolve) => {
            const child = spawn(php, ['artisan', 'typescript:transform'], {
                stdio: 'ignore',
            });
            child.on('error', () => resolve());
            child.on('close', () => resolve());
        });

    return {
        name: 'laravel-typescript-transform',
        async buildStart() {
            await run();
        },
        configureServer(server) {
            server.watcher.add('app/Enums/**/*.php');
            const onChange = (file: string) => {
                if (file.replaceAll('\\', '/').includes('/app/Enums/')) {
                    void run().then(() =>
                        server.ws.send({ type: 'full-reload' }),
                    );
                }
            };
            server.watcher.on('change', onChange);
            server.watcher.on('add', onChange);
            server.watcher.on('unlink', onChange);
        },
    };
}

export default defineConfig({
    plugins: [
        !isSvelteCheck && laravelTypescriptTransform(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        svelte(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
