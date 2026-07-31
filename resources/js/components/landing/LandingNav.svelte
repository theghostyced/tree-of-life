<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Menu, X } from '@lucide/svelte';
    import Logo from '@/components/Logo.svelte';
    import { cn } from '@/lib/utils';

    /**
     * Public top bar for the marketing page. Sits over the hero photograph, so
     * every control is light-on-dark regardless of what the image is doing
     * behind it. Tolfund is invitation-only — there is no "sign up" here on
     * purpose; the only account action a visitor can take is signing in.
     */
    const sections = [
        { href: '#how-it-works', label: 'How it works' },
        { href: '#programme', label: 'The programme' },
        { href: '#faq', label: 'Questions' },
    ];

    let menuOpen = $state(false);

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas';
</script>

<nav
    class="absolute top-0 z-50 flex w-full items-center justify-between px-6 py-5 lg:px-12"
>
    <Link
        href="/"
        aria-label="Tree Of Life Fund home"
        class={cn('rounded-md', focusRing)}
    >
        <Logo size="sm" />
    </Link>

    <ul class="hidden items-center gap-8 text-sm font-medium lg:flex">
        {#each sections as section (section.href)}
            <li>
                <a
                    href={section.href}
                    class={cn(
                        'rounded-md text-ink/80 transition-colors hover:text-ink',
                        focusRing,
                    )}
                >
                    {section.label}
                </a>
            </li>
        {/each}
    </ul>

    <div class="hidden items-center gap-3 text-sm font-medium md:flex">
        <Link
            href="/login"
            class={cn(
                'rounded-lg bg-accent px-5 py-2.5 font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong hover:shadow-btn-strong active:translate-y-0',
                focusRing,
            )}
        >
            Sign in
        </Link>
    </div>

    <button
        type="button"
        onclick={() => (menuOpen = !menuOpen)}
        aria-expanded={menuOpen}
        aria-controls="landing-mobile-menu"
        aria-label={menuOpen ? 'Close menu' : 'Open menu'}
        class={cn('rounded-lg p-2 text-ink lg:hidden', focusRing)}
    >
        {#if menuOpen}
            <X class="size-6" strokeWidth={1.75} />
        {:else}
            <Menu class="size-6" strokeWidth={1.75} />
        {/if}
    </button>
</nav>

{#if menuOpen}
    <div
        id="landing-mobile-menu"
        class="animate-fade-in absolute top-[4.75rem] right-6 left-6 z-50 rounded-xl border border-line bg-surface p-2 lg:hidden"
    >
        {#each sections as section (section.href)}
            <a
                href={section.href}
                onclick={() => (menuOpen = false)}
                class={cn(
                    'block rounded-lg px-4 py-3 text-sm font-medium text-ink transition-colors hover:bg-elevated',
                    focusRing,
                )}
            >
                {section.label}
            </a>
        {/each}
        <Link
            href="/login"
            class={cn(
                'mt-1 block rounded-lg bg-accent px-4 py-3 text-center text-sm font-semibold text-on-accent',
                focusRing,
            )}
        >
            Sign in
        </Link>
    </div>
{/if}
