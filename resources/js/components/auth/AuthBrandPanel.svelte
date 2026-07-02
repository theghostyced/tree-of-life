<script lang="ts">
    import Logo from '@/components/Logo.svelte';
    import AuthFooter from './AuthFooter.svelte';

    /**
     * Left-hand brand panel for the auth screen. A warm, softly lit dark surface
     * — the "greenhouse at first light": layered sage and amber glows that drift
     * slowly — carrying the Tree Of Life Fund logo, a welcoming statement, the
     * community avatars, and the legal footer. Desktop only.
     */
    const community = [
        { initials: 'EN', class: 'bg-accent/25 text-accent' },
        { initials: 'ME', class: 'bg-accent-orange/25 text-accent-orange' },
        { initials: 'AD', class: 'bg-surface text-ink' },
    ];
</script>

<aside
    class="relative hidden w-[45%] flex-col justify-between overflow-hidden border-r border-line bg-panel lg:flex"
>
    <!-- Warm greenhouse light: layered ambient glows, no cold wireframe grid. -->
    <div class="glow glow-sage" aria-hidden="true"></div>
    <div class="glow glow-amber" aria-hidden="true"></div>
    <div class="glow glow-breathe" aria-hidden="true"></div>
    <div class="vignette" aria-hidden="true"></div>

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
                {#each community as person (person.initials)}
                    <div
                        class="flex size-10 items-center justify-center rounded-full border-2 border-panel text-xs font-semibold {person.class}"
                    >
                        {person.initials}
                    </div>
                {/each}
            </div>
            <p class="text-sm font-medium text-muted">
                Founders, mentors, and reviewers in one workflow
            </p>
        </div>
    </div>

    <AuthFooter class="relative z-10 px-12" />
</aside>

<style>
    /* Soft ambient light pools. Each drifts on its own long, offset cycle so the
       light feels alive but calm — like sun moving through leaves, never a
       pulse. Motion is tiny and slow on purpose; transform + opacity only. */
    .glow {
        position: absolute;
        z-index: 0;
        border-radius: 9999px;
        pointer-events: none;
        will-change: transform, opacity;
    }

    /* Cool sage light spilling from the top-left, near the logo. */
    .glow-sage {
        top: -180px;
        left: -140px;
        width: 540px;
        height: 540px;
        background: radial-gradient(
            circle,
            color-mix(in srgb, var(--color-accent) 11%, transparent),
            transparent 62%
        );
        animation: drift-sage 26s ease-in-out infinite;
    }

    /* Warm amber light rising from the bottom-right — the morning-sun note
       that turns the panel from "cold tool" into "warm room". */
    .glow-amber {
        right: -200px;
        bottom: -180px;
        width: 600px;
        height: 600px;
        background: radial-gradient(
            circle,
            color-mix(in srgb, var(--color-glow-amber) 7%, transparent),
            transparent 60%
        );
        animation: drift-amber 32s ease-in-out infinite;
    }

    /* A gentle third light behind the headline that slowly breathes. */
    .glow-breathe {
        top: 32%;
        left: 24%;
        width: 460px;
        height: 460px;
        background: radial-gradient(
            circle,
            color-mix(in srgb, var(--color-accent-strong) 7%, transparent),
            transparent 66%
        );
        animation: breathe 18s ease-in-out infinite;
    }

    /* Settle the edges so the glows read as light in a room, not flat panels. */
    .vignette {
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        background: radial-gradient(
            120% 100% at 50% 0%,
            transparent 55%,
            rgba(0, 0, 0, 0.4)
        );
    }

    @keyframes drift-sage {
        0%,
        100% {
            transform: translate3d(0, 0, 0) scale(1);
            opacity: 0.7;
        }
        50% {
            transform: translate3d(22px, 26px, 0) scale(1.05);
            opacity: 1;
        }
    }

    @keyframes drift-amber {
        0%,
        100% {
            transform: translate3d(0, 0, 0) scale(1);
            opacity: 0.55;
        }
        50% {
            transform: translate3d(-26px, -20px, 0) scale(1.07);
            opacity: 0.9;
        }
    }

    @keyframes breathe {
        0%,
        100% {
            transform: scale(1);
            opacity: 0.45;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .glow-sage,
        .glow-amber,
        .glow-breathe {
            animation: none;
        }
    }
</style>
