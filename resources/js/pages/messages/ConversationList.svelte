<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';
    import type { ConversationSummary } from './types';

    let { conversations, selectedId, onlineIds, rolePrefix }:
        { conversations: ConversationSummary[]; selectedId: number | null; onlineIds: Set<number>; rolePrefix: string } = $props();

    function open(id: number) {
        router.visit(`/${rolePrefix}/messages/${id}`, { preserveState: true, preserveScroll: true });
    }
    const rel = (iso: string | null) => iso ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(iso)) : '';
</script>

<ul class="divide-y divide-line overflow-y-auto">
    {#each conversations as c (c.id)}
        <li>
            <button
                onclick={() => open(c.id)}
                class={cn('flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-elevated/50', selectedId === c.id && 'bg-elevated')}
            >
                <div class="relative shrink-0">
                    <div class="flex size-11 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent">{c.other.initials}</div>
                    {#if onlineIds.has(c.other.id)}<span class="absolute right-0 bottom-0 size-3 rounded-full border-2 border-panel bg-emerald-500"></span>{/if}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="truncate text-sm font-medium text-ink">{c.other.name}</p>
                        <span class="shrink-0 text-[11px] text-faint">{rel(c.last_message_at)}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <p class={cn('truncate text-xs', c.unread_count > 0 ? 'font-medium text-ink' : 'text-muted')}>{c.last_message_preview ?? 'No messages yet'}</p>
                        {#if c.unread_count > 0}<span class="flex min-w-5 shrink-0 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-semibold text-on-accent">{c.unread_count}</span>{/if}
                    </div>
                </div>
            </button>
        </li>
    {/each}
</ul>
