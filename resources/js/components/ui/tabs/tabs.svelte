<script lang="ts">
    import { cn } from '@/lib/utils';

    export type TabItem = { label: string; value: string };

    /**
     * Reusable underline tab bar. Controlled via the bindable `active` value.
     */
    let {
        items = [],
        active = $bindable(),
        onchange,
        class: className,
    }: {
        items?: TabItem[];
        active?: string;
        onchange?: (value: string) => void;
    } & { class?: string } = $props();

    // Default the selection to the first tab when none is provided.
    $effect(() => {
        if (active === undefined && items.length > 0) {
            active = items[0].value;
        }
    });

    function select(value: string) {
        active = value;
        onchange?.(value);
    }
</script>

<div
    class={cn(
        'custom-scrollbar flex items-center gap-8 overflow-x-auto border-b border-line',
        className,
    )}
>
    {#each items as item (item.value)}
        <button
            type="button"
            onclick={() => select(item.value)}
            class={cn(
                'shrink-0 whitespace-nowrap rounded-sm pb-3 text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-accent/50',
                active === item.value
                    ? 'border-b-2 border-accent text-accent'
                    : 'text-muted hover:text-ink',
            )}
        >
            {item.label}
        </button>
    {/each}
</div>
