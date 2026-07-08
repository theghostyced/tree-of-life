<script lang="ts">
    import { Send } from '@lucide/svelte';

    let {
        disabled = false,
        onsend,
        ontyping,
    }: {
        disabled?: boolean;
        onsend: (body: string) => void;
        ontyping: () => void;
    } = $props();

    let body = $state('');
    let el = $state<HTMLTextAreaElement | null>(null);
    let lastTyped = 0;

    function autogrow() {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 160) + 'px';
    }

    function submit(e: Event) {
        e.preventDefault();
        const text = body.trim();
        if (!text) return;
        onsend(text);
        body = '';
        queueMicrotask(autogrow);
    }

    function onInput() {
        autogrow();
        const now = Date.now();
        if (now - lastTyped > 1500) {
            ontyping();
            lastTyped = now;
        }
    }
</script>

<form
    onsubmit={submit}
    class="shrink-0 border-t border-line bg-panel px-4 py-3"
>
    <div class="mx-auto flex w-full max-w-3xl items-end gap-2.5">
        <textarea
            bind:this={el}
            bind:value={body}
            oninput={onInput}
            {disabled}
            rows="1"
            placeholder={disabled
                ? 'This mentorship has ended — messaging is closed'
                : 'Write a message…'}
            class="max-h-40 flex-1 resize-none rounded-2xl border border-line bg-surface px-4 py-2.5 text-[15px] leading-relaxed text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus:ring-2 focus:ring-accent/25 disabled:cursor-not-allowed disabled:opacity-60"
            onkeydown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) submit(e);
            }}
        ></textarea>
        <button
            type="submit"
            disabled={disabled || body.trim().length === 0}
            aria-label="Send message"
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-accent text-on-accent transition-all hover:bg-accent-strong focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:outline-none active:scale-95 disabled:opacity-40 disabled:hover:bg-accent disabled:active:scale-100"
        >
            <Send class="size-[18px]" strokeWidth={2} />
        </button>
    </div>
</form>
