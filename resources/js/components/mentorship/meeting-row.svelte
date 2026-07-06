<script lang="ts">
    import { MapPin, Video } from '@lucide/svelte';
    import { cn } from '@/lib/utils';
    import { meetingTime } from './types';
    import type { WeekMeeting } from './types';

    let { meeting }: { meeting: WeekMeeting } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
</script>

<li class="flex items-center gap-4 py-3.5">
    {#if meeting.sessionType === 'virtual'}
        <Video class="size-4 shrink-0 text-accent" strokeWidth={1.75} />
    {:else}
        <MapPin class="size-4 shrink-0 text-accent" strokeWidth={1.75} />
    {/if}
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-ink">
            {meeting.menteeName}
        </p>
        <p class="mt-0.5 text-[13px] text-muted">
            {meetingTime(meeting.startsAt)}
            {#if meeting.location}
                <span class="text-faint">· {meeting.location}</span>
            {/if}
        </p>
    </div>
    {#if meeting.meetingLink}
        <a
            href={meeting.meetingLink}
            target="_blank"
            rel="noopener noreferrer"
            class={cn(
                'shrink-0 rounded-md px-2.5 py-1.5 text-xs font-semibold text-accent transition-colors hover:bg-elevated',
                focusRing,
            )}
        >
            Join
        </a>
    {/if}
</li>
