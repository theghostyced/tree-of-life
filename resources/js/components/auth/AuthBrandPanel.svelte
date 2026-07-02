<script lang="ts">
    import Logo from '@/components/Logo.svelte';
    import AuthFooter from './AuthFooter.svelte';

    /**
     * Left-hand brand panel for the auth screen: framed dark surface with a
     * masked grid, ambient sage glows, subtle tracing beams, the Tree Of Life
     * Fund logo, a hero statement, and the legal footer. Desktop only.
     */
    const roles = [
        { initials: 'EN', class: 'bg-elevated text-accent' },
        { initials: 'ME', class: 'bg-line-strong text-muted' },
        { initials: 'AD', class: 'bg-surface text-ink' },
    ];
</script>

<aside
    class="relative hidden w-[45%] flex-col justify-between overflow-hidden border-r border-line bg-panel lg:flex"
>
    <div class="grid-pattern absolute inset-0 z-0" aria-hidden="true"></div>
    <div class="ambient-glow animate-pulse-slow z-0" aria-hidden="true"></div>
    <div
        class="pointer-events-none absolute right-[-200px] bottom-[-200px] z-0 h-[600px] w-[600px] rounded-full"
        style="background: radial-gradient(circle, rgba(163,177,138,0.02) 0%, rgba(10,12,11,0) 60%);"
        aria-hidden="true"
    ></div>

    <!-- Sage lights tracing along the grid lines, turning through boxes -->
    <svg
        class="line-trace pointer-events-none absolute inset-0 z-0 h-full w-full"
        fill="none"
        preserveAspectRatio="none"
        aria-hidden="true"
    >
        <path
            class="trace-1"
            d="M -40 128 H 200 V 320 H 72 V 480 H 360 V 192 H 552 V 416 H 296 V 640"
        />
        <path
            class="trace-2"
            d="M 640 64 H 424 V 224 H 552 V 384 H 328 V 544 H 200 V 704"
        />
        <path
            class="trace-3"
            d="M 128 -40 V 160 H 328 V 288 H 200 V 448 H 456 V 640"
        />
    </svg>

    <div class="relative z-10 p-12">
        <Logo size="lg" />
    </div>

    <div class="relative z-10 flex flex-1 flex-col justify-center p-12">
        <h1
            class="mb-6 text-5xl leading-[1.1] font-semibold tracking-tight text-balance text-white"
        >
            Back the builders,<br />fund the milestones.
        </h1>
        <p class="max-w-md text-lg leading-relaxed text-muted">
            An invitation only workspace where funding and mentorship empower
            founders, especially women, from their first application through
            funded milestones and beyond.
        </p>

        <div class="mt-12 flex items-center gap-4">
            <div class="flex -space-x-3">
                {#each roles as role (role.initials)}
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-panel text-xs font-medium {role.class}"
                    >
                        {role.initials}
                    </div>
                {/each}
            </div>
            <div class="text-sm font-medium text-muted">
                Founders, mentors, and reviewers in one workflow
            </div>
        </div>
    </div>

    <AuthFooter class="relative z-10 px-12" />
</aside>

<style>
    .grid-pattern {
        background-size: 32px 32px;
        background-image:
            linear-gradient(
                to right,
                rgba(26, 31, 28, 0.5) 1px,
                transparent 1px
            ),
            linear-gradient(
                to bottom,
                rgba(26, 31, 28, 0.5) 1px,
                transparent 1px
            );
        mask-image: linear-gradient(to bottom right, black 20%, transparent 80%);
        -webkit-mask-image: linear-gradient(
            to bottom right,
            black 20%,
            transparent 80%
        );
    }

    .ambient-glow {
        position: absolute;
        top: -200px;
        left: -200px;
        width: 800px;
        height: 800px;
        border-radius: 50%;
        pointer-events: none;
        background: radial-gradient(
            circle,
            rgba(163, 177, 138, 0.04) 0%,
            rgba(5, 6, 6, 0) 60%
        );
    }

    /* A lit segment travels a grid-aligned path, tracing box borders. The path
       coordinates are multiples of 32px, so the light rides the existing grid
       lines. Masked to fade with the grid toward the bottom-right. */
    .line-trace {
        overflow: visible;
        mask-image: linear-gradient(to bottom right, black 20%, transparent 75%);
        -webkit-mask-image: linear-gradient(
            to bottom right,
            black 20%,
            transparent 75%
        );
    }

    .line-trace path {
        fill: none;
        stroke: rgba(163, 177, 138, 0.42);
        stroke-width: 1;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 52 2400;
        filter: drop-shadow(0 0 2px rgba(163, 177, 138, 0.25));
        /* dashoffset repaints, but strokes are 1px in a small masked area and
           GPU-compositing the layer keeps it cheap. */
        will-change: stroke-dashoffset;
    }

    .trace-1 {
        animation: trace 11s linear infinite;
    }
    .trace-2 {
        animation: trace 14s linear infinite;
        animation-delay: -5s;
    }
    .trace-3 {
        animation: trace 17s linear infinite;
        animation-delay: -9s;
    }

    @keyframes trace {
        from {
            stroke-dashoffset: 0;
        }
        to {
            stroke-dashoffset: -2452;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .line-trace path {
            animation: none;
            opacity: 0;
        }
    }
</style>
