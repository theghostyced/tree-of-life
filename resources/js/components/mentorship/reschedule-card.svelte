<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { CalendarClock } from '@lucide/svelte';
    import { cn } from '@/lib/utils';
    import { meetingTime } from './types';
    import type { AttentionReschedule } from './types';

    let {
        reschedule,
        onReviewed,
    }: {
        reschedule: AttentionReschedule;
        onReviewed: (accepted: boolean) => void;
    } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    let acting = $state(false);

    function review(accepted: boolean) {
        router.post(
            `/mentor/reschedules/${reschedule.id}/${accepted ? 'accept' : 'decline'}`,
            {},
            {
                preserveScroll: true,
                onStart: () => (acting = true),
                onFinish: () => (acting = false),
                onSuccess: () => onReviewed(accepted),
            },
        );
    }
</script>

<div class="rounded-xl border border-line bg-panel/40 p-5">
    <div class="flex items-start gap-3">
        <CalendarClock
            class="mt-0.5 size-4 shrink-0 text-glow-amber"
            strokeWidth={1.75}
        />
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-ink">
                {reschedule.menteeName} asked to move your meeting
            </p>
            <p class="mt-1 text-sm text-muted">
                <span class="line-through"
                    >{meetingTime(reschedule.previousStartsAt)}</span
                >
                <span class="mx-1.5 text-faint" aria-hidden="true">&rarr;</span>
                <span class="text-ink"
                    >{meetingTime(reschedule.newStartsAt)}</span
                >
            </p>
            {#if reschedule.reason}
                <p class="mt-1.5 text-[13px] text-faint">
                    "{reschedule.reason}"
                </p>
            {/if}
        </div>
    </div>
    <div class="mt-4 flex items-center gap-2">
        <button
            type="button"
            disabled={acting}
            onclick={() => review(true)}
            class={cn(
                'inline-flex items-center rounded-lg bg-accent px-3.5 py-2 text-sm font-semibold text-on-accent shadow-btn transition-all hover:bg-accent-strong disabled:pointer-events-none disabled:opacity-50',
                focusRing,
            )}
        >
            Accept new time
        </button>
        <button
            type="button"
            disabled={acting}
            onclick={() => review(false)}
            class={cn(
                'inline-flex items-center rounded-lg border border-line bg-surface px-3.5 py-2 text-sm font-medium text-muted transition-colors hover:border-line-strong hover:text-ink disabled:pointer-events-none disabled:opacity-50',
                focusRing,
            )}
        >
            Decline
        </button>
    </div>
</div>
