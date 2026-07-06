<script lang="ts">
    import { router, Link } from '@inertiajs/svelte';
    import {
        Search,
        Check,
        X,
        ChevronLeft,
        ChevronRight,
    } from '@lucide/svelte';
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
    type Paginated<T> = {
        data: T[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };

    let {
        mentors,
        yourMentors = [],
        focusAreas = [],
        filters,
    }: {
        mentors: Paginated<Mentor>;
        yourMentors: Mentor[];
        focusAreas: string[];
        filters: { search: string; focus: string };
    } = $props();

    // ── Server-driven search + filter + pagination ──────────────────────
    let search = $state(filters.search ?? '');
    let focus = $state(filters.focus || 'all');
    let loading = $state(false);

    const focusLabel = $derived(focus === 'all' ? 'All focus areas' : focus);
    const isFiltering = $derived(
        (filters.search ?? '') !== '' || (filters.focus ?? '') !== '',
    );

    function reload() {
        router.get(
            '/entrepreneur/mentors',
            {
                search: search.trim() || undefined,
                focus: focus === 'all' ? undefined : focus,
            },
            {
                only: ['mentors', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (loading = true),
                onFinish: () => (loading = false),
            },
        );
    }

    let searchTimer: ReturnType<typeof setTimeout> | undefined;
    function onSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(reload, 300);
    }

    function setFocus(value: string | undefined) {
        focus = value ?? 'all';
        reload();
    }

    function clearFilters() {
        search = '';
        focus = 'all';
        reload();
    }

    function goTo(url: string | null) {
        if (!url) {
            return;
        }

        router.get(
            url,
            {},
            {
                only: ['mentors', 'filters'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => (loading = true),
                onFinish: () => (loading = false),
            },
        );
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
                    toast.success(`You're now working with ${m.name}.`),
                onError: () =>
                    toast.error('That mentor is no longer available.'),
            },
        );
    }
</script>

