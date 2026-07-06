<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Search, Check, X } from '@lucide/svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import * as Select from '@/components/ui/select';
    import { Toaster, toast } from '@/components/ui/sonner';
    import { YEAR_RANGES } from '@/lib/onboarding-options';
    import { cn } from '@/lib/utils';

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
        mentors = [],
        focusAreas = [],
    }: {
        mentors: Mentor[];
        focusAreas: string[];
    } = $props();

    // ── Search + filter (client-side over the loaded pool) ──────────────
    let search = $state('');
    let focusArea = $state('all');

    const focusAreaLabel = $derived(
        focusArea === 'all' ? 'All focus areas' : focusArea,
    );

    const filtered = $derived(
        mentors.filter((m) => {
            const q = search.trim().toLowerCase();
            const matchesSearch =
                q === '' ||
                [m.name, m.expertise, m.bio].some((f) =>
                    f?.toLowerCase().includes(q),
                );
            const matchesFocus =
                focusArea === 'all' || m.industries.includes(focusArea);
            return matchesSearch && matchesFocus;
        }),
    );

    const isFiltering = $derived(search.trim() !== '' || focusArea !== 'all');

    function clearFilters() {
        search = '';
        focusArea = 'all';
    }

    // ── Helpers ─────────────────────────────────────────────────────────
    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

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

    // ── Choose a mentor ─────────────────────────────────────────────────
    let choosing = $state<number | null>(null);
    function choose(m: Mentor) {
        router.post(
            '/entrepreneur/pairings',
            { mentor_id: m.id },
            {
                onStart: () => (choosing = m.id),
                onFinish: () => (choosing = null),
                onSuccess: () =>
                    toast.success(`You're now paired with ${m.name}.`),
                onError: () =>
                    toast.error('That mentor is no longer available.'),
            },
        );
    }
</script>

<EntrepreneurLayout title="Find a mentor">
    <Toaster position="top-center" />

    <div class="mx-auto w-full max-w-5xl px-6 py-8">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Find your mentor
            </h1>
            <p class="mt-1.5 max-w-2xl text-[15px] text-muted">
                Browse mentors, see what they do and who they help, then choose
                the one who fits your business. You pair with one mentor.
            </p>
        </div>

        {#if mentors.length === 0}
            <!-- No mentors in the programme yet -->
            <div
                class="mt-8 flex flex-col items-center justify-center rounded-2xl border border-line bg-panel/40 px-6 py-16 text-center"
            >
                <img
                    src="/images/illustrations/weather-plants.svg"
                    alt=""
                    class="mb-6 size-40 opacity-90 sm:size-48"
                />
                <h3 class="text-lg font-semibold text-ink">
                    No mentors available yet
                </h3>
                <p class="mt-2 max-w-sm text-[15px] text-muted">
                    Mentors are still setting up their profiles. Check back soon
                    — we'll have people ready to guide you.
                </p>
            </div>
        {:else}
            <!-- Toolbar: search + focus-area filter -->
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1 sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-faint"
                        strokeWidth={1.75}
                    />
                    <input
                        type="search"
                        bind:value={search}
                        placeholder="Search by name or expertise"
                        class={cn(
                            'h-9 w-full rounded-lg border border-line bg-surface pr-3 pl-9 text-sm text-ink placeholder:text-faint',
                            'outline-none focus-visible:border-accent focus-visible:ring-3 focus-visible:ring-accent/30',
                        )}
                    />
                </div>

                {#if focusAreas.length}
                    <Select.Root type="single" bind:value={focusArea}>
                        <Select.Trigger
                            size="sm"
                            class="w-full sm:w-56"
                            aria-label="Filter by focus area"
                        >
                            {focusAreaLabel}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item value="all" label="All focus areas">
                                All focus areas
                            </Select.Item>
                            {#each focusAreas as fa (fa)}
                                <Select.Item value={fa} label={fa}>
                                    {fa}
                                </Select.Item>
                            {/each}
                        </Select.Content>
                    </Select.Root>
                {/if}

                <span
                    class="text-sm text-muted sm:ml-auto"
                    role="status"
                    aria-live="polite"
                >
                    {filtered.length}
                    {filtered.length === 1 ? 'mentor' : 'mentors'}
                </span>
            </div>

            {#if filtered.length}
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    {#each filtered as m (m.id)}
                        <article
                            class="flex flex-col rounded-2xl border border-line bg-panel/40 p-6 transition-colors hover:border-line-strong"
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent"
                                >
                                    {initials(m.name)}
                                </div>
                                <div class="min-w-0">
                                    <h3
                                        class="truncate text-base font-semibold text-ink"
                                    >
                                        {m.name}
                                    </h3>
                                    {#if m.expertise}
                                        <p class="truncate text-sm text-accent">
                                            {m.expertise}
                                        </p>
                                    {/if}
                                </div>
                            </div>

                            {#if m.bio}
                                <p
                                    class="mt-4 line-clamp-3 text-[15px] text-muted"
                                >
                                    {m.bio}
                                </p>
                            {/if}

                            {#if m.industries.length}
                                <div class="mt-4">
                                    <p
                                        class="text-xs font-medium tracking-wide text-faint uppercase"
                                    >
                                        Focus areas
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        {#each m.industries as ind (ind)}
                                            <span
                                                class="rounded-md border border-line bg-elevated px-2 py-0.5 text-xs text-muted"
                                            >
                                                {ind}
                                            </span>
                                        {/each}
                                    </div>
                                </div>
                            {/if}

                            <dl class="mt-4 flex flex-wrap gap-x-8 gap-y-2">
                                {#if yearsLabel(m.yearsExperience)}
                                    <div>
                                        <dt class="text-xs text-muted">
                                            Experience
                                        </dt>
                                        <dd class="text-sm text-ink">
                                            {yearsLabel(m.yearsExperience)}
                                        </dd>
                                    </div>
                                {/if}
                                {#if m.availability}
                                    <div>
                                        <dt class="text-xs text-muted">
                                            Availability
                                        </dt>
                                        <dd class="text-sm text-ink">
                                            {m.availability}
                                        </dd>
                                    </div>
                                {/if}
                            </dl>

                            <button
                                type="button"
                                onclick={() => choose(m)}
                                disabled={choosing !== null}
                                class={cn(
                                    'mt-5 inline-flex items-center justify-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-60',
                                    focusRing,
                                )}
                            >
                                {#if choosing === m.id}
                                    Pairing…
                                {:else}
                                    <Check class="size-4" strokeWidth={2.25} />
                                    Choose {m.name.split(' ')[0]}
                                {/if}
                            </button>
                        </article>
                    {/each}
                </div>
            {:else}
                <!-- No matches for the current search / filter -->
                <div
                    class="mt-6 flex flex-col items-center justify-center rounded-2xl border border-line bg-panel/40 px-6 py-14 text-center"
                >
                    <h3 class="text-base font-semibold text-ink">
                        No mentors match your search
                    </h3>
                    <p class="mt-1.5 max-w-sm text-[15px] text-muted">
                        Try a different search or focus area.
                    </p>
                    {#if isFiltering}
                        <button
                            type="button"
                            onclick={clearFilters}
                            class={cn(
                                'mt-4 inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-sm text-muted transition-colors hover:text-ink',
                                focusRing,
                            )}
                        >
                            <X class="size-3.5" strokeWidth={1.75} />
                            Clear filters
                        </button>
                    {/if}
                </div>
            {/if}
        {/if}
    </div>
</EntrepreneurLayout>
