<script lang="ts">
    import type { HTMLInputAttributes } from 'svelte/elements';
    import type { WithElementRef } from 'bits-ui';
    import { cn } from '@/lib/utils';

    let {
        ref = $bindable(null),
        value = $bindable(),
        class: className,
        ...restProps
    }: WithElementRef<HTMLInputAttributes> = $props();
</script>

<input
    bind:this={ref}
    data-slot="input"
    class={cn(
        'border-input bg-background placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
        className,
    )}
    bind:value
    {...restProps}
/>
