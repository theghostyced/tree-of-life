<script lang="ts">
    import { TriangleAlert } from '@lucide/svelte';
    import { type Status, statusMeta } from './types';
    import { cn } from '@/lib/utils';

    let {
        status,
        deliveryFailed = false,
        deliveryError = null,
    }: {
        status: Status;
        deliveryFailed?: boolean;
        deliveryError?: string | null;
    } = $props();
    const meta = $derived(statusMeta[status]);
</script>

<div class="flex flex-wrap items-center gap-1.5">
    <span
        class={cn(
            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
            meta.chip,
            meta.text,
        )}
    >
        <span class={cn('size-1.5 rounded-full', meta.dot)}></span>
        {meta.label}
    </span>

    <!-- A pending invitation whose mail never left looks identical to one
         sitting in an inbox, so the failure needs saying out loud. -->
    {#if deliveryFailed}
        <span
            title={deliveryError ?? undefined}
            class="inline-flex items-center gap-1 rounded-full border border-danger/25 bg-danger/10 px-2 py-1 text-xs font-medium text-danger"
        >
            <TriangleAlert class="size-3" strokeWidth={2} />
            Not delivered
        </span>
    {/if}
</div>
