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

    const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    function firstOfMonth(v: number | Date): Date {
        const d = new Date(v);
        return new Date(d.getFullYear(), d.getMonth(), 1);
    }
    function startOfDay(v: number | Date): Date {
        const d = new Date(v);
        d.setHours(0, 0, 0, 0);
        return d;
    }
    function addDays(d: Date, n: number): Date {
        const x = new Date(d);
        x.setDate(x.getDate() + n);
        return x;
    }
    function addMonths(d: Date, n: number): Date {
        return new Date(d.getFullYear(), d.getMonth() + n, 1);
    }
    function sameDay(ms: number, d: Date): boolean {
        const a = new Date(ms);
        return (
            a.getFullYear() === d.getFullYear() &&
            a.getMonth() === d.getMonth() &&
            a.getDate() === d.getDate()
        );
    }

    const resolvedInitial =
        initialPairingId &&
        mentors.some((m) => m.pairingId === initialPairingId)
            ? initialPairingId
            : (mentors[0]?.pairingId ?? null);
    const initialOcc =
        resolvedInitial !== null ? (availability[resolvedInitial] ?? []) : [];

    let selectedPairing = $state<number | null>(resolvedInitial);
    let currentMonth = $state(
        firstOfMonth(initialOcc[0]?.startsAt ?? Date.now()),
    );
    let selectedDay = $state<Date | null>(
        initialOcc.length ? startOfDay(initialOcc[0].startsAt) : null,
    );
    let selected = $state<Occurrence | null>(null);
    let booking = $state(false);

    const thisMonth = firstOfMonth(Date.now());

    const mentorName = $derived(
        mentors.find((m) => m.pairingId === selectedPairing)?.name ?? '',
    );
    const occ = $derived(
        selectedPairing !== null ? (availability[selectedPairing] ?? []) : [],
    );
    const totalOpen = $derived(occ.length);
    const maxMonth = $derived(
        occ.length ? firstOfMonth(occ[occ.length - 1].startsAt) : thisMonth,
    );
    const canPrev = $derived(currentMonth.getTime() > thisMonth.getTime());
    const canNext = $derived(currentMonth.getTime() < maxMonth.getTime());
    const monthLabel = $derived(
        currentMonth.toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric',
        }),
    );

    // Days (this month) that have at least one open slot, for the dot markers.
    const openDays = $derived(
        new Set(occ.map((o) => startOfDay(o.startsAt).getTime())),
    );

    const cells = $derived.by(() => {
        const y = currentMonth.getFullYear();
        const m = currentMonth.getMonth();
        const first = new Date(y, m, 1);
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        const lead = (first.getDay() + 6) % 7;
        const gridStart = addDays(first, -lead);
        const total = Math.ceil((lead + daysInMonth) / 7) * 7;
        return Array.from({ length: total }, (_, i) => addDays(gridStart, i));
    });

    const fmtTime = (ms: number) =>
        new Date(ms).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        });
    const fmtDayFull = (v: number | Date) =>
        new Date(v).toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'short',
            day: 'numeric',
        });

    function timesFor(day: Date): Occurrence[] {
        return occ
            .filter((o) => sameDay(o.startsAt, day))
            .sort((a, b) => a.startsAt - b.startsAt);
    }
    const selectedTimes = $derived(selectedDay ? timesFor(selectedDay) : []);

    function isSelected(o: Occurrence): boolean {
        return (
            selected !== null &&
            selected.slotId === o.slotId &&
            selected.startsAt === o.startsAt
        );
    }
    function selectMentor(pairingId: number) {
        selectedPairing = pairingId;
        const list = availability[pairingId] ?? [];
        currentMonth = firstOfMonth(list[0]?.startsAt ?? Date.now());
        selectedDay = list.length ? startOfDay(list[0].startsAt) : null;
        selected = null;
    }
    function prevMonth() {
        if (canPrev) {
            currentMonth = addMonths(currentMonth, -1);
            selectedDay = null;
            selected = null;
        }
    }
    function nextMonth() {
        if (canNext) {
            currentMonth = addMonths(currentMonth, 1);
            selectedDay = null;
            selected = null;
        }
    }
    function selectDay(d: Date) {
        selectedDay = startOfDay(d);
        selected = null;
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
            <div class="mt-4 flex flex-col gap-6 md:flex-row md:gap-8">
                <!-- Month grid -->
                <div class="w-full md:w-[320px] md:shrink-0">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-ink">
                            {monthLabel}
                        </h3>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                onclick={prevMonth}
                                disabled={!canPrev}
                                aria-label="Previous month"
                                class="flex size-8 items-center justify-center rounded-lg border border-line text-muted outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/40 disabled:opacity-40 disabled:hover:bg-transparent"
                            >
                                <ChevronLeft
                                    class="size-4"
                                    strokeWidth={1.75}
                                />
                            </button>
                            <button
                                type="button"
                                onclick={nextMonth}
                                disabled={!canNext}
                                aria-label="Next month"
                                class="flex size-8 items-center justify-center rounded-lg border border-line text-muted outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/40 disabled:opacity-40 disabled:hover:bg-transparent"
                            >
                                <ChevronRight
                                    class="size-4"
                                    strokeWidth={1.75}
                                />
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-7 gap-1">
                        {#each WEEKDAYS as w (w)}
                            <div
                                class="pb-1 text-center text-[11px] font-medium text-faint"
                            >
                                {w}
                            </div>
                        {/each}
                        {#each cells as d (d.getTime())}
                            {@const inMonth =
                                d.getMonth() === currentMonth.getMonth()}
                            {@const has = openDays.has(startOfDay(d).getTime())}
                            {@const today = sameDay(Date.now(), d)}
                            {@const isSel =
                                selectedDay !== null &&
                                sameDay(selectedDay.getTime(), d)}
                            <button
                                type="button"
                                disabled={!inMonth || !has}
                                onclick={() => selectDay(d)}
                                class={cn(
                                    'relative flex aspect-square items-center justify-center rounded-lg text-sm tabular-nums outline-none transition-colors focus-visible:ring-2 focus-visible:ring-accent/50',
                                    !inMonth && 'text-faint/40',
                                    inMonth && !has && 'text-muted',
                                    inMonth &&
                                        has &&
                                        !isSel &&
                                        'font-medium text-ink hover:bg-elevated',
                                    isSel &&
                                        'bg-accent font-semibold text-on-accent',
                                    today &&
                                        !isSel &&
                                        'font-semibold text-accent',
                                )}
                            >
                                {d.getDate()}
                                {#if inMonth && has && !isSel}
                                    <span
                                        class="absolute bottom-1 size-1 rounded-full bg-accent"
                                    ></span>
                                {/if}
                            </button>
                        {/each}
                    </div>
                </div>

                <!-- Times for the selected day -->
                <div class="min-w-0 flex-1">
                    {#if selectedDay && selectedTimes.length}
                        <p class="text-sm font-semibold text-ink">
                            {fmtDayFull(selectedDay)}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            {#each selectedTimes as o (o.startsAt)}
                                <button
                                    type="button"
                                    onclick={() => pick(o)}
                                    class={cn(
                                        'rounded-lg border px-3 py-1.5 text-sm font-medium tabular-nums outline-none transition-colors focus-visible:ring-2 focus-visible:ring-accent/50',
                                        isSelected(o)
                                            ? 'border-accent bg-accent text-on-accent'
                                            : 'border-line bg-surface text-ink hover:border-accent hover:text-accent',
                                    )}
                                >
                                    {fmtTime(o.startsAt)}
                                </button>
                            {/each}
                        </div>
                    {:else}
                        <p class="text-sm text-muted">
                            Select a highlighted day to see open times.
                        </p>
                    {/if}
                </div>
            </div>

            {#if selected}
                <div
                    class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-accent/40 bg-accent-soft/50 px-4 py-3"
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
