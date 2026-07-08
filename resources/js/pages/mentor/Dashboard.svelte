<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight, ListChecks } from '@lucide/svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import BarTrend from '@/components/charts/bar-trend.svelte';
    import MeetingRow from '@/components/mentorship/meeting-row.svelte';
    import ReportSlideOver from '@/components/mentorship/report-slide-over.svelte';
    import RescheduleCard from '@/components/mentorship/reschedule-card.svelte';
    import { DAY_NAMES, meetingTime } from '@/components/mentorship/types';
    import type {
        AttentionReschedule,
        AvailabilitySlot,
        Mentee,
        MissingReport,
        WeekMeeting,
    } from '@/components/mentorship/types';
    import { cn } from '@/lib/utils';

    type Onboarding = {
        total: number;
        completed: number;
        remaining: number;
        isComplete: boolean;
        missing: string[];
    };

    let {
        onboarding,
        attention,
        meetings = [],
        mentees = [],
        availability,
        stats,
        sessions = [],
    }: {
        onboarding: Onboarding;
        attention: {
            reschedules: AttentionReschedule[];
            missingReports: MissingReport[];
        };
        meetings: WeekMeeting[];
        mentees: Mentee[];
        availability: { activeCount: number; slots: AvailabilitySlot[] };
        stats: {
            menteeCount: number;
            completedCount: number;
            hoursMentored: number;
        };
        sessions: { week: string; sessions: number }[];
    } = $props();

    const sessionsSeries = [
        { key: 'sessions', label: 'Sessions', color: 'var(--chart-1)' },
    ];

    const pct = $derived(
        onboarding.total === 0
            ? 0
            : Math.round((onboarding.completed / onboarding.total) * 100),
    );

    const needsAttention = $derived(
        attention.reschedules.length + attention.missingReports.length > 0,
    );

    let reportFor = $state<MissingReport | null>(null);

    // Toast, matching pages/admin/users/Index.svelte exactly:
    let toast = $state<string | null>(null);
    let toastTimer: ReturnType<typeof setTimeout> | undefined;
    function toastMsg(m: string) {
        toast = m;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toast = null), 3400);
    }

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
</script>

