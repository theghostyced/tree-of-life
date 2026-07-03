<script lang="ts">
    import { type Invitation, initials } from './types';

    let { invitation }: { invitation: Invitation } = $props();
</script>

<div class="flex min-w-0 items-center gap-2.5">
    <div
        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[11px] font-semibold text-accent"
    >
        {initials(invitation)}
    </div>
    <div class="min-w-0">
        {#if invitation.name}
            <p class="truncate text-sm font-medium text-ink">
                {invitation.name}
            </p>
            <p class="truncate text-xs text-muted">{invitation.email}</p>
        {:else}
            <p class="truncate text-sm font-medium text-ink">
                {invitation.email}
            </p>
            <p class="text-xs text-faint">No name provided</p>
        {/if}
    </div>
</div>
