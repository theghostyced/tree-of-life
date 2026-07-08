<script lang="ts">
    import { Send } from '@lucide/svelte';
    let { disabled = false, onsend, ontyping }: { disabled?: boolean; onsend: (body: string) => void; ontyping: () => void } = $props();
    let body = $state('');
    let lastTyped = 0;

    function submit(e: Event) {
        e.preventDefault();
        const text = body.trim();
        if (!text) return;
        onsend(text);
        body = '';
    }
    function onInput() {
        const now = Date.now();
        if (now - lastTyped > 1500) { ontyping(); lastTyped = now; }
    }
</script>

<form onsubmit={submit} class="flex items-end gap-2 border-t border-line p-3">
    <textarea
        bind:value={body}
        oninput={onInput}
        {disabled}
        rows="1"
        placeholder={disabled ? 'This mentorship has ended' : 'Write a message…'}
        class="max-h-32 flex-1 resize-none rounded-lg border border-line bg-surface px-3 py-2 text-[15px] text-ink outline-none placeholder:text-faint focus:border-accent disabled:opacity-60"
        onkeydown={(e) => { if (e.key === 'Enter' && !e.shiftKey) submit(e); }}
    ></textarea>
    <button type="submit" {disabled} aria-label="Send" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-on-accent transition-colors hover:bg-accent-strong disabled:opacity-50">
        <Send class="size-4" strokeWidth={2} />
    </button>
</form>
