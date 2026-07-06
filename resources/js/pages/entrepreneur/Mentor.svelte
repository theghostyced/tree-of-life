<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { BadgeCheck, ChevronLeft } from '@lucide/svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import { YEAR_RANGES } from '@/lib/onboarding-options';

    type Mentor = {
        id: number;
        name: string;
        expertise: string | null;
        industries: string[];
        yearsExperience: number | null;
        availability: string | null;
        bio: string | null;
    };

    let {
        mentor,
        pairedAt = null,
    }: {
        mentor: Mentor;
        pairedAt: string | null;
    } = $props();

    const firstName = $derived(mentor.name.split(' ')[0]);

    const initials = (name: string) =>
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0])
            .join('')
            .toUpperCase();

    const yearsLabel = (v: number | null) =>
        v == null
            ? null
            : (YEAR_RANGES.find((r) => r.value === v)?.label ?? null);

    const pairedLabel = $derived(
        pairedAt
            ? new Date(pairedAt).toLocaleDateString('en-US', {
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              })
            : null,
    );
</script>

<EntrepreneurLayout title="My mentor">
    <div class="mx-auto w-full max-w-4xl px-6 py-8">
        <!-- Header -->
        <div>
            <Link
                href="/entrepreneur/mentors"
                class="inline-flex items-center gap-1 text-sm text-muted transition-colors outline-none hover:text-ink focus-visible:text-ink"
            >
                <ChevronLeft class="size-4" strokeWidth={1.75} />
                Mentors
            </Link>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink">
                My mentor
            </h1>
            <p class="mt-1.5 text-[15px] text-muted">
                The mentor you're working with through the programme.
            </p>
        </div>

        <!-- Profile -->
        <div class="mt-8 rounded-2xl border border-line bg-panel/40 p-6 sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <div
                    class="flex size-16 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xl font-semibold text-accent"
                >
                    {initials(mentor.name)}
                </div>
                <div class="min-w-0 flex-1">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent"
                    >
                        <BadgeCheck class="size-3.5" strokeWidth={2} />
                        Your mentor
                    </span>
                    <h2
                        class="mt-2 text-2xl font-semibold tracking-tight text-ink"
                    >
                        {mentor.name}
                    </h2>
                    {#if mentor.expertise}
                        <p class="text-[15px] text-accent capitalize">
                            {mentor.expertise}
                        </p>
                    {/if}
                    {#if pairedLabel}
                        <p class="mt-1 text-sm text-muted">
                            Paired since {pairedLabel}
                        </p>
                    {/if}
                </div>
            </div>

            {#if mentor.bio}
                <div class="mt-6 border-t border-line pt-6">
                    <h3 class="text-sm font-semibold text-ink">
                        About {firstName}
                    </h3>
                    <p
                        class="mt-2 max-w-2xl text-[15px] leading-relaxed text-muted"
                    >
                        {mentor.bio}
                    </p>
                </div>
            {/if}

            {#if mentor.industries.length}
                <div class="mt-6">
                    <p
                        class="text-xs font-medium tracking-wide text-faint uppercase"
                    >
                        Focus areas
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        {#each mentor.industries as ind (ind)}
                            <span
                                class="rounded-md border border-line bg-elevated px-2.5 py-1 text-xs text-muted capitalize"
                            >
                                {ind}
                            </span>
                        {/each}
                    </div>
                </div>
            {/if}

            {#if yearsLabel(mentor.yearsExperience) || mentor.availability}
                <dl
                    class="mt-6 grid grid-cols-1 gap-4 border-t border-line pt-6 sm:grid-cols-2"
                >
                    {#if yearsLabel(mentor.yearsExperience)}
                        <div>
                            <dt class="text-xs text-muted">Experience</dt>
                            <dd class="mt-0.5 text-[15px] text-ink">
                                {yearsLabel(mentor.yearsExperience)}
                            </dd>
                        </div>
                    {/if}
                    {#if mentor.availability}
                        <div>
                            <dt class="text-xs text-muted">Availability</dt>
                            <dd class="mt-0.5 text-[15px] text-ink">
                                {mentor.availability}
                            </dd>
                        </div>
                    {/if}
                </dl>
            {/if}
        </div>

        <!-- Meetings & reports (coming soon) -->
        <div
            class="mt-5 flex flex-col gap-6 rounded-2xl border border-line bg-accent-soft/40 p-6 sm:flex-row sm:items-center sm:justify-between sm:gap-8"
        >
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h3 class="text-base font-semibold text-ink">
                        Meetings &amp; reports
                    </h3>
                    <span
                        class="rounded-full border border-line bg-elevated px-2 py-0.5 text-[11px] font-medium text-muted"
                    >
                        Coming soon
                    </span>
                </div>
                <p class="mt-2 max-w-md text-sm text-muted">
                    Scheduling meetings with {firstName} and reading your meeting
                    reports arrives here next.
                </p>
            </div>
            <img
                src="/images/illustrations/social-talk.svg"
                alt=""
                class="h-28 w-auto shrink-0 self-center opacity-90 sm:h-36"
            />
        </div>
    </div>
</EntrepreneurLayout>
