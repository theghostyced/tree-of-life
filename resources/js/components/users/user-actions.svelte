<script lang="ts">
    import { Ban, RotateCcw, Trash2 } from '@lucide/svelte';
    import { cn } from '@/lib/utils';
    import type { UserRow } from './types';

    let {
        user,
        currentUserId,
        onDeactivate,
        onReactivate,
        onDelete,
    }: {
        user: UserRow;
        currentUserId: number;
        onDeactivate: (u: UserRow) => void;
        onReactivate: (u: UserRow) => void;
        onDelete: (u: UserRow) => void;
    } = $props();

    const isSelf = $derived(user.id === currentUserId);

    let confirming = $state(false);
    let timer: ReturnType<typeof setTimeout> | undefined;
    function askDelete() {
        confirming = true;
        clearTimeout(timer);
        timer = setTimeout(() => (confirming = false), 4000);
    }

    $effect(() => () => clearTimeout(timer));

    const btn =
        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:outline-none';
</script>

{#if isSelf}
    <div class="flex justify-end pr-1 text-xs text-faint">You</div>
{:else}
    <div class="flex items-center justify-end gap-1">
        {#if user.status === 'approved'}
            <button
                type="button"
                onclick={() => onDeactivate(user)}
                class={cn(btn, 'text-muted hover:bg-elevated hover:text-ink')}
            >
                <Ban class="size-3.5" strokeWidth={1.75} />
                Revoke
            </button>
        {:else}
            <button
                type="button"
                onclick={() => onReactivate(user)}
                class={cn(
                    btn,
                    'text-muted hover:bg-elevated hover:text-accent',
                )}
            >
                <RotateCcw class="size-3.5" strokeWidth={1.75} />
                Restore
            </button>
        {/if}
        <!-- One element for both steps so keyboard focus survives the swap. -->
        <button
            type="button"
            onclick={confirming ? () => onDelete(user) : askDelete}
            class={cn(
                btn,
                confirming
                    ? 'bg-danger/12 font-semibold text-danger-strong hover:bg-danger/20'
                    : 'text-muted hover:bg-elevated hover:text-danger-strong',
            )}
        >
            <Trash2 class="size-3.5" strokeWidth={confirming ? 2 : 1.75} />
            {confirming ? 'Confirm' : 'Delete'}
        </button>
    </div>
{/if}
