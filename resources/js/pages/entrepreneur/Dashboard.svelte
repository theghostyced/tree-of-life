<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight, ListChecks, BadgeCheck } from '@lucide/svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import { Toaster } from '@/components/ui/sonner';
    import { YEAR_RANGES } from '@/lib/onboarding-options';
    import { cn } from '@/lib/utils';

    type Onboarding = {
        total: number;
        completed: number;
        remaining: number;
        isComplete: boolean;
        missing: string[];
    };
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
        onboarding,
        mentor = null,
    }: {
        onboarding: Onboarding;
        mentor: Mentor | null;
    } = $props();

    const pct = $derived(
        onboarding.total === 0
            ? 0
            : Math.round((onboarding.completed / onboarding.total) * 100),
    );

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
</script>

<EntrepreneurLayout title="Dashboard">
    <Toaster position="top-center" />

    <div class="mx-auto w-full max-w-5xl px-6 py-8">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Dashboard
            </h1>
            <p class="mt-1.5 text-[15px] text-muted">
                Your mentorship workspace at a glance.
            </p>
        </div>

        {#if mentor}
            <!-- ── Paired ──────────────────────────────────────────────── -->
            <section class="mt-8">
                <div
                    class="rounded-2xl border border-line bg-panel/40 p-6 sm:p-7"
                >
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <div
                            class="flex size-14 shrink-0 items-center justify-center rounded-full bg-accent-soft text-lg font-semibold text-accent"
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
                                class="mt-2 text-xl font-semibold tracking-tight text-ink"
                            >
                                {mentor.name}
                            </h2>
                            {#if mentor.expertise}
                                <p class="text-[15px] text-accent">
                                    {mentor.expertise}
                                </p>
                            {/if}
                            {#if mentor.bio}
                                <p
                                    class="mt-3 max-w-2xl text-[15px] text-muted"
                                >
                                    {mentor.bio}
                                </p>
                            {/if}
                            <dl class="mt-4 flex flex-wrap gap-x-10 gap-y-3">
                                {#if yearsLabel(mentor.yearsExperience)}
                                    <div>
                                        <dt class="text-xs text-muted">
                                            Experience
                                        </dt>
                                        <dd class="text-[15px] text-ink">
                                            {yearsLabel(mentor.yearsExperience)}
                                        </dd>
                                    </div>
                                {/if}
                                {#if mentor.availability}
                                    <div>
                                        <dt class="text-xs text-muted">
                                            Availability
                                        </dt>
                                        <dd class="text-[15px] text-ink">
                                            {mentor.availability}
                                        </dd>
                                    </div>
                                {/if}
                            </dl>
                        </div>
                    </div>
                </div>

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
                            Scheduling meetings with {mentor.name.split(' ')[0]}
                            and reading your meeting reports arrives here next.
                        </p>
                    </div>
                    <img
                        src="/images/illustrations/social-talk.svg"
                        alt=""
                        class="h-28 w-auto shrink-0 self-center opacity-90 sm:h-36"
                    />
                </div>
            </section>
        {:else if !onboarding.isComplete}
            <!-- ── Onboarding not finished ─────────────────────────────── -->
            <div
                class="mt-8 rounded-2xl border border-line bg-panel/50 p-6 sm:p-7"
            >
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-line bg-accent-soft text-accent"
                        >
                            <ListChecks class="size-5" strokeWidth={1.75} />
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-ink">
                                Complete your profile
                            </p>
                            <p class="mt-1 text-[15px] text-muted">
                                {onboarding.remaining} of {onboarding.total} items
                                left — then you can choose your mentor.
                            </p>
                        </div>
                    </div>
                    <Link
                        href="/entrepreneur/onboarding"
                        class={cn(
                            'inline-flex shrink-0 items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong',
                            focusRing,
                        )}
                    >
                        Continue onboarding
                        <ArrowRight class="size-4" strokeWidth={2} />
                    </Link>
                </div>
                <div class="mt-5 flex items-center gap-3">
                    <div
                        class="h-1.5 flex-1 overflow-hidden rounded-full bg-elevated"
                    >
                        <div
                            class="h-full rounded-full bg-accent transition-all"
                            style="width: {pct}%"
                        ></div>
                    </div>
                    <span class="shrink-0 text-sm font-medium text-muted"
                        >{pct}%</span
                    >
                </div>
            </div>
        {:else}
            <!-- ── Onboarded, no mentor yet: send them to the directory ── -->
            <div
                class="mt-8 flex flex-col gap-6 rounded-2xl border border-line bg-panel/50 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-7"
            >
                <div class="max-w-md">
                    <h2 class="text-xl font-semibold tracking-tight text-ink">
                        Choose your mentor
                    </h2>
                    <p class="mt-2 text-[15px] text-muted">
                        Your profile is complete. Browse the mentors in the
                        programme and pick the one who best fits your business —
                        you'll pair with one.
                    </p>
                    <Link
                        href="/entrepreneur/mentors"
                        class={cn(
                            'mt-5 inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong',
                            focusRing,
                        )}
                    >
                        Find a mentor
                        <ArrowRight class="size-4" strokeWidth={2} />
                    </Link>
                </div>
                <img
                    src="/images/illustrations/social-talk.svg"
                    alt=""
                    class="h-32 w-auto shrink-0 self-center opacity-90 sm:h-44"
                />
            </div>
        {/if}
    </div>
</EntrepreneurLayout>
