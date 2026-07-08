<script lang="ts">
    import type { Message } from './types';

    let {
        message,
        mine,
        seen = false,
        grouped = false,
        showTime = true,
    }: {
        message: Message;
        mine: boolean;
        seen?: boolean;
        grouped?: boolean;
        showTime?: boolean;
    } = $props();

    const time = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(message.created_at));
</script>

{#if message.type === 'system'}
    <div class="my-3 flex justify-center">
        <span class="rounded-full bg-elevated px-3 py-1 text-xs text-muted">
            {message.body}
        </span>
    </div>
{:else}
    <div
        class="flex flex-col {mine ? 'items-end' : 'items-start'} {grouped
            ? 'mt-0.5'
            : 'mt-3'}"
    >
        <div
            class="max-w-[min(88%,34rem)] px-3.5 py-2 text-[15px] leading-relaxed break-words rounded-2xl {mine
                ? 'bg-accent text-on-accent ' + (grouped ? 'rounded-tr-md' : '')
                : 'bg-elevated text-ink ' + (grouped ? 'rounded-tl-md' : '')}"
        >
            {message.body}
        </div>
        {#if showTime}
            <span class="mt-1 px-1 text-[11px] text-faint">
                {time}{#if mine && seen}<span class="text-accent">
                        · Seen</span
                    >{/if}
            </span>
        {/if}
    </div>
{/if}
