<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    /**
     * Reusable empty state: centred icon, title, supporting copy and an optional
     * action. Used wherever a surface has no data to show yet.
     */
    let {
        title,
        description,
        icon,
        action,
        class: className,
    }: {
        title: string;
        description?: string;
        icon?: Snippet;
        action?: Snippet;
        class?: string;
    } = $props();
</script>

<div
    class={cn(
        'flex flex-col items-center justify-center px-6 py-20 text-center',
        className,
    )}
>
    {#if icon}
        <div
            class="mb-5 flex size-12 items-center justify-center rounded-full border border-line bg-accent-soft text-accent"
        >
            {@render icon()}
        </div>
    {/if}
    <h3 class="text-base font-medium text-ink">{title}</h3>
    {#if description}
        <p class="mt-1.5 max-w-md text-sm text-muted">{description}</p>
    {/if}
    {#if action}
        <div class="mt-6">{@render action()}</div>
    {/if}
</div>
