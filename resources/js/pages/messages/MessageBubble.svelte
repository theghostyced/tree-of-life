<script lang="ts">
    import type { Message } from './types';
    let { message, mine, seen = false }: { message: Message; mine: boolean; seen?: boolean } = $props();
    const time = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(message.created_at));
</script>

{#if message.type === 'system'}
    <div class="my-2 flex justify-center">
        <span class="rounded-full bg-elevated px-3 py-1 text-xs text-muted">{message.body}</span>
    </div>
{:else}
    <div class="flex flex-col {mine ? 'items-end' : 'items-start'} gap-0.5">
        <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-[15px] {mine ? 'bg-accent text-on-accent' : 'bg-elevated text-ink'}">
            {message.body}
        </div>
        <span class="px-1 text-[11px] text-faint">{time}{#if mine && seen} · Seen{/if}</span>
    </div>
{/if}
