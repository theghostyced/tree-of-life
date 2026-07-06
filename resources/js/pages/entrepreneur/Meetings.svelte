<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Video, MapPin, Calendar, Plus } from '@lucide/svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import { Toaster, toast } from '@/components/ui/sonner';
    import { cn } from '@/lib/utils';

    type Meeting = {
        id: number;
        counterpartName: string;
        startsAt: number;
        endsAt: number;
        sessionType: 'virtual' | 'in_person';
        location: string | null;
        meetingLink: string | null;
        agenda: string | null;
        status: 'confirmed' | 'completed' | 'cancelled';
        reportSummary: string | null;
    };
    type Bookable = {
        slotId: number;
        mentorName: string;
        startsAt: number;
        sessionType: 'virtual' | 'in_person';
    };

    let {
        upcoming = [],
        past = [],
        bookable = [],
    }: {
        upcoming: Meeting[];
        past: Meeting[];
        bookable: Bookable[];
    } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const fmtDate = (ms: number) =>
        new Date(ms).toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
        });
    const fmtTime = (ms: number) =>
        new Date(ms).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        });

    const STATUS: Record<string, { label: string; cls: string }> = {
        confirmed: {
            label: 'Confirmed',
            cls: 'bg-positive/12 text-positive-strong',
        },
        completed: { label: 'Completed', cls: 'bg-elevated text-muted' },
        cancelled: {
            label: 'Cancelled',
            cls: 'bg-danger/12 text-danger-strong',
        },
    };

    let booking = $state<number | null>(null);
    function book(slotId: number) {
        router.post(
            '/entrepreneur/meetings',
            { slot_id: slotId },
            {
                preserveScroll: true,
                onStart: () => (booking = slotId),
                onFinish: () => (booking = null),
                onSuccess: () => toast.success('Meeting booked.'),
                onError: () =>
                    toast.error("That time isn't available anymore."),
            },
        );
    }
</script>

<EntrepreneurLayout title="Meetings">
    <Toaster position="top-center" />

    <div class="mx-auto w-full max-w-7xl px-6 py-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Meetings
            </h1>
            <p class="mt-1.5 text-[15px] text-muted">
                Book time with your mentors and keep track of your sessions.
            </p>
        </div>

        {#snippet meetingRow(m: Meeting)}
            <div class="rounded-xl border border-line bg-panel/40 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">
                            {m.counterpartName}
                        </p>
                        <p class="mt-0.5 text-sm text-muted tabular-nums">
                            {fmtDate(m.startsAt)} · {fmtTime(m.startsAt)} – {fmtTime(
                                m.endsAt,
                            )}
                        </p>
                    </div>
                    <span
                        class={cn(
                            'rounded-full px-2 py-0.5 text-[11px] font-medium',
                            STATUS[m.status].cls,
                        )}
                    >
                        {STATUS[m.status].label}
                    </span>
                </div>
                <div
                    class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted"
                >
                    {#if m.sessionType === 'virtual'}
                        <span class="inline-flex items-center gap-1.5">
                            <Video class="size-3.5" strokeWidth={1.75} />
                            Virtual
                        </span>
                        {#if m.meetingLink && m.status === 'confirmed'}
                            <a
                                href={m.meetingLink}
                                target="_blank"
                                rel="noopener noreferrer"
                                class={cn(
                                    'font-medium text-accent hover:text-accent-strong',
                                    focusRing,
                                )}
                            >
                                Join link
                            </a>
                        {/if}
                    {:else}
                        <span class="inline-flex items-center gap-1.5">
                            <MapPin class="size-3.5" strokeWidth={1.75} />
                            {m.location ?? 'In person'}
                        </span>
                    {/if}
                </div>
                {#if m.reportSummary}
                    <div class="mt-3 border-t border-line pt-3">
                        <p
                            class="text-[11px] font-medium tracking-wide text-faint uppercase"
                        >
                            Meeting report
                        </p>
                        <p class="mt-1 text-sm text-muted">{m.reportSummary}</p>
                    </div>
                {/if}
            </div>
        {/snippet}

        <!-- Book -->
        {#if bookable.length}
            <section class="mt-8">
                <h2 class="text-lg font-semibold text-ink">Book a session</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    {#each bookable as b (b.slotId)}
                        <div
                            class="flex items-center gap-3 rounded-xl border border-line bg-panel/40 p-4"
                        >
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent"
                            >
                                {#if b.sessionType === 'virtual'}
                                    <Video class="size-4" strokeWidth={1.75} />
                                {:else}
                                    <MapPin class="size-4" strokeWidth={1.75} />
                                {/if}
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-medium text-ink"
                                >
                                    {b.mentorName}
                                </p>
                                <p class="text-xs text-muted tabular-nums">
                                    {fmtDate(b.startsAt)} · {fmtTime(
                                        b.startsAt,
                                    )}
                                </p>
                            </div>
                            <button
                                type="button"
                                onclick={() => book(b.slotId)}
                                disabled={booking !== null}
                                class={cn(
                                    'ml-auto inline-flex items-center gap-1 rounded-lg bg-accent px-3 py-1.5 text-xs font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong disabled:opacity-60',
                                    focusRing,
                                )}
                            >
                                <Plus class="size-3.5" strokeWidth={2.25} />
                                Book
                            </button>
                        </div>
                    {/each}
                </div>
            </section>
        {/if}

        <!-- Upcoming -->
        <section class="mt-8">
            <h2 class="text-lg font-semibold text-ink">Upcoming</h2>
            {#if upcoming.length}
                <div class="mt-4 space-y-3">
                    {#each upcoming as m (m.id)}
                        {@render meetingRow(m)}
                    {/each}
                </div>
            {:else}
                <div
                    class="mt-4 flex flex-col items-center justify-center rounded-2xl border border-line bg-panel/40 px-6 py-12 text-center"
                >
                    <div
                        class="mb-4 flex size-12 items-center justify-center rounded-full bg-accent-soft text-accent"
                    >
                        <Calendar class="size-6" strokeWidth={1.75} />
                    </div>
                    <h3 class="text-base font-semibold text-ink">
                        No upcoming meetings
                    </h3>
                    <p class="mt-1.5 max-w-sm text-[15px] text-muted">
                        {bookable.length
                            ? 'Book a session above to get started.'
                            : "Your mentors haven't opened any times yet. Check back soon."}
                    </p>
                </div>
            {/if}
        </section>

        <!-- Past -->
        {#if past.length}
            <section class="mt-8">
                <h2 class="text-lg font-semibold text-ink">Past</h2>
                <div class="mt-4 space-y-3">
                    {#each past as m (m.id)}
                        {@render meetingRow(m)}
                    {/each}
                </div>
            </section>
        {/if}
    </div>
</EntrepreneurLayout>
