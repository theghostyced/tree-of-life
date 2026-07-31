<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    /**
     * Full-bleed photographic hero. The overlay grades into the canvas colour
     * rather than pure black so the photograph dissolves into the page instead
     * of sitting on it as a separate block.
     *
     * NOTE: the photograph is a remote Unsplash asset carried over from the
     * source design — replace it with an owned, licensed image before launch.
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
         than importing its own colour cast. -->
    <img
        src="https://images.unsplash.com/photo-1573164713988-8665fc963095?ixlib=rb-4.0.3&auto=format&fit=crop&w=2069&q=80"
        alt=""
        class="absolute inset-0 size-full object-cover object-top brightness-[.78] saturate-[.55]"
    />

    <!-- Sage wash: ties any source photograph to the accent hue. -->
    <div
        class="absolute inset-0 bg-accent/10 mix-blend-soft-light"
        aria-hidden="true"
    ></div>

    <!-- Grade to canvas, not black: the hero should end where the page begins. -->
    <div
        class="absolute inset-0 bg-gradient-to-t from-canvas via-canvas/75 to-canvas/20"
        aria-hidden="true"
    ></div>

    <div
        class="relative z-10 mx-auto flex w-full max-w-7xl flex-col items-start justify-between gap-12 px-6 pb-16 lg:flex-row lg:items-end lg:pb-24"
    >
        <div class="w-full lg:max-w-3xl">
            <h1
                class="text-5xl leading-[1.1] font-semibold tracking-tight text-white sm:text-6xl lg:text-7xl"
            >
                Where founders meet
                <br class="hidden sm:block" />
                their mentors
            </h1>
        </div>

        <div class="w-full lg:mb-4 lg:max-w-md xl:max-w-lg">
            <p class="mb-8 text-base leading-relaxed text-muted sm:text-lg">
                Vetted mentors, shared availability, and a written report after
                every meeting — an invitation-only programme for the women
                building what's next.
            </p>
            <div class="flex flex-wrap items-center gap-4">
                <Link
                    href="/login"
                    class={cn(
                        'rounded-lg bg-accent px-6 py-3 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong hover:shadow-btn-strong active:translate-y-0 sm:text-base',
                        focusRing,
                    )}
                >
                    Sign in
                </Link>
                <a
                    href="#how-it-works"
                    class={cn(
                        'rounded-lg border border-line-strong px-6 py-3 text-sm font-medium text-ink transition-colors hover:bg-elevated sm:text-base',
                        focusRing,
                    )}
                >
                    How it works
                </a>
            </div>
        </div>
    </div>
</header>
