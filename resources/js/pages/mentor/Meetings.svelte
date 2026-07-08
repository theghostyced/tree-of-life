<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Video, MapPin, Calendar, FileText } from '@lucide/svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import { toast } from '@/components/ui/sonner';
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
        canReport: boolean;
    };

    let {
        upcoming = [],
        past = [],
    }: {
        upcoming: Meeting[];
        past: Meeting[];
    } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
    const field =
        'w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink outline-none focus-visible:border-accent focus-visible:ring-3 focus-visible:ring-accent/30';

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

    // Report composer: one open at a time.
    let reportFor = $state<number | null>(null);
    let summary = $state('');
    let submitting = $state(false);

    function openReport(id: number) {
        reportFor = id;
        summary = '';
    }
    function submitReport(id: number) {
        router.post(
            `/mentor/meetings/${id}/report`,
            { summary },
            {
                preserveScroll: true,
                onStart: () => (submitting = true),
                onFinish: () => (submitting = false),
                onSuccess: () => {
                    toast.success('Report submitted.');
                    reportFor = null;
                    summary = '';
                },
            },
        );
    }
</script>

<MentorLayout title="Meetings">
    <div class="mx-auto w-full max-w-7xl px-6 py-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Meetings
            </h1>
            <p class="mt-1.5 text-[15px] text-muted">
                Your sessions with mentees, and the reports for each one.
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
                            Your report
                        </p>
                        <p class="mt-1 text-sm text-muted">{m.reportSummary}</p>
                    </div>
                {:else if m.canReport}
                    <div class="mt-3 border-t border-line pt-3">
                        {#if reportFor === m.id}
                            <textarea
                                bind:value={summary}
                                rows="3"
                                placeholder="What did you cover, and what are the next steps?"
                                class={field}
                            ></textarea>
                            <div class="mt-2 flex items-center gap-2">
                                <button
                                    type="button"
                                    onclick={() => submitReport(m.id)}
                                    disabled={submitting ||
                                        summary.trim() === ''}
                                    class={cn(
                                        'inline-flex items-center gap-1.5 rounded-lg bg-accent px-3 py-1.5 text-xs font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong disabled:opacity-50',
                                        focusRing,
                                    )}
                                >
                                    Submit report
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (reportFor = null)}
                                    class={cn(
                                        'rounded-lg px-3 py-1.5 text-xs text-muted transition-colors hover:text-ink',
                                        focusRing,
                                    )}
                                >
                                    Cancel
                                </button>
                            </div>
                        {:else}
                            <button
                                type="button"
                                onclick={() => openReport(m.id)}
                                class={cn(
                                    'inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:text-ink',
                                    focusRing,
                                )}
                            >
                                <FileText class="size-3.5" strokeWidth={1.75} />
                                Add report
                            </button>
                        {/if}
                    </div>
                {/if}
            </div>
        {/snippet}

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
                        When a mentee books one of your available times, it
                        shows up here.
                    </p>
                </div>
            {/if}
        </section>

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
</MentorLayout>
