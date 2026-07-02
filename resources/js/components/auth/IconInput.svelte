<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLInputAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';

    /**
     * Translucent auth text field with a leading icon and an optional trailing
     * control (e.g. a password reveal button). Reused for every auth input.
     */
    let {
        icon,
        trailing,
        value = $bindable(''),
        class: className,
        ...rest
    }: {
        icon?: Snippet;
        trailing?: Snippet;
        value?: string;
        class?: string;
    } & HTMLInputAttributes = $props();
</script>

<div class="group relative">
    {#if icon}
        <div
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-faint transition-colors duration-200 group-focus-within:text-accent"
        >
            {@render icon()}
        </div>
    {/if}
    <input
        bind:value
        class={cn(
            'auth-input w-full rounded-xl py-3.5 text-[15px] text-ink',
            icon ? 'pl-11' : 'pl-4',
            trailing ? 'pr-12' : 'pr-4',
            className,
        )}
        {...rest}
    />
    {#if trailing}
        <div class="absolute top-1/2 right-2 -translate-y-1/2">
            {@render trailing()}
        </div>
    {/if}
</div>
