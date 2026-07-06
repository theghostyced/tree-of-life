<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { X } from '@lucide/svelte';
    import { fade, fly } from 'svelte/transition';
    import { cn } from '@/lib/utils';
    import { meetingTime } from './types';
    import type { MissingReport } from './types';

    let {
        meeting,
        onClose,
        onSubmitted,
    }: {
        meeting: MissingReport;
        onClose: () => void;
        onSubmitted: () => void;
    } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const reduce =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const form = useForm<{ summary: string }>({ summary: '' });

    function submit(e: Event) {
        e.preventDefault();
        form.post(`/mentor/meetings/${meeting.meetingId}/report`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSubmitted();
            },
        });
    }
</script>

<div class="fixed inset-0 z-50">
    <button
        type="button"
        aria-label="Close report panel"
        onclick={onClose}
        transition:fade={{ duration: reduce ? 0 : 180 }}
        class="absolute inset-0 bg-black/50"
    ></button>
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="report-title"
        transition:fly={{ x: 32, duration: reduce ? 0 : 240 }}
        class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-line bg-panel shadow-card"
    >
        <div
            class="flex items-start justify-between border-b border-line px-6 py-5"
        >
            <div>
                <h2 id="report-title" class="text-lg font-semibold text-ink">
                    Meeting report
                </h2>
                <p class="mt-1 text-sm text-muted">
                    {meeting.menteeName} · {meetingTime(meeting.endedAt)}
                </p>
            </div>
            <button
                type="button"
                onclick={onClose}
                aria-label="Close"
                class={cn(
                    'rounded-lg p-1.5 text-muted transition-colors hover:bg-elevated hover:text-ink',
                    focusRing,
                )}
            >
                <X class="size-4" strokeWidth={1.75} />
            </button>
        </div>

        <form onsubmit={submit} class="flex flex-1 flex-col gap-4 px-6 py-6">
            <label class="block">
                <span class="text-sm font-medium text-ink">
                    What happened in this meeting?
                </span>
                <textarea
                    bind:value={form.summary}
                    rows="8"
                    maxlength="5000"
                    placeholder="What you covered, decisions made, and the next steps you agreed on."
                    class={cn(
                        'auth-input mt-2 w-full rounded-lg px-3 py-2.5 text-[15px] text-ink placeholder:text-muted',
                        focusRing,
                    )}
                ></textarea>
            </label>
            {#if form.errors.summary}
                <p class="text-sm text-danger-strong" role="alert">
                    {form.errors.summary}
                </p>
            {/if}
            <div class="mt-auto flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick={onClose}
                    class={cn(
                        'rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:bg-elevated hover:text-ink',
                        focusRing,
                    )}
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={!form.summary.trim() || form.processing}
                    class={cn(
                        'inline-flex items-center rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:bg-accent-strong disabled:pointer-events-none disabled:opacity-50',
                        focusRing,
                    )}
                >
                    {form.processing ? 'Submitting' : 'Submit report'}
                </button>
            </div>
        </form>
    </div>
</div>
