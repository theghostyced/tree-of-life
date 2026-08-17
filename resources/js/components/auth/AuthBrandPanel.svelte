<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Logo from '@/components/Logo.svelte';
    import AuthFooter from './AuthFooter.svelte';

    /**
     * Left-hand brand panel for the auth screen: a full-height photograph,
     * desaturated and washed with the accent green so it reads as part of the
     * theme rather than stock art, carrying the Tree Of Life Fund logo, a
     * welcoming statement set low in the frame, the community avatars, and the
     * legal footer. Desktop only.
     */
    const community = [
        { initials: 'EN', class: 'bg-white/20 text-white' },
        { initials: 'ME', class: 'bg-accent-orange/25 text-accent-orange' },
        { initials: 'AD', class: 'bg-surface text-ink' },
    ];
</script>

<aside
    class="relative hidden w-[45%] flex-col justify-between overflow-hidden border-r border-line bg-accent lg:flex"
>
    <!-- A landscape frame in a tall panel: object-position holds the group
         centred so the vertical crop keeps faces rather than table and wall. -->
    <img
        src="/images/auth/corporate-founders.jpg"
        alt=""
        aria-hidden="true"
        fetchpriority="high"
        class="absolute inset-0 size-full object-cover object-[55%_35%] brightness-75 contrast-125 grayscale"
    />
    <div class="tint" aria-hidden="true"></div>
    <div class="scrim" aria-hidden="true"></div>

    <div class="relative z-10 p-12">
        <Link
            href="/"
            aria-label="Tree Of Life Fund home"
            class="inline-block rounded-md outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-panel"
        >
            <Logo size="lg" class="text-white" />
        </Link>
    </div>

    <div class="relative z-10 flex flex-1 flex-col justify-end p-12">
        <h1
            class="mb-6 text-5xl leading-[1.1] font-semibold tracking-tight text-balance text-white"
        >
            Where founders<br />meet their mentors.
        </h1>
        <p class="max-w-md text-lg leading-relaxed text-white/80">
            An invitation-only workspace that pairs entrepreneurs with mentors,
            schedules their meetings, and keeps a clear report of every one,
            especially for the women building what's next.
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
            <p class="text-sm font-medium text-white/80">
                Founders, mentors, and reviewers in one workflow
            </p>
        </div>
    </div>

    <AuthFooter
        class="relative z-10 border-white/15 px-12 text-white/70 [&_a:hover]:text-white"
    />
</aside>

<style>
    /* Wash the grey photo in the brand green so it belongs to the theme.
       Sits above the image, below the z-10 content. */
    .tint {
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        background: color-mix(in srgb, var(--color-accent) 18%, transparent);
    }

    /* Legibility, not decoration: the photo is bright sky at the top (behind the
       logo) and busy city bokeh at the bottom (behind the copy). Darken both
       ends so white text clears contrast over either. */
    .scrim {
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        background:
            linear-gradient(
                to bottom,
                color-mix(in srgb, var(--color-ink) 55%, transparent) 0%,
                transparent 28%
            ),
            linear-gradient(
                to top,
                color-mix(in srgb, var(--color-ink) 82%, transparent) 0%,
                color-mix(in srgb, var(--color-ink) 58%, transparent) 30%,
                color-mix(in srgb, var(--color-ink) 14%, transparent) 62%,
                transparent 82%
            );
    }
</style>
