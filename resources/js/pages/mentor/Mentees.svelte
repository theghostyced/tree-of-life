<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { CalendarClock, MessageSquare, Users } from '@lucide/svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import { cn } from '@/lib/utils';

    type Mentee = {
        pairingId: number;
        userId: number;
        name: string;
        email: string;
        company: string | null;
        business: string | null;
        sectors: string[];
        pairedAt: number | null;
        endedAt: number | null;
        lastMeetingAt: number | null;
        nextMeetingAt: number | null;
        meetingCount: number;
        conversationId: number | null;
    };

    let { active = [], ended = [] }: { active: Mentee[]; ended: Mentee[] } =
        $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const fmt = (ms: number | null) =>
        ms
            ? new Date(ms).toLocaleDateString('en-US', {
                  month: 'short',
                  day: 'numeric',
                  year: 'numeric',
              })
            : null;

    const initials = (name: string) =>
        name
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase() ?? '')
            .join('');
</script>

<MentorLayout title="Mentees">
    <div class="mx-auto w-full max-w-7xl px-6 py-8">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Mentees
            </h1>
            <p class="mt-1 text-sm text-muted">
                {active.length}
                {active.length === 1 ? 'entrepreneur' : 'entrepreneurs'} currently
                paired with you.
            </p>
        </header>

        {#if active.length === 0 && ended.length === 0}
            <div
                class="flex flex-col items-center justify-center rounded-xl border border-line bg-panel/40 px-6 py-16 text-center"
            >
                <Users class="size-6 text-faint" strokeWidth={1.5} />
                <p class="mt-3 text-sm font-medium text-ink">No mentees yet</p>
                <p class="mt-1 max-w-sm text-sm text-muted">
                    Entrepreneurs choose their own mentors. Once someone picks
                    you, they appear here with their next meeting.
                </p>
            </div>
        {/if}

        {#if active.length}
            <ul class="border-t border-line">
                {#each active as mentee (mentee.pairingId)}
                    <li
                        class="flex flex-col gap-4 border-b border-line py-5 sm:flex-row sm:items-center"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent"
                            aria-hidden="true"
                        >
                            {initials(mentee.name)}
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink">
                                {mentee.name}
                            </p>
                            <p class="truncate text-sm text-muted">
                                {mentee.business ??
                                    mentee.company ??
                                    mentee.email}
                                {#if mentee.sectors.length}
                                    <span class="text-faint">
                                        · {mentee.sectors.join(', ')}</span
                                    >
                                {/if}
                            </p>
                        </div>

                        <div class="text-sm sm:w-56">
                            {#if mentee.nextMeetingAt}
                                <p class="flex items-center gap-1.5 text-ink">
                                    <CalendarClock
                                        class="size-3.5 shrink-0 text-accent"
                                        strokeWidth={1.75}
                                    />
                                    Next {fmt(mentee.nextMeetingAt)}
                                </p>
                            {:else}
                                <p class="text-muted">No meeting scheduled</p>
                            {/if}
                            <p class="mt-0.5 text-xs text-faint">
                                {mentee.meetingCount}
                                {mentee.meetingCount === 1
                                    ? 'meeting'
                                    : 'meetings'} so far{#if mentee.lastMeetingAt},
                                    last {fmt(mentee.lastMeetingAt)}{/if}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            {#if mentee.conversationId}
                                <Link
                                    href={`/mentor/messages/${mentee.conversationId}`}
                                    class={cn(
                                        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-elevated hover:text-ink',
                                        focusRing,
                                    )}
                                >
                                    <MessageSquare
                                        class="size-3.5"
                                        strokeWidth={1.75}
                                    />
                                    Message
                                </Link>
                            {/if}
                            <Link
                                href="/mentor/meetings"
                                class={cn(
                                    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-elevated hover:text-ink',
                                    focusRing,
                                )}
                            >
                                <CalendarClock
                                    class="size-3.5"
                                    strokeWidth={1.75}
                                />
                                Meetings
                            </Link>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}

        {#if ended.length}
            <section class="mt-12">
                <h2 class="text-sm font-semibold text-ink">Past mentees</h2>
                <ul class="mt-3 border-t border-line">
                    {#each ended as mentee (mentee.pairingId)}
                        <li
                            class="flex items-center gap-4 border-b border-line py-4"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm text-ink">
                                    {mentee.name}
                                </p>
                                <p class="truncate text-xs text-muted">
                                    {mentee.business ??
                                        mentee.company ??
                                        mentee.email}
                                </p>
                            </div>
                            <p class="shrink-0 text-xs text-faint">
                                Ended {fmt(mentee.endedAt) ?? 'recently'}
                            </p>
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}
    </div>
</MentorLayout>
