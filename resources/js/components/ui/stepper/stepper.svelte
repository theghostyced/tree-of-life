<script lang="ts">
    import { Check } from '@lucide/svelte';
    import { cn } from '@/lib/utils';

    type Step = { label: string; complete: boolean };

    let {
        steps,
        current,
        onselect,
    }: {
        steps: Step[];
        current: number;
        onselect?: (index: number) => void;
    } = $props();
</script>

<nav aria-label="Onboarding steps">
    <ol class="flex items-center">
        {#each steps as step, i (step.label)}
            <li class={cn('flex items-center', i < steps.length - 1 && 'flex-1')}>
                <button
                    type="button"
                    onclick={() => onselect?.(i)}
                    aria-current={i === current ? 'step' : undefined}
                    class="flex items-center gap-2.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
                >
                    <span
                        class={cn(
                            'flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold transition-colors',
                            i === current
                                ? 'border-accent bg-accent text-on-accent'
                                : step.complete
                                  ? 'border-accent bg-accent-soft text-accent'
                                  : 'border-line bg-surface text-faint',
                        )}
                    >
                        {#if step.complete && i !== current}
                            <Check class="size-4" strokeWidth={2.5} />
                        {:else}
                            {i + 1}
                        {/if}
                    </span>
                    <span
                        class={cn(
                            'hidden whitespace-nowrap text-sm font-medium transition-colors sm:inline',
                            i === current
                                ? 'text-ink'
                                : step.complete
                                  ? 'text-muted'
                                  : 'text-faint',
                        )}
                    >
                        {step.label}
                    </span>
                </button>
                {#if i < steps.length - 1}
                    <div
                        class={cn(
                            'mx-3 h-px flex-1 transition-colors',
                            step.complete ? 'bg-accent/40' : 'bg-line',
                        )}
                    ></div>
                {/if}
            </li>
        {/each}
    </ol>
</nav>
