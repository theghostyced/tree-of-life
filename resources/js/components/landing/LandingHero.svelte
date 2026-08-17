<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas';
</script>

<!-- `isolate` keeps the hero's layers in their own stacking context, so the
     full-bleed overlays can never paint over the nav. -->
<header
    class="relative isolate flex h-screen min-h-[700px] w-full flex-col justify-end overflow-hidden"
>
    <!-- Fully desaturated so the photograph joins the accent world rather than
         importing its own colour cast. Intrinsic size is declared so the hero
         reserves its space and cannot shift layout as it decodes. -->
    <!-- The hero is the LCP element and spans the viewport, so it ships a
         srcset rather than one file: a phone pulls 112KB where a wide 2x
         display pulls 356KB. `sizes` is 100vw because the frame is full-bleed. -->
    <img
        src="/images/landing/boardroom-founders-1920.jpg"
        srcset="/images/landing/boardroom-founders-1280.jpg 1280w,
                /images/landing/boardroom-founders-1920.jpg 1920w,
                /images/landing/boardroom-founders-2560.jpg 2560w"
        sizes="100vw"
        alt="Five women entrepreneurs around a boardroom table, one speaking while the others listen"
        width="1920"
        height="1282"
        fetchpriority="high"
        class="absolute inset-0 size-full object-cover object-center contrast-110 grayscale"
    />

    <!-- Accent wash: ties any source photograph to the brand hue. Plain alpha,
         not mix-blend-soft-light: blending a large filtered image intermittently
         lost its raster in Chrome and the hero went blank. -->
    <div class="wash" aria-hidden="true"></div>

    <!-- Grade to canvas, not black: the hero should end where the page begins.
         The stops are held low so the photograph stays legible through the
         upper two thirds; a full-height wash would erase it against a light
         canvas. The top scrim keeps the nav readable over a bright frame. -->
    <div
        class="absolute inset-0 bg-gradient-to-t from-canvas from-15% via-canvas/70 via-38% to-transparent to-72%"
        aria-hidden="true"
    ></div>
    <div
        class="absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-canvas/85 to-transparent"
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
                <!-- Fluid rather than three fixed steps: the jump from 48 to 60
                     to 72px landed awkwardly in the gaps between breakpoints.
                     The 4.5rem ceiling keeps it under the display maximum, and
                     text-balance owns the line break so the composition adapts
                     to the copy instead of a hard-coded <br>. -->
                <h1
                    class="rise text-[clamp(2.75rem,6.2vw,4.5rem)] leading-[1.06] font-semibold tracking-[-0.025em] text-balance text-ink"
                >
                    Where founders meet their mentors
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
                        Get Started
                    </Link>
                    <a
                        href="/how-it-works"
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
    .wash {
        position: absolute;
        inset: 0;
        background: color-mix(in srgb, var(--color-accent) 14%, transparent);
    }

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
