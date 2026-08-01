<script lang="ts">
    /**
     * One step in the "How Tolfund works" sequence.
     *
     * Deliberately not a card. DESIGN.md rules out repeated icon+heading+text
     * tiles and asks for queues and timelines instead, and the invite → profile
     * → pairing → booking → meeting → report loop genuinely is ordered, so the
     * number carries information rather than decorating the heading. Rows are
     * separated by a single hairline; no borders boxing each item.
     */
    let {
        index,
        title,
        body,
    }: {
        index: number;
        title: string;
        body: string;
    } = $props();
</script>

<li
    class="grid grid-cols-[auto_1fr] items-baseline gap-x-5 gap-y-2 border-t border-line py-7 md:grid-cols-[auto_minmax(0,16rem)_1fr] md:gap-x-8 md:py-9"
>
    <!-- Large but deliberately quiet: the numeral is ~3x the title's size yet
         sits at 3.73:1 against the canvas where the title sits at 16.32:1, so
         hierarchy is carried by contrast and weight rather than by size. -->
    <span
        class="text-3xl leading-none font-normal tracking-[-0.02em] text-accent/60 tabular-nums md:text-5xl"
        aria-hidden="true"
    >
        {String(index).padStart(2, '0')}
    </span>
    <h3 class="text-lg font-semibold text-balance text-ink">{title}</h3>
    <p
        class="col-start-2 max-w-[65ch] text-[15px] leading-relaxed text-pretty text-muted md:col-start-3"
    >
        {body}
    </p>
</li>
