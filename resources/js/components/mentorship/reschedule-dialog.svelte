<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { X, Calendar } from '@lucide/svelte';
    import { toast } from '@/components/ui/sonner';
    import { cn } from '@/lib/utils';

    type Occurrence = { slotId: number; startsAt: number; endsAt: number };
    type Target = {
        id: number;
        pairingId: number;
        startsAt: number;
        counterpartName: string;
    };

    let {
        target = null,
        occurrences = [],
        endpoint,
        reasonRequired = false,
        onclose,
    }: {
        target: Target | null;
        occurrences: Occurrence[];
        endpoint: (meetingId: number) => string;
        reasonRequired?: boolean;
        onclose: () => void;
    } = $props();

    let dialog = $state<HTMLDialogElement | null>(null);
    let chosen = $state<Occurrence | null>(null);
    let reason = $state('');
    let saving = $state(false);

    $effect(() => {
        if (target && dialog && !dialog.open) {
            chosen = null;
            reason = '';
            dialog.showModal();
        }
    });

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const fmt = (ms: number) =>
        new Date(ms).toLocaleString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });

    const canSubmit = $derived(
        chosen !== null && (!reasonRequired || reason.trim().length > 0),
    );

    function submit() {
        if (!target || !chosen) {
            return;
        }

        router.post(
            endpoint(target.id),
            {
                slot_id: chosen.slotId,
                starts_at: chosen.startsAt,
                reason: reason.trim() || null,
            },
            {
                preserveScroll: true,
                onStart: () => (saving = true),
                onFinish: () => (saving = false),
                onSuccess: () => dialog?.close(),
                onError: (errors) =>
                    toast.error(
                        errors.reason ??
                            errors.starts_at ??
                            'That time is no longer available.',
                    ),
            },
        );
    }
</script>

<dialog
    bind:this={dialog}
    data-test="reschedule-dialog"
    aria-label="Reschedule call"
    onclose={() => onclose()}
    onclick={(event) => {
        if (event.target === dialog) {
            dialog?.close();
        }
    }}
    class="m-auto w-[min(34rem,92vw)] rounded-xl border border-line bg-panel p-0 text-ink backdrop:bg-black/70 backdrop:backdrop-blur-sm"
>
    {#if target}
        <div class="flex items-start gap-3 border-b border-line px-5 py-4">
            <Calendar
                class="mt-0.5 size-4 shrink-0 text-accent"
                strokeWidth={1.75}
            />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-ink">Move this call</p>
                <p class="text-xs text-muted">
                    With {target.counterpartName}, currently {fmt(
                        target.startsAt,
                    )}
                </p>
            </div>
            <button
                type="button"
                aria-label="Close"
                onclick={() => dialog?.close()}
                class={cn(
                    'rounded-md p-1.5 text-muted transition-colors hover:bg-elevated hover:text-ink',
                    focusRing,
                )}
            >
                <X class="size-4" strokeWidth={1.75} />
            </button>
        </div>

        <div class="max-h-[50vh] overflow-y-auto px-5 py-4">
            {#if occurrences.length === 0}
                <p class="text-sm text-muted">
                    There are no other free times in this mentor's published
                    availability right now.
                </p>
            {:else}
                <p class="mb-3 text-xs font-semibold text-muted">
                    Choose a new time
                </p>
                <div class="grid gap-2">
                    {#each occurrences as occurrence (occurrence.startsAt)}
                        <button
                            type="button"
                            data-test="reschedule-option"
                            onclick={() => (chosen = occurrence)}
                            class={cn(
                                'rounded-lg border px-3 py-2 text-left text-sm transition-colors',
                                chosen?.startsAt === occurrence.startsAt
                                    ? 'border-accent bg-accent-soft text-ink'
                                    : 'border-line text-muted hover:border-line-strong hover:text-ink',
                                focusRing,
                            )}
                        >
                            {fmt(occurrence.startsAt)}
                        </button>
                    {/each}
                </div>

                <label
                    for="reschedule-reason"
                    class="mt-5 mb-2 block text-xs font-semibold text-muted"
                >
                    {reasonRequired
                        ? 'Why are you moving it?'
                        : 'Reason (optional)'}
                </label>
                <textarea
                    id="reschedule-reason"
                    bind:value={reason}
                    rows="3"
                    data-test="reschedule-reason"
                    class="auth-input w-full rounded-lg px-3 py-2 text-sm text-ink"
                ></textarea>
            {/if}
        </div>

        <div class="flex justify-end gap-3 border-t border-line px-5 py-4">
            <button
                type="button"
                onclick={() => dialog?.close()}
                class={cn(
                    'rounded-lg px-4 py-2 text-sm font-medium text-muted transition-colors hover:bg-elevated hover:text-ink',
                    focusRing,
                )}
            >
                Cancel
            </button>
            <button
                type="button"
                data-test="reschedule-submit"
                disabled={!canSubmit || saving}
                onclick={submit}
                class={cn(
                    'rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent transition-colors hover:bg-accent-strong disabled:pointer-events-none disabled:opacity-50',
                    focusRing,
                )}
            >
                {saving
                    ? 'Saving…'
                    : reasonRequired
                      ? 'Request move'
                      : 'Move call'}
            </button>
        </div>
    {/if}
</dialog>
