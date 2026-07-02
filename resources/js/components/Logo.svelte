<script lang="ts">
    import { TreeDeciduous } from '@lucide/svelte';
    import { cn } from '@/lib/utils';

    /**
     * Reusable Tree Of Life Fund logo: a sage tile holding a tree mark, with an
     * optional wordmark beside it. Used on the auth screens and in app chrome.
     */
    let {
        size = 'lg',
        showName = true,
        name = 'Tree Of Life Fund',
        class: className,
    }: {
        size?: 'sm' | 'lg';
        showName?: boolean;
        name?: string;
        class?: string;
    } = $props();

    const tile = {
        sm: 'size-7 rounded-md',
        lg: 'size-10 rounded',
    };
    const icon = {
        sm: 'size-4',
        lg: 'size-6',
    };
    const label = {
        sm: 'text-sm font-semibold tracking-tight',
        lg: 'text-2xl font-semibold tracking-tight',
    };
</script>

<div class={cn('flex items-center gap-3', className)}>
    <div
        class={cn(
            'flex items-center justify-center bg-[#A3B18A] text-[#0A0C0B]',
            tile[size],
        )}
    >
        <TreeDeciduous class={icon[size]} strokeWidth={2} />
    </div>
    {#if showName}
        <span class={cn('text-ink', label[size])}>{name}</span>
    {/if}
</div>
