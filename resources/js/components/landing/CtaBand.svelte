<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    /**
     * Closing call to action. The source design used a purple brand gradient,
     * which DESIGN.md rules out explicitly; this echoes AuthBrandPanel's
     * "greenhouse at first light" instead — soft sage and amber glows over the
     * panel surface, done in CSS rather than the auth screen's WebGL field.
     */
    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-panel';
</script>

<section
    class="relative overflow-hidden border-y border-line bg-panel px-6 py-24 lg:px-12"
>
    <div class="glow glow-sage" aria-hidden="true"></div>
    <div class="glow glow-amber" aria-hidden="true"></div>

    <div class="relative z-10 mx-auto max-w-3xl text-center">
        <h2
            class="mb-6 text-4xl leading-tight font-semibold tracking-tight text-balance text-white md:text-5xl"
        >
            Already invited to Tolfund?
        </h2>
        <p class="mx-auto mb-10 max-w-2xl text-lg text-muted">
            Your invitation email carries a single-use link to set up your
            account. Once you're in, your mentor, your meetings, and every
            report live in one place.
        </p>
        <div
            class="flex flex-col items-center justify-center gap-4 sm:flex-row"
        >
            <Link
                href="/login"
                class={cn(
                    'w-full rounded-lg bg-accent px-8 py-3.5 font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong hover:shadow-btn-strong active:translate-y-0 sm:w-auto',
                    focusRing,
                )}
            >
                Sign in to Tolfund
            </Link>
            <a
                href="#faq"
                class={cn(
                    'w-full rounded-lg border border-line-strong px-8 py-3.5 font-medium text-ink transition-colors hover:bg-elevated sm:w-auto',
                    focusRing,
                )}
            >
                Read the questions
            </a>
        </div>
    </div>
</section>

<style>
    .glow {
        position: absolute;
        border-radius: 9999px;
        filter: blur(80px);
        pointer-events: none;
    }
    .glow-sage {
        top: -6rem;
        right: -6rem;
        width: 24rem;
        height: 24rem;
        background: color-mix(in srgb, var(--color-accent) 14%, transparent);
    }
    .glow-amber {
        bottom: -8rem;
        left: -6rem;
        width: 20rem;
        height: 20rem;
        background: color-mix(
            in srgb,
            var(--color-glow-amber) 10%,
            transparent
        );
    }
</style>
