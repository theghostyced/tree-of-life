<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import { ArrowLeft, House, RotateCw } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Logo from '@/components/Logo.svelte';
    import { cn } from '@/lib/utils';
    import type { Auth } from '@/types/auth';

    /**
     * Error page, rendered by the exception handler in bootstrap/app.php
     * for the statuses in the map below. Standalone on purpose: the viewer
     * may lack the role (403) or the destination (404) that any app chrome
     * would imply — so it shows none, just a way back to familiar ground.
     */
    let { status }: { status: number } = $props();

    type ErrorMeta = {
        title: string;
        chip: string;
        /** Status-dot tone. Color never carries the meaning alone; the chip label does. */
        dot: string;
        illustration: string;
        headline: string;
        body: string;
        /**
         * Overrides the default primary action (dashboard/homepage) where the
         * status implies a better next step than "go where you came from".
         */
        primary?: { href: string; label: string };
        /**
         * Secondary escape: 'back' navigates away, 'retry' reloads (for transient
         * failures), 'home' for arrivals from outside the app with no history.
         */
        secondary: 'back' | 'retry' | 'home';
    };

    const meta: Record<number, ErrorMeta> = {
        403: {
            title: 'Access restricted',
            chip: '403 · Restricted access',
            dot: 'bg-accent-orange',
            illustration: '/images/illustrations/security-spy.svg',
            headline: 'This area isn’t part of your role',
            body: 'You’re signed in, but this page belongs to a different role on TLF. If you think you should have access, ask your program admin to update your account.',
            secondary: 'back',
        },
        404: {
            title: 'Page not found',
            chip: '404 · Page not found',
            dot: 'bg-secondary-blue',
            illustration: '/images/illustrations/science-find.svg',
            headline: 'We couldn’t find that page',
            body: 'The link may be broken, or the page may have been moved or removed. Check the address, or head back to familiar ground.',
            secondary: 'back',
        },
        410: {
            title: 'Invitation link no longer active',
            chip: '410 · Invitation link inactive',
            dot: 'bg-accent-orange',
            illustration: '/images/illustrations/social-envelope.svg',
            headline: 'This invitation link is no longer active',
            // Deliberately generic: the visitor is unauthenticated and holds only
            // a token, so the copy must not reveal whether the invitation was
            // used, withdrawn, or simply timed out. The conditional phrasing lets
            // someone who already registered help themselves without the page
            // confirming that an account exists at the invited address.
            body: 'Invitation links are single-use and expire after a while. If you’ve already set up your account, sign in below. Otherwise, ask your program admin to send you a fresh invitation.',
            primary: { href: '/login', label: 'Sign in' },
            secondary: 'home',
        },
        500: {
            title: 'Something went wrong',
            chip: '500 · Server error',
            dot: 'bg-error',
            illustration: '/images/illustrations/weather-forecast.svg',
            headline: 'Something went wrong on our side',
            body: 'The server hit a problem while handling your request — it’s us, not you. Try again in a moment, and if it keeps happening, let your program admin know.',
            secondary: 'retry',
        },
    };

    const current = $derived(meta[status] ?? meta[404]);

    const auth = $derived(page.props.auth as Auth | undefined);

    const dashboards: Record<string, string> = {
        admin: '/admin/dashboard',
        entrepreneur: '/entrepreneur/dashboard',
        mentor: '/mentor/dashboard',
    };

    const homeHref = $derived(
        auth?.user ? (dashboards[auth.role] ?? '/') : '/',
    );
    const homeLabel = $derived(
        homeHref === '/' ? 'Go to the homepage' : 'Go to your dashboard',
    );

    const primaryHref = $derived(current.primary?.href ?? homeHref);
    const primaryLabel = $derived(current.primary?.label ?? homeLabel);

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
</script>

<AppHead title={current.title} />

<div class="flex min-h-screen flex-col bg-canvas font-sans text-ink">
    <header class="flex h-14 shrink-0 items-center px-4 sm:px-6">
        <Link
            href="/"
            aria-label="Tree Of Life Fund home"
            class={cn('shrink-0 rounded-md', focusRing)}
        >
            <Logo size="sm" />
        </Link>
    </header>

    <main
        class="animate-fade-in flex flex-1 flex-col items-center justify-center px-6 pb-20"
    >
        <div class="flex w-full max-w-md flex-col items-center text-center">
            <div class="relative mb-7">
                <!-- Soft accent halo grounds the illustration on the canvas. -->
                <div
                    class="absolute -inset-12 rounded-full"
                    style="background: radial-gradient(closest-side, color-mix(in srgb, var(--color-accent) 9%, transparent), transparent)"
                    aria-hidden="true"
                ></div>
                <img
                    src={current.illustration}
                    alt=""
                    class="relative size-40 opacity-90 sm:size-48"
                />
            </div>

            <p
                class="inline-flex items-center gap-1.5 rounded-full border border-line bg-elevated px-2.5 py-1 text-xs font-medium text-muted"
            >
                <span
                    class={cn('size-1.5 rounded-full', current.dot)}
                    aria-hidden="true"
                ></span>
                <span>{current.chip}</span>
            </p>

            <h1
                class="mt-4 text-2xl font-semibold tracking-tight text-balance text-ink sm:text-3xl"
            >
                {current.headline}
            </h1>

            <p class="mt-3 max-w-sm text-[15px] leading-relaxed text-muted">
                {current.body}
            </p>

            <div
                class="mt-8 flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row"
            >
                <Link
                    href={primaryHref}
                    class={cn(
                        'inline-flex w-full items-center justify-center rounded-lg bg-accent px-5 py-3 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong active:translate-y-0 sm:w-auto',
                        focusRing,
                    )}
                >
                    {primaryLabel}
                </Link>
                {#if current.secondary === 'home'}
                    <Link
                        href={homeHref}
                        class={cn(
                            'inline-flex w-full items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-medium text-muted transition-colors hover:bg-elevated hover:text-ink sm:w-auto',
                            focusRing,
                        )}
                    >
                        <House class="size-4" strokeWidth={1.75} />
                        {homeLabel}
                    </Link>
                {:else}
                    <button
                        type="button"
                        onclick={() =>
                            current.secondary === 'retry'
                                ? window.location.reload()
                                : history.back()}
                        class={cn(
                            'inline-flex w-full items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-medium text-muted transition-colors hover:bg-elevated hover:text-ink sm:w-auto',
                            focusRing,
                        )}
                    >
                        {#if current.secondary === 'retry'}
                            <RotateCw class="size-4" strokeWidth={1.75} />
                            Try again
                        {:else}
                            <ArrowLeft class="size-4" strokeWidth={1.75} />
                            Go back
                        {/if}
                    </button>
                {/if}
            </div>
        </div>
    </main>
</div>
