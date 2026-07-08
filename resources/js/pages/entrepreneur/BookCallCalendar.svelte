<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { ChevronLeft, ChevronRight, Check } from '@lucide/svelte';
    import { toast } from '@/components/ui/sonner';
    import { cn } from '@/lib/utils';

    type Occurrence = {
        slotId: number;
        startsAt: number;
        endsAt: number;
        sessionType: 'virtual' | 'in_person';
        location: string | null;
        meetingLink: string | null;
    };
    type Mentor = { pairingId: number; mentorId: number; name: string };

    let {
        mentors,
        availability,
        initialPairingId = null,
    }: {
        mentors: Mentor[];
        availability: Record<number, Occurrence[]>;
        initialPairingId?: number | null;
    } = $props();

    const HORIZON_WEEKS = 8;

    function mondayOf(d: Date): Date {
        const x = new Date(d);
        const off = (x.getDay() + 6) % 7;
        x.setDate(x.getDate() - off);
        x.setHours(0, 0, 0, 0);
        return x;
    }
    function addDays(d: Date, n: number): Date {
        const x = new Date(d);
        x.setDate(x.getDate() + n);
        return x;
    }
    function sameDay(ms: number, d: Date): boolean {
        const a = new Date(ms);
        return (
            a.getFullYear() === d.getFullYear() &&
            a.getMonth() === d.getMonth() &&
            a.getDate() === d.getDate()
        );
    }

    const thisMonday = mondayOf(new Date());
    const maxMonday = addDays(thisMonday, 7 * (HORIZON_WEEKS - 1));

    let selectedPairing = $state<number | null>(
        initialPairingId &&
            mentors.some((m) => m.pairingId === initialPairingId)
            ? initialPairingId
            : (mentors[0]?.pairingId ?? null),
    );
    let weekStart = $state(thisMonday);
    let selected = $state<Occurrence | null>(null);
    let booking = $state(false);

    const mentorName = $derived(
        mentors.find((m) => m.pairingId === selectedPairing)?.name ?? '',
    );
    const occ = $derived(
        selectedPairing !== null ? (availability[selectedPairing] ?? []) : [],
    );
    const totalOpen = $derived(occ.length);
    const days = $derived(
        Array.from({ length: 7 }, (_, i) => addDays(weekStart, i)),
    );
    const canPrev = $derived(weekStart.getTime() > thisMonday.getTime());
    const canNext = $derived(weekStart.getTime() < maxMonday.getTime());

    const weekLabel = $derived.by(() => {
        const end = addDays(weekStart, 6);
        const mon = (d: Date) =>
            d.toLocaleDateString('en-US', { month: 'short' });
        return weekStart.getMonth() === end.getMonth()
            ? `${mon(weekStart)} ${weekStart.getDate()} – ${end.getDate()}`
            : `${mon(weekStart)} ${weekStart.getDate()} – ${mon(end)} ${end.getDate()}`;
    });

    const fmtTime = (ms: number) =>
        new Date(ms).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        });
    const fmtDayFull = (ms: number) =>
        new Date(ms).toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'short',
            day: 'numeric',
        });

    function timesFor(day: Date): Occurrence[] {
        return occ
            .filter((o) => sameDay(o.startsAt, day))
            .sort((a, b) => a.startsAt - b.startsAt);
    }
    function isSelected(o: Occurrence): boolean {
        return (
            selected !== null &&
            selected.slotId === o.slotId &&
            selected.startsAt === o.startsAt
        );
    }
    function selectMentor(pairingId: number) {
        selectedPairing = pairingId;
        selected = null;
        weekStart = thisMonday;
    }
    function prevWeek() {
        if (canPrev) {
            weekStart = addDays(weekStart, -7);
            selected = null;
        }
    }
    function nextWeek() {
        if (canNext) {
            weekStart = addDays(weekStart, 7);
            selected = null;
        }
    }
    function pick(o: Occurrence) {
        selected = isSelected(o) ? null : o;
    }
    function confirm() {
        if (!selected) return;
        const o = selected;
        router.post(
            '/entrepreneur/meetings',
            { slot_id: o.slotId, starts_at: o.startsAt },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => (booking = true),
                onFinish: () => (booking = false),
                onSuccess: () => {
                    toast.success('Meeting booked.');
                    selected = null;
                },
                onError: () =>
                    toast.error("That time isn't available anymore."),
            },
        );
    }
</script>