<MentorLayout title="Dashboard">
    <div class="mx-auto w-full max-w-7xl px-6 pt-10">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-ink">
                Dashboard
            </h1>
            <p class="mt-1.5 text-[15px] text-muted">
                Your mentorship workspace at a glance.
            </p>
        </div>

        {#if !onboarding.isComplete}
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
                                left to finish setting up your mentor profile.
                            </p>
                        </div>
                    </div>
                    <Link
                        href="/mentor/onboarding"
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
        {/if}
    </div>

    <div class="mx-auto w-full max-w-7xl px-6">
        {#if needsAttention}
            <section class="mt-8" aria-labelledby="attention-heading">
                <h2
                    id="attention-heading"
                    class="text-sm font-semibold text-ink"
                >
                    Needs your attention
                </h2>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    {#each attention.reschedules as reschedule (reschedule.id)}
                        <RescheduleCard
                            {reschedule}
                            onReviewed={(accepted) =>
                                toastMsg(
                                    accepted
                                        ? 'Meeting moved to the new time.'
                                        : 'Reschedule request declined.',
                                )}
                        />
                    {/each}
                    {#each attention.missingReports as missing (missing.meetingId)}
                        <div
                            class="rounded-xl border border-line bg-panel/40 p-5"
                        >
                            <p class="text-sm font-medium text-ink">
                                Your meeting with {missing.menteeName} needs a report
                            </p>
                            <p class="mt-1 text-[13px] text-muted">
                                Held {meetingTime(missing.endedAt)}
                            </p>
                            <button
                                type="button"
                                onclick={() => (reportFor = missing)}
                                class={cn(
                                    'mt-4 inline-flex items-center rounded-lg bg-accent px-3.5 py-2 text-sm font-semibold text-on-accent shadow-btn transition-all hover:bg-accent-strong',
                                    focusRing,
                                )}
                            >
                                Write report
                            </button>
                        </div>
                    {/each}
                </div>
            </section>
        {/if}

        <section class="mt-8" aria-labelledby="week-heading">
            <h2 id="week-heading" class="text-sm font-semibold text-ink">
                This week
            </h2>
            {#if meetings.length}
                <ul
                    class="mt-3 divide-y divide-line rounded-xl border border-line bg-panel/40 px-5"
                >
                    {#each meetings as meeting (meeting.id)}
                        <MeetingRow {meeting} />
                    {/each}
                </ul>
            {:else}
                <p
                    class="mt-3 rounded-xl border border-line bg-panel/40 p-5 text-sm text-muted"
                >
                    No meetings booked this week.
                </p>
            {/if}
        </section>

        <section class="mt-8" aria-labelledby="sessions-heading">
            <h2 id="sessions-heading" class="text-sm font-semibold text-ink">
                Sessions
            </h2>
            <p class="mt-0.5 text-xs text-muted">
                Completed sessions over the last 8 weeks
            </p>
            <div class="mt-4 rounded-2xl border border-line bg-panel/40 p-6">
                <BarTrend data={sessions} x="week" series={sessionsSeries} />
            </div>
        </section>

        <section class="mt-8" aria-labelledby="mentees-heading">
            <h2
                id="mentees-heading"
                class="flex items-center gap-2 text-sm font-semibold text-ink"
            >
                Your mentees
                {#if stats.menteeCount}
                    <span
                        class="rounded-full bg-elevated px-1.5 py-0.5 text-[11px] font-semibold text-faint tabular-nums"
                    >
                        {stats.menteeCount}
                    </span>
                {/if}
            </h2>
            {#if mentees.length}
                <ul
                    class="mt-3 divide-y divide-line rounded-xl border border-line bg-panel/40 px-5"
                >
                    {#each mentees as mentee (mentee.pairingId)}
                        <li class="flex items-center gap-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-ink"
                                >
                                    {mentee.name}
                                </p>
                                {#if mentee.company}
                                    <p
                                        class="mt-0.5 truncate text-[13px] text-muted"
                                    >
                                        {mentee.company}
                                    </p>
                                {/if}
                            </div>
                            <p class="shrink-0 text-[13px] text-muted">
                                {mentee.nextMeetingAt
                                    ? `Next ${meetingTime(mentee.nextMeetingAt)}`
                                    : mentee.lastMeetingAt
                                      ? `Last met ${meetingTime(mentee.lastMeetingAt)}`
                                      : 'No meetings yet'}
                            </p>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p
                    class="mt-3 rounded-xl border border-line bg-panel/40 p-5 text-sm text-muted"
                >
                    No mentees yet. Entrepreneurs choose their mentor when they
                    join, and new mentees appear here automatically.
                </p>
            {/if}
        </section>

        <section class="mt-8 mb-12" aria-labelledby="availability-heading">
            <h2
                id="availability-heading"
                class="text-sm font-semibold text-ink"
            >
                Availability
            </h2>
            {#if availability.activeCount}
                <ul class="mt-3 flex flex-wrap gap-2">
                    {#each availability.slots as slot (slot.id)}
                        <li
                            class="rounded-full border border-line bg-elevated px-3 py-1.5 text-xs text-muted"
                        >
                            <span class="font-medium text-ink"
                                >{DAY_NAMES[slot.dayOfWeek]}</span
                            >
                            {slot.startTime} to {slot.endTime}
                        </li>
                    {/each}
                </ul>
            {:else}
                <p
                    class="mt-3 rounded-xl border border-line bg-panel/40 p-5 text-sm text-muted"
                >
                    No availability set yet. Availability management is coming
                    soon; an admin can help set your slots in the meantime.
                </p>
            {/if}
        </section>
    </div>

    {#if reportFor}
        <ReportSlideOver
            meeting={reportFor}
            onClose={() => (reportFor = null)}
            onSubmitted={() => {
                reportFor = null;
                toastMsg('Report submitted.');
            }}
        />
    {/if}

    {#if toast}
        <div class="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2">
            <div
                role="status"
                class="flex items-center gap-2.5 rounded-lg border border-line-strong bg-elevated px-4 py-2.5 text-sm text-ink shadow-card"
            >
                {toast}
            </div>
        </div>
    {/if}
</MentorLayout>
