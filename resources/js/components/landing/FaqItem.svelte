<script lang="ts">
    import { ChevronDown } from '@lucide/svelte';

    /**
     * Native <details> disclosure: keyboard and screen-reader behaviour comes
     * free, no JS state. The open/close sweep is preserved from the source
     * design and disabled under prefers-reduced-motion.
     */
    let {
        question,
        answer,
        open = false,
    }: {
        question: string;
        answer: string;
        open?: boolean;
    } = $props();
</script>

<details
    class="group overflow-hidden rounded-xl border border-line bg-surface"
    {open}
>
    <summary
        class="flex cursor-pointer items-center justify-between p-6 font-medium text-ink transition-colors outline-none hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-inset"
    >
        <span class="text-lg">{question}</span>
        <span class="ml-4 shrink-0 text-muted transition group-open:rotate-180">
            <ChevronDown class="size-5" strokeWidth={1.75} />
        </span>
    </summary>
    <div class="px-6 pb-6 leading-relaxed text-muted">{answer}</div>
</details>

<style>
    /* Hide the native disclosure triangle across engines. */
    summary {
        list-style: none;
    }
    summary::-webkit-details-marker {
        display: none;
    }

    details[open] summary ~ * {
        animation: sweep 0.3s ease-in-out;
    }

    @keyframes sweep {
        from {
            opacity: 0;
            margin-top: -10px;
        }
        to {
            opacity: 1;
            margin-top: 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        details[open] summary ~ *,
        summary span {
            animation: none !important;
            transition: none !important;
        }
    }
</style>