<EntrepreneurLayout title="Mentors">
    <Toaster position="top-center" />

    <div class="mx-auto w-full max-w-7xl px-6 py-8">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Mentors
            </h1>
            <p class="mt-1.5 max-w-2xl text-[15px] text-muted">
                The mentors you're working with, and the rest of the programme
                to explore. You can work with more than one.
            </p>
        </div>

        {#if yourMentors.length}
            <section class="mt-8">
                <h2 class="text-lg font-semibold text-ink">Your mentors</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {#each yourMentors as m (m.id)}
                        <Link
                            href={`/entrepreneur/mentors/${m.id}`}
                            class="group flex items-center gap-3 rounded-xl border border-line bg-panel/40 p-4 transition-colors hover:border-line-strong"
                        >
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent"
                            >
                                {initials(m.name)}
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-ink"
                                >
                                    {m.name}
                                </p>
                                {#if m.expertise}
                                    <p
                                        class="truncate text-xs text-muted capitalize"
                                    >
                                        {m.expertise}
                                    </p>
                                {/if}
                            </div>
                            <ChevronRight
                                class="ml-auto size-4 shrink-0 text-faint transition-colors group-hover:text-muted"
                                strokeWidth={1.75}
                            />
                        </Link>
                    {/each}
                </div>
            </section>
        {/if}

        <section class="mt-8">
            <h2 class="text-lg font-semibold text-ink">
                {yourMentors.length ? 'Add another mentor' : 'Find a mentor'}
            </h2>

            {#if mentors.total === 0 && !isFiltering}
                <!-- No mentors left to add -->
                <div
                    class="mt-4 flex flex-col items-center justify-center rounded-2xl border border-line bg-panel/40 px-6 py-16 text-center"
                >
                    <img
                        src="/images/illustrations/weather-plants.svg"
                        alt=""
                        class="mb-6 size-40 opacity-90 sm:size-48"
                    />
                    <h3 class="text-lg font-semibold text-ink">
                        {yourMentors.length
                            ? "That's everyone for now"
                            : 'No mentors available yet'}
                    </h3>
                    <p class="mt-2 max-w-sm text-[15px] text-muted">
                        {yourMentors.length
                            ? "You're working with every mentor currently in the programme. Check back as more join."
                            : "Mentors are still setting up their profiles. Check back soon — we'll have people ready to guide you."}
                    </p>
                </div>
            {:else}
                <!-- Toolbar: search + focus-area filter -->
                <div
                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center"
                >
                    <div class="relative flex-1 sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-faint"
                            strokeWidth={1.75}
                        />
                        <input
                            type="search"
                            bind:value={search}
                            oninput={onSearchInput}
                            placeholder="Search by name or expertise"
                            class={cn(
                                'h-9 w-full rounded-lg border border-line bg-surface pr-3 pl-9 text-sm text-ink placeholder:text-faint',
                                'outline-none focus-visible:border-accent focus-visible:ring-3 focus-visible:ring-accent/30',
                            )}
                        />
                    </div>

                    {#if focusAreas.length}
                        <Select.Root
                            type="single"
                            value={focus}
                            onValueChange={setFocus}
                        >
                            <Select.Trigger
                                size="sm"
                                class="w-full sm:w-56"
                                aria-label="Filter by focus area"
                            >
                                {focusLabel}
                            </Select.Trigger>
                            <Select.Content>
                                <Select.Item
                                    value="all"
                                    label="All focus areas"
                                >
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
                        {mentors.total}
                        {mentors.total === 1 ? 'mentor' : 'mentors'}
                    </span>
                </div>

                {#if mentors.data.length}
                    <div
                        class={cn(
                            'mt-6 grid gap-5 transition-opacity sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
                            loading && 'pointer-events-none opacity-60',
                        )}
                    >
                        {#each mentors.data as m (m.id)}
                            <article
                                class="flex h-full flex-col rounded-2xl border border-line bg-panel/40 p-6 transition-colors hover:border-line-strong"
                            >
                                <div class="flex flex-1 flex-col">
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
                                                <p
                                                    class="truncate text-sm text-accent capitalize"
                                                >
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
                                            <div
                                                class="mt-2 flex flex-wrap gap-1.5"
                                            >
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

                                    <dl
                                        class="mt-4 flex flex-wrap gap-x-8 gap-y-2"
                                    >
                                        {#if yearsLabel(m.yearsExperience)}
                                            <div>
                                                <dt class="text-xs text-muted">
                                                    Experience
                                                </dt>
                                                <dd class="text-sm text-ink">
                                                    {yearsLabel(
                                                        m.yearsExperience,
                                                    )}
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
                                </div>

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
                                        <Check
                                            class="size-4"
                                            strokeWidth={2.25}
                                        />
                                        Choose {m.name.split(' ')[0]}
                                    {/if}
                                </button>
                            </article>
                        {/each}
                    </div>

                    {#if mentors.last_page > 1}
                        <div
                            class="mt-8 flex flex-col items-center justify-between gap-3 sm:flex-row"
                        >
                            <p class="text-sm text-muted">
                                Showing {mentors.from}–{mentors.to} of {mentors.total}
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    onclick={() => goTo(mentors.prev_page_url)}
                                    disabled={!mentors.prev_page_url}
                                    class={cn(
                                        'inline-flex items-center gap-1 rounded-lg border border-line px-3 py-1.5 text-sm text-muted transition-colors hover:text-ink disabled:cursor-not-allowed disabled:opacity-40',
                                        focusRing,
                                    )}
                                >
                                    <ChevronLeft
                                        class="size-4"
                                        strokeWidth={1.75}
                                    />
                                    Previous
                                </button>
                                <span class="px-1 text-sm text-muted">
                                    Page {mentors.current_page} of {mentors.last_page}
                                </span>
                                <button
                                    type="button"
                                    onclick={() => goTo(mentors.next_page_url)}
                                    disabled={!mentors.next_page_url}
                                    class={cn(
                                        'inline-flex items-center gap-1 rounded-lg border border-line px-3 py-1.5 text-sm text-muted transition-colors hover:text-ink disabled:cursor-not-allowed disabled:opacity-40',
                                        focusRing,
                                    )}
                                >
                                    Next
                                    <ChevronRight
                                        class="size-4"
                                        strokeWidth={1.75}
                                    />
                                </button>
                            </div>
                        </div>
                    {/if}
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
                    </div>
                {/if}
            {/if}
        </section>
    </div>
</EntrepreneurLayout>
