<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Globe } from '@lucide/svelte';
    import Logo from '@/components/Logo.svelte';

    /**
     * Public footer.
     *
     * The source design carried a row of social icons; those are omitted rather
     * than pointed at invented accounts. Legal hrefs stay as '#' placeholders,
     * matching the existing convention in AuthFooter.svelte. Swap them for
     * real destinations when those pages exist.
     */
    const columns = [
        {
            heading: 'The programme',
            links: [
                { label: 'The fund', href: '#fund' },
                { label: 'How it works', href: '#how-it-works' },
                { label: 'Inside Tolfund', href: '#programme' },
                { label: 'Questions', href: '#faq' },
            ],
        },
        {
            heading: 'Access',
            links: [
                { label: 'Sign in', href: '/login' },
                { label: 'Invitation help', href: '#faq' },
            ],
        },
        {
            heading: 'Legal',
            links: [
                { label: 'Terms of Service', href: '#' },
                { label: 'Privacy Policy', href: '#' },
                { label: 'Security', href: '#' },
            ],
        },
    ];

    /** py-2 lifts the hit area to ~34px, clearing WCAG 2.2 AA target size (24px). */
    const linkClass =
        'inline-block rounded-sm py-2 transition-colors hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50';
</script>

<footer class="bg-panel px-6 pt-20 pb-10 text-muted lg:px-12">
    <div class="mx-auto max-w-7xl">
        <div
            class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-5"
        >
            <div class="lg:col-span-2">
                <Logo size="sm" class="mb-6" />
                <p class="max-w-sm leading-relaxed text-muted">
                    An invitation-only programme pairing entrepreneurs with
                    mentors, scheduling their meetings, and keeping a clear
                    report of every one.
                </p>
            </div>

            {#each columns as column (column.heading)}
                <div>
                    <h2
                        class="mb-6 text-xs font-semibold tracking-[0.04em] text-ink uppercase"
                    >
                        {column.heading}
                    </h2>
                    <ul class="space-y-1 text-[15px]">
                        {#each column.links as link (link.label)}
                            <li>
                                {#if link.href.startsWith('/')}
                                    <Link href={link.href} class={linkClass}>
                                        {link.label}
                                    </Link>
                                {:else}
                                    <a href={link.href} class={linkClass}>
                                        {link.label}
                                    </a>
                                {/if}
                            </li>
                        {/each}
                    </ul>
                </div>
            {/each}
        </div>

        <div
            class="flex flex-col items-center justify-between gap-4 border-t border-line pt-8 text-sm text-muted md:flex-row"
        >
            <p>© 2026 Tree Of Life Fund. All rights reserved.</p>
            <p class="flex items-center gap-2">
                <Globe class="size-4" strokeWidth={1.75} />
                Invitation-only programme
            </p>
        </div>
    </div>
</footer>
