<script lang="ts">
    import { cn } from '@/lib/utils';
    import { Checkbox } from '@/components/ui/checkbox';

    let {
        options,
        value = $bindable([]),
    }: {
        options: string[];
        value: string[];
    } = $props();

    const optionSet = $derived(new Set(options));

    let otherOpen = $state(value.some((v) => !optionSet.has(v)));
    let otherText = $state(value.filter((v) => !optionSet.has(v)).join(', '));

    function toggle(opt: string) {
        value = value.includes(opt)
            ? value.filter((v) => v !== opt)
            : [...value, opt];
    }

    function commitOther() {
        const predefined = value.filter((v) => optionSet.has(v));
        const customs = otherText
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
        value = [...predefined, ...customs];
    }

    function toggleOther() {
        otherOpen = !otherOpen;
        if (!otherOpen) {
            value = value.filter((v) => optionSet.has(v));
            otherText = '';
        } else {
            commitOther();
        }
    }

    const rowClass = (active: boolean) =>
        cn(
            'flex w-full cursor-pointer items-center gap-2.5 rounded-lg border px-3.5 py-2.5 text-left text-[15px] outline-none transition-colors focus-visible:ring-2 focus-visible:ring-accent/40',
            active
                ? 'border-accent bg-accent-soft text-ink'
                : 'border-line bg-surface text-muted hover:border-line-strong',
        );
</script>

<div class="space-y-3">
    <div class="grid gap-2 sm:grid-cols-2">
        {#each options as opt (opt)}
            <button
                type="button"
                onclick={() => toggle(opt)}
                class={rowClass(value.includes(opt))}
            >
                <Checkbox
                    checked={value.includes(opt)}
                    tabindex={-1}
                    class="pointer-events-none"
                />
                {opt}
            </button>
        {/each}
        <button
            type="button"
            onclick={toggleOther}
            class={rowClass(otherOpen)}
        >
            <Checkbox
                checked={otherOpen}
                tabindex={-1}
                class="pointer-events-none"
            />
            Other
        </button>
    </div>
    {#if otherOpen}
        <input
            bind:value={otherText}
            oninput={commitOther}
            placeholder="Add your own, separated by commas"
            class="h-11 w-full rounded-lg border border-line bg-surface px-3.5 text-[15px] text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus-visible:ring-2 focus-visible:ring-accent/50"
        />
    {/if}
</div>
