<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    /**
     * Full-bleed photographic hero. The overlay grades into the canvas colour
     * rather than pure black so the photograph dissolves into the page instead
     * of sitting on it as a separate block.
     *
     * NOTE: the photograph is a remote Unsplash asset. Replace it with an
     * owned, licensed image before launch.
     */
    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas';
</script>

<!-- `isolate` confines the sage wash's blending group to this header; without it
     the blend escapes to the root stacking context and eats the nav. -->
<header
    class="relative isolate flex h-screen min-h-[700px] w-full flex-col justify-end overflow-hidden"
>
    <!-- Desaturated and dimmed so the photograph joins the sage world rather
         than importing its own colour cast. Intrinsic size is declared so the
         hero reserves its space and cannot shift layout as it decodes. -->
    <img
        src="https://images.unsplash.com/photo-1573167101669-476636b96cea?auto=format&fit=crop&w=2000&q=80"
        alt="Four women entrepreneurs around a boardroom table, one mid-sentence while the others listen and laugh"
        width="2000"
        height="1335"
        fetchpriority="high"
        decoding="async"
        class="absolute inset-0 size-full object-cover object-center brightness-[.88] saturate-[.62]"
    />

    <!-- Sage wash: ties any source photograph to the accent hue. -->
    <div
        class="absolute inset-0 bg-accent/10 mix-blend-soft-light"
        aria-hidden="true"
    ></div>

    <!-- Grade to canvas, not black: the hero should end where the page begins.
         The top scrim keeps the nav legible over a bright photograph. -->
    <div
        class="absolute inset-0 bg-gradient-to-t from-canvas via-canvas/65 to-canvas/15"
        aria-hidden="true"
    ></div>
    <div
        class="absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-canvas/70 to-transparent"
        aria-hidden="true"
    ></div>

    <!-- Padding sits OUTSIDE the 7xl cap, matching every section below, so the
         headline shares one vertical line with the section headings. Putting
         px-* inside the cap indents the hero by the padding amount. -->
    <div class="relative z-10 w-full px-6 pb-16 lg:px-12 lg:pb-24">
        <div
            class="mx-auto flex w-full max-w-7xl flex-col items-start justify-between gap-12 lg:flex-row lg:items-end"
        >
            <div class="w-full lg:max-w-3xl">
                <h1
                    class="rise text-5xl leading-[1.08] font-semibold tracking-[-0.03em] text-balance text-white sm:text-6xl lg:text-7xl"
                >
                    Where founders meet
                    <br class="hidden sm:block" />
                    their mentors
                </h1>
            </div>

            <div class="w-full lg:mb-4 lg:max-w-md xl:max-w-lg">
                <p
                    class="rise rise-1 mb-8 text-base leading-relaxed text-pretty text-muted sm:text-lg"
                >
                    Vetted mentors, shared availability, and a written report
                    after every meeting. An invitation-only programme for the
                    women building what's next.
                </p>
                <div class="rise rise-2 flex flex-wrap items-center gap-4">
                    <Link
                        href="/login"
                        class={cn(
                            'inline-flex min-h-11 items-center rounded-lg bg-accent px-6 py-3 text-sm font-semibold text-on-accent shadow-btn transition-all duration-200 hover:-translate-y-px hover:bg-accent-strong hover:shadow-btn-strong active:translate-y-0 sm:text-base',
                            focusRing,
                        )}
                    >
                        Sign in
                    </Link>
                    <a
                        href="#how-it-works"
                        class={cn(
                            'inline-flex min-h-11 items-center rounded-lg border border-line-strong px-6 py-3 text-sm font-medium text-ink transition-colors duration-200 hover:bg-elevated sm:text-base',
                            focusRing,
                        )}
                    >
                        How it works
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
    /* One orchestrated page-load rather than scattered scroll reveals. The
       animation only ever *adds* to a default-visible element, so if it never
       runs (reduced motion, no animation support, a headless renderer), the
       hero still renders fully. */
    .rise {
        animation: rise 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .rise-1 {
        animation-delay: 100ms;
    }
    .rise-2 {
        animation-delay: 200ms;
    }

    @keyframes rise {
        from {
            opacity: 0;
            transform: translate3d(0, 20px, 0);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .rise {
            animation: none;
        }
    }
</style>
