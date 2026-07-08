<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';
    import type { ConversationSummary } from './types';

    let {
        conversations,
        selectedId,
        onlineIds,
        rolePrefix,
    }: {
        conversations: ConversationSummary[];
        selectedId: number | null;
        onlineIds: Set<number>;
        rolePrefix: string;
    } = $props();

    function open(id: number) {
        router.visit(`/${rolePrefix}/messages/${id}`, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function rel(iso: string | null): string {
        if (!iso) return '';
        const d = new Date(iso);
        const secs = (Date.now() - d.getTime()) / 1000;
        if (secs < 60) return 'now';
        if (secs < 3600) return `${Math.floor(secs / 60)}m`;
        if (secs < 86400) return `${Math.floor(secs / 3600)}h`;
        if (secs < 604800)
            return new Intl.DateTimeFormat(undefined, {
                weekday: 'short',
            }).format(d);
        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
        }).format(d);
    }
</script>

<ul class="flex-1 overflow-y-auto py-1">
    {#each conversations as c (c.id)}
        <li>
            <button
                onclick={() => open(c.id)}
                class={cn(
                    'flex w-full items-center gap-3 px-3 py-2.5 text-left outline-none transition-colors focus-visible:bg-elevated',
                    selectedId === c.id
                        ? 'bg-elevated'
                        : 'hover:bg-elevated/50',
                )}
            >
                <span class="relative shrink-0">
                    <span
                        class="flex size-11 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent"
                    >
                        {c.other.initials}
                    </span>
                    {#if onlineIds.has(c.other.id)}
                        <span
                            title="Active now"
                            class="absolute right-0 bottom-0 size-3 rounded-full border-2 border-panel bg-emerald-500"
                        ></span>
                    {/if}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-baseline justify-between gap-2">
                        <span class="truncate text-sm font-semibold text-ink"
                            >{c.other.name}</span
                        >
                        <span
                            class={cn(
                                'shrink-0 text-[11px] tabular-nums',
                                c.unread_count > 0
                                    ? 'font-medium text-accent'
                                    : 'text-faint',
                            )}
                        >
                            {rel(c.last_message_at)}
                        </span>
                    </span>
                    <span
                        class="mt-0.5 flex items-center justify-between gap-2"
                    >
                        <span
                            class={cn(
                                'truncate text-[13px]',
                                c.unread_count > 0
                                    ? 'font-medium text-ink'
                                    : 'text-muted',
                            )}
                        >
                            {c.last_message_preview ?? 'No messages yet'}
                        </span>
                        {#if c.unread_count > 0}
                            <span
                                class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-semibold text-on-accent tabular-nums"
                            >
                                {c.unread_count}
                            </span>
                        {/if}
                    </span>
                </span>
            </button>
        </li>
    {:else}
        <li class="px-4 py-10 text-center text-sm text-muted">
            No conversations found.
        </li>
    {/each}
</ul>
