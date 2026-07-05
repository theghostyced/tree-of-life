<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import { ArrowLeft } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Logo from '@/components/Logo.svelte';
    import { cn } from '@/lib/utils';
    import type { Auth } from '@/types/auth';

    /**
     * 403 page, rendered by the exception handler in bootstrap/app.php.
     * Standalone on purpose: the viewer lacks the role this area belongs
     * to, so it shows no role chrome — just a way back to their own space.
     */
    const auth = $derived(page.props.auth as Auth | undefined);

    const dashboards: Record<string, string> = {
        admin: '/admin/dashboard',
        entrepreneur: '/entrepreneur/dashboard',
        mentor: '/mentor/dashboard',
    };

    const homeHref = $derived(dashboards[auth?.role ?? ''] ?? '/');
    const homeLabel = $derived(
        homeHref === '/' ? 'Go to the homepage' : 'Go to your dashboard',
    );

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
</script>

<AppHead title="Access restricted" />

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
                <!-- Soft sage halo grounds the illustration on the dark canvas. -->
                <div
                    class="absolute -inset-12 rounded-full"
                    style="background: radial-gradient(closest-side, color-mix(in srgb, var(--color-accent) 9%, transparent), transparent)"
                    aria-hidden="true"
                ></div>
                <img
                    src="/images/illustrations/security-spy.svg"
                    alt=""
                    class="relative size-40 opacity-90 sm:size-48"
                />
            </div>

            <p
                class="inline-flex items-center gap-1.5 rounded-full border border-line bg-elevated px-2.5 py-1 text-xs font-medium text-muted"
            >
                <span
                    class="size-1.5 rounded-full bg-accent-orange"
                    aria-hidden="true"
                ></span>
                <span>403 · Restricted access</span>
            </p>

            <h1
                class="mt-4 text-2xl font-semibold tracking-tight text-balance text-ink sm:text-3xl"
            >
                This area isn&rsquo;t part of your role
            </h1>

            <p class="mt-3 max-w-sm text-[15px] leading-relaxed text-muted">
                You&rsquo;re signed in, but this page belongs to a different
                role on Tolfund. If you think you should have access, ask your
                program admin to update your account.
            </p>

            <div
                class="mt-8 flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row"
            >
                <Link
                    href={homeHref}
                    class={cn(
                        'inline-flex w-full items-center justify-center rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong active:translate-y-0 sm:w-auto',
                        focusRing,
                    )}
                >
                    {homeLabel}
                </Link>
                <button
                    type="button"
                    onclick={() => history.back()}
                    class={cn(
                        'inline-flex w-full items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium text-muted transition-colors hover:bg-elevated hover:text-ink sm:w-auto',
                        focusRing,
                    )}
                >
                    <ArrowLeft class="size-4" strokeWidth={1.75} />
                    Go back
                </button>
            </div>
        </div>
    </main>
</div>
