<script lang="ts">
    import { ArrowUp, ArrowDown, ChevronsUpDown } from '@lucide/svelte';
    import type { Column } from '@tanstack/table-core';
    import { cn } from '@/lib/utils';

    let {
        label,
        column,
        align = 'left',
    }: {
        label: string;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        column: Column<any, unknown>;
        align?: 'left' | 'right';
    } = $props();

    const sorted = $derived(column.getIsSorted());
</script>

<button
    type="button"
    onclick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
    class={cn(
        '-mx-1 inline-flex items-center gap-1.5 rounded px-1 py-0.5 text-xs font-medium text-faint transition-colors hover:text-muted focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:outline-none',
        align === 'right' && 'flex-row-reverse',
    )}
>
    {label}
    {#if sorted === 'asc'}
        <ArrowUp class="size-3.5 text-accent" strokeWidth={2} />
    {:else if sorted === 'desc'}
        <ArrowDown class="size-3.5 text-accent" strokeWidth={2} />
    {:else}
        <ChevronsUpDown class="size-3.5 opacity-50" strokeWidth={1.75} />
    {/if}
</button>
