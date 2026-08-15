<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Menu, X } from '@lucide/svelte';
    import Logo from '@/components/Logo.svelte';
    import { cn } from '@/lib/utils';

    /**
     * Public top bar for the marketing page. Sits over the hero photograph, so
     * every control is light-on-dark regardless of what the image is doing
     * behind it. Tolfund is invitation-only, so there is no "sign up" here on
     * purpose; the only account action a visitor can take is signing in.
     */
    /** Ordered to match the page, so the bar reads as a table of contents. */
    const sections = [
        { href: '#fund', label: 'The fund' },
        { href: '#how-it-works', label: 'How it works' },
        { href: '#programme', label: 'The programme' },
        { href: '#faq', label: 'Questions' },
    ];

    let menuOpen = $state(false);

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas';
</script>

<!-- Full-bleed bar whose inner content aligns to the same 7xl column as every
     section, per the Page-Width Rule in DESIGN.md. -->
<nav class="absolute top-0 z-50 w-full px-6 py-5 lg:px-12">
    <div class="mx-auto flex w-full max-w-7xl items-center justify-between">
        <Link
            href="/"
            aria-label="Tree Of Life Fund home"
            class={cn('rounded-md', focusRing)}
        >
            <Logo size="md" />
        </Link>

        <ul class="hidden items-center gap-8 text-sm font-medium lg:flex">
            {#each sections as section (section.href)}
                <li>
                    <a
                        href={section.href}
                        class={cn(
                            'rounded-md py-2 text-ink/90 transition-colors hover:text-ink',
                            focusRing,
                        )}
                    >
                        {section.label}
                    </a>
                </li>
            {/each}
        </ul>

        <!-- lg, not md: below the lg breakpoint the burger owns the nav, and showing
         both a Sign in button and a burger duplicates the affordance. -->
        <div class="hidden items-center gap-3 text-sm font-medium lg:flex">
            <!-- A link, not a filled button: the hero already carries the primary
             Sign in CTA, and two competing buttons blunt both. -->
            <Link
                href="/login"
                class={cn(
                    'rounded-md py-2 font-medium text-accent underline-offset-4 transition-colors hover:text-accent-strong hover:underline',
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
            class={cn('-mr-2.5 rounded-lg p-2.5 text-ink lg:hidden', focusRing)}
        >
            {#if menuOpen}
                <X class="size-6" strokeWidth={1.75} />
            {:else}
                <Menu class="size-6" strokeWidth={1.75} />
            {/if}
        </button>
    </div>
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
