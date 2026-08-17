<script lang="ts">
    import { RotateCw, Ban } from '@lucide/svelte';
    import type { Invitation } from './types';
    import { cn } from '@/lib/utils';

    let {
        invitation,
        onResend,
        onRevoke,
    }: {
        invitation: Invitation;
        onResend: (inv: Invitation) => void;
        onRevoke: (inv: Invitation) => void;
    } = $props();

    let confirming = $state(false);
    let timer: ReturnType<typeof setTimeout> | undefined;

    function askRevoke() {
        confirming = true;
        clearTimeout(timer);
        timer = setTimeout(() => (confirming = false), 4000);
    }

    const btn =
        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:outline-none';
</script>

{#if invitation.status === 'pending'}
    <div class="flex items-center justify-end gap-1">
        <button
            type="button"
            onclick={() => onResend(invitation)}
            class={cn(
                btn,
                invitation.deliveryFailed
                    ? 'font-semibold text-danger hover:bg-danger/10'
                    : 'text-muted hover:bg-elevated hover:text-ink',
            )}
        >
            <RotateCw class="size-3.5" strokeWidth={1.75} />
            <!-- Same action either way; the word changes because retrying a
                 failed delivery is a different intent from nudging an invitee. -->
            {invitation.deliveryFailed ? 'Retry' : 'Resend'}
        </button>
        {#if confirming}
            <button
                type="button"
                onclick={() => onRevoke(invitation)}
                class={cn(
                    btn,
                    'bg-[#cf8b8b]/12 font-semibold text-[#d7a1a1] hover:bg-[#cf8b8b]/20',
                )}
            >
                <Ban class="size-3.5" strokeWidth={2} />
                Confirm revoke
            </button>
        {:else}
            <button
                type="button"
                onclick={askRevoke}
                class={cn(
                    btn,
                    'text-muted hover:bg-elevated hover:text-[#d7a1a1]',
                )}
            >
                <Ban class="size-3.5" strokeWidth={1.75} />
                Revoke
            </button>
        {/if}
    </div>
{:else}
    <div class="flex justify-end pr-1 text-xs text-faint">—</div>
{/if}