<section>
    <div class="flex items-baseline justify-between gap-3">
        <h2 class="text-lg font-semibold text-ink">Book a call</h2>
        {#if selectedPairing !== null && totalOpen > 0}
            <span class="text-xs text-faint tabular-nums">
                {totalOpen} open {totalOpen === 1 ? 'slot' : 'slots'}
            </span>
        {/if}
    </div>

    {#if mentors.length === 0}
        <p
            class="mt-4 rounded-xl border border-line bg-panel/40 px-4 py-6 text-center text-sm text-muted"
        >
            You're not paired with a mentor yet.
        </p>
    {:else}
        {#if mentors.length > 1}
            <div class="mt-3 flex flex-wrap gap-1.5">
                {#each mentors as m (m.pairingId)}
                    <button
                        type="button"
                        onclick={() => selectMentor(m.pairingId)}
                        class={cn(
                            'rounded-full px-3 py-1 text-xs font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-accent/50',
                            selectedPairing === m.pairingId
                                ? 'bg-accent text-on-accent'
                                : 'bg-elevated text-muted hover:text-ink',
                        )}
                    >
                        {m.name}
                    </button>
                {/each}
            </div>
        {/if}

        {#if totalOpen === 0}
            <p
                class="mt-4 rounded-xl border border-line bg-panel/40 px-4 py-6 text-center text-sm text-muted"
            >
                {mentorName} hasn't opened any times yet. Check back soon.
            </p>
        {:else}
            <div class="mt-4 flex items-center justify-between">
                <h3 class="text-sm font-medium text-muted tabular-nums">
                    {weekLabel}
                </h3>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        onclick={prevWeek}
                        disabled={!canPrev}
                        aria-label="Previous week"
                        class="flex size-8 items-center justify-center rounded-lg border border-line text-muted outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/40 disabled:opacity-40 disabled:hover:bg-transparent"
                    >
                        <ChevronLeft class="size-4" strokeWidth={1.75} />
                    </button>
                    <button
                        type="button"
                        onclick={nextWeek}
                        disabled={!canNext}
                        aria-label="Next week"
                        class="flex size-8 items-center justify-center rounded-lg border border-line text-muted outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/40 disabled:opacity-40 disabled:hover:bg-transparent"
                    >
                        <ChevronRight class="size-4" strokeWidth={1.75} />
                    </button>
                </div>
            </div>

            <div
                class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7"
            >
                {#each days as d (d.getTime())}
                    {@const times = timesFor(d)}
                    {@const today = sameDay(Date.now(), d)}
                    <div
                        class="rounded-xl border border-line bg-panel/40 p-2.5"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium {today
                                    ? 'text-accent'
                                    : 'text-muted'}"
                            >
                                {d.toLocaleDateString('en-US', {
                                    weekday: 'short',
                                })}
                            </span>
                            <span
                                class="flex size-6 items-center justify-center rounded-full text-xs font-semibold tabular-nums {today
                                    ? 'bg-accent text-on-accent'
                                    : 'text-ink'}"
                            >
                                {d.getDate()}
                            </span>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            {#each times as o (o.startsAt)}
                                <button
                                    type="button"
                                    onclick={() => pick(o)}
                                    class={cn(
                                        'rounded-lg border px-2 py-1.5 text-xs font-medium tabular-nums outline-none transition-colors focus-visible:ring-2 focus-visible:ring-accent/50',
                                        isSelected(o)
                                            ? 'border-accent bg-accent text-on-accent'
                                            : 'border-line bg-surface text-ink hover:border-accent hover:text-accent',
                                    )}
                                >
                                    {fmtTime(o.startsAt)}
                                </button>
                            {:else}
                                <span
                                    class="py-1.5 text-center text-xs text-faint"
                                    >—</span
                                >
                            {/each}
                        </div>
                    </div>
                {/each}
            </div>

            {#if selected}
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-accent/40 bg-accent-soft/50 px-4 py-3"
                >
                    <p class="text-sm text-ink">
                        Book
                        <span class="font-semibold"
                            >{fmtDayFull(selected.startsAt)} at {fmtTime(
                                selected.startsAt,
                            )}</span
                        >
                        with {mentorName}?
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick={() => (selected = null)}
                            class="rounded-lg px-3 py-1.5 text-sm font-medium text-muted outline-none transition-colors hover:text-ink focus-visible:ring-2 focus-visible:ring-accent/40"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onclick={confirm}
                            disabled={booking}
                            class="inline-flex items-center gap-1.5 rounded-lg bg-accent px-3.5 py-1.5 text-sm font-semibold text-on-accent shadow-btn outline-none transition-all hover:-translate-y-px hover:bg-accent-strong focus-visible:ring-2 focus-visible:ring-accent/60 disabled:opacity-60"
                        >
                            <Check class="size-4" strokeWidth={2.25} />
                            {booking ? 'Booking…' : 'Confirm'}
                        </button>
                    </div>
                </div>
            {/if}
        {/if}
    {/if}
</section>
