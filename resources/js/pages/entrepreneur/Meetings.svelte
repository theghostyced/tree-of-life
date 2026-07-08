<script lang="ts">
    import { Video, MapPin, Calendar } from '@lucide/svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import BookCallCalendar from './BookCallCalendar.svelte';
    import { Toaster } from '@/components/ui/sonner';
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
    type Mentor = { pairingId: number; mentorId: number; name: string };
    type Occurrence = {
        slotId: number;
        startsAt: number;
        endsAt: number;
        sessionType: 'virtual' | 'in_person';
        location: string | null;
        meetingLink: string | null;
    };

    let {
        upcoming = [],
        past = [],
        mentors = [],
        availability = {},
    }: {
        upcoming: Meeting[];
        past: Meeting[];
        mentors: Mentor[];
        availability: Record<number, Occurrence[]>;
    } = $props();

    const preselect =
        Number(new URLSearchParams(window.location.search).get('pairing')) ||
        null;

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
        <div class="mt-8">
            <BookCallCalendar
                {mentors}
                {availability}
                initialPairingId={preselect}
            />
        </div>

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
                        Pick an open time above to book your first session.
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
