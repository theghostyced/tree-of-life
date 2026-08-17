<script lang="ts">
    import { cn } from '@/lib/utils';

    /**
     * Reusable Tree Of Life Fund logo: the tree mark with an optional wordmark
     * beside it. Used on the auth screens and in app chrome.
     *
     * The mark is a raster rather than the source SVG: that file is traced
     * artwork of ~7,600 paths. The PNG is 168px with a transparent background,
     * which caps `lg` at 80px to stay sharp on a 2x display. Going larger needs
     * a new export — the 1080px source in the repo root has an opaque white
     * background and renders as a white box on the dark hero.
     */
    let {
        size = 'lg',
        showName = true,
        name = 'Tree Of Life Fund',
        class: className,
    }: {
        size?: 'sm' | 'md' | 'lg';
        showName?: boolean;
        name?: string;
        class?: string;
    } = $props();

    /**
     * The mark is a detailed illustration — fine roots, gem leaves, a double
     * ring — so it needs real size before those features read at all. `sm` is
     * bounded by the app navbar's h-14 and cannot grow; `md` and `lg` sit in
     * chrome that sets its own height, so they carry the mark large.
     */
    const mark = {
        sm: 'size-9',
        md: 'size-16',
        lg: 'size-20',
    };
    const label = {
        sm: 'text-sm font-semibold tracking-tight',
        md: 'text-lg font-semibold tracking-tight',
        lg: 'text-2xl font-semibold tracking-tight',
    };
</script>

<!-- The wordmark inherits its colour so a dark surface can override it. -->
<div class={cn('flex items-center gap-3 text-ink', className)}>
    <img
        src="/images/brand/tree-of-life.png"
        alt={showName ? '' : name}
        width="168"
        height="168"
        class={cn('shrink-0 object-contain', mark[size])}
    />
    {#if showName}
        <span class={cn(label[size])}>{name}</span>
    {/if}
</div>
