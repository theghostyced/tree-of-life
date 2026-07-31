<script lang="ts">
    import type { Icon as IconType } from '@lucide/svelte';

    /**
     * One capability tile in the "How it works" grid.
     *
     * The source design gave every card its own hue (purple/blue/green/orange/
     * pink/teal). DESIGN.md's Accent Rarity Rule caps warm accents at ~10% of a
     * screen and reserves colour for status, so all six tiles share the sage
     * accent and separate by icon and copy instead. Flat at rest per the
     * Flat-Field Rule: a border defines the card, no resting drop shadow.
     */
    let {
        icon: Icon,
        title,
        body,
    }: {
        icon: typeof IconType;
        title: string;
        body: string;
    } = $props();
</script>

<div
    class="group rounded-xl border border-line bg-surface p-8 transition-colors hover:border-line-strong"
>
    <div
        class="mb-6 flex size-12 items-center justify-center rounded-lg bg-accent-soft text-accent transition-transform group-hover:scale-105"
    >
        <Icon class="size-6" strokeWidth={1.75} />
    </div>
    <h3 class="mb-3 text-lg font-semibold text-ink">{title}</h3>
    <p class="text-[15px] leading-relaxed text-muted">{body}</p>
</div>
