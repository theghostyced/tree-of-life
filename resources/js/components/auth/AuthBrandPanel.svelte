<script lang="ts">
    import Logo from '@/components/Logo.svelte';
    import AuthFooter from './AuthFooter.svelte';
    import ShaderGlow from './ShaderGlow.svelte';

    /**
     * Left-hand brand panel for the auth screen. A warm, softly lit dark surface
     * — the "greenhouse at first light": a WebGL shader field of slowly drifting
     * sage and amber glows — carrying the Tree Of Life Fund logo, a welcoming
     * statement, the community avatars, and the legal footer. Desktop only.
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
    <!-- Warm greenhouse light: a slow-drifting WebGL glow field. -->
    <ShaderGlow />
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
    /* Settle the edges so the shader field reads as light in a room, not a flat
       panel. Sits above the shader canvas, below the z-10 content. */
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
</style>
