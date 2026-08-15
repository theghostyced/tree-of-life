<script lang="ts">
    import { cn } from '@/lib/utils';

    /**
     * Reusable Tree Of Life Fund logo: the tree mark with an optional wordmark
     * beside it. Used on the auth screens and in app chrome.
     *
     * The mark is a raster rather than the source SVG: that file is traced
     * artwork of ~7,600 paths, and the logo never renders above 40px.
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

    const mark = {
        sm: 'size-7',
        lg: 'size-10',
    };
    const label = {
        sm: 'text-sm font-semibold tracking-tight',
        lg: 'text-2xl font-semibold tracking-tight',
    };
</script>

<div class={cn('flex items-center gap-3', className)}>
    <img
        src="/images/brand/tree-of-life.png"
        alt={showName ? '' : name}
        width="120"
        height="120"
        class={cn('shrink-0 object-contain', mark[size])}
    />
    {#if showName}
        <span class={cn('text-ink', label[size])}>{name}</span>
    {/if}
</div>
