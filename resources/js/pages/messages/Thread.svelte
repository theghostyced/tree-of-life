<script lang="ts">
    import { onDestroy, tick } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { CalendarPlus } from '@lucide/svelte';
    import { subscribeConversation, whisperTyping } from '@/lib/chat';
    import type { Message, Thread } from './types';
    import MessageBubble from './MessageBubble.svelte';
    import Composer from './Composer.svelte';

    let {
        thread,
        currentUserId,
        rolePrefix,
        onlineIds,
    }: {
        thread: Thread;
        currentUserId: number;
        rolePrefix: string;
        onlineIds: Set<number>;
    } = $props();

    let messages = $state<Message[]>(thread.messages);
    let otherLastRead = $state<number | null>(
        thread.conversation.other_last_read_message_id,
    );
    let typing = $state(false);
    let typingTimer: ReturnType<typeof setTimeout>;
    let teardown: () => void = () => {};
    let scrollEl = $state<HTMLElement | null>(null);
    let loadingOlder = $state(false);
    let hasMore = $state(false);

    const PAGE_SIZE = 30;

    const online = $derived(onlineIds.has(thread.conversation.other.id));
    const lastMineId = $derived(
        messages.filter((m) => m.sender_id === currentUserId).at(-1)?.id ?? -1,
    );

    // Annotate each message with day-boundary, same-sender grouping, and whether
    // it's the last of a run (so only the run's tail shows a timestamp).
    const items = $derived.by(() =>
        messages.map((m, i) => {
            const prev = messages[i - 1];
            const next = messages[i + 1];
            const day = new Date(m.created_at).toDateString();
            const showDay =
                !prev || new Date(prev.created_at).toDateString() !== day;
            const grouped =
                !!prev &&
                !showDay &&
                prev.sender_id === m.sender_id &&
                m.type === 'text' &&
                prev.type === 'text';
            const showTime =
                m.type === 'system' ||
                !next ||
                next.sender_id !== m.sender_id ||
                next.type !== m.type ||
                new Date(next.created_at).toDateString() !== day;
            return { m, showDay, grouped, showTime };
        }),
    );

    function dayLabel(iso: string): string {
        const d = new Date(iso);
        const today = new Date();
        const yest = new Date();
        yest.setDate(today.getDate() - 1);
        if (d.toDateString() === today.toDateString()) return 'Today';
        if (d.toDateString() === yest.toDateString()) return 'Yesterday';
        return new Intl.DateTimeFormat(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
        }).format(d);
    }

    // Re-subscribe whenever the open conversation changes.
    $effect(() => {
        const id = thread.conversation.id;
        messages = thread.messages;
        otherLastRead = thread.conversation.other_last_read_message_id;
        // A full first page implies there may be older messages to load.
        hasMore = thread.messages.length >= PAGE_SIZE;
        loadingOlder = false;
        markRead(id);
        scrollToBottom();
        teardown();
        teardown = subscribeConversation(id, {
            onMessage: ({ message }) => {
                // Ignore our own broadcast (already shown optimistically) and
                // guard against duplicate delivery so the keyed #each never
                // sees a repeated id.
                if (message.sender_id === currentUserId) return;
                if (messages.some((m) => m.id === message.id)) return;
                const near = atBottom();
                messages = [...messages, message];
                markRead(id);
                if (near) scrollToBottom();
            },
            onRead: ({ last_read_message_id }) =>
                (otherLastRead = last_read_message_id),
            onTyping: () => {
                const near = atBottom();
                typing = true;
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => (typing = false), 3000);
                if (near) scrollToBottom();
            },
        });
        return () => teardown();
    });
    onDestroy(() => teardown());

    async function scrollToBottom() {
        await tick();
        if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
    }
    function atBottom(): boolean {
        if (!scrollEl) return true;
        return (
            scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight <
            120
        );
    }

    // Fetch the previous page of messages and prepend it, anchoring the scroll
    // so the reader's position doesn't jump.
    async function loadOlder() {
        if (!hasMore || loadingOlder || messages.length === 0 || !scrollEl)
            return;
        loadingOlder = true;
        const node = scrollEl;
        const before = messages[0].id;
        const prevHeight = node.scrollHeight;
        const prevTop = node.scrollTop;
        try {
            const res = await fetch(
                `/conversations/${thread.conversation.id}/messages?before=${before}`,
                { headers: { Accept: 'application/json' } },
            );
            if (!res.ok) return;
            const { messages: older }: { messages: Message[] } =
                await res.json();
            const existing = new Set(messages.map((m) => m.id));
            const fresh = older.filter((m) => !existing.has(m.id));
            if (fresh.length === 0) {
                hasMore = false;
                return;
            }
            messages = [...fresh, ...messages];
            if (older.length < PAGE_SIZE) hasMore = false;
            await tick();
            node.scrollTop = prevTop + (node.scrollHeight - prevHeight);
        } finally {
            loadingOlder = false;
        }
    }
    function onScroll() {
        if (scrollEl && scrollEl.scrollTop < 80) loadOlder();
    }

    async function send(body: string) {
        const optimistic: Message = {
            id: Date.now(),
            conversation_id: thread.conversation.id,
            sender_id: currentUserId,
            type: 'text',
            body,
            created_at: new Date().toISOString(),
        };
        messages = [...messages, optimistic];
        scrollToBottom();
        const res = await fetch(
            `/conversations/${thread.conversation.id}/messages`,
            {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ body }),
            },
        );
        if (res.ok) {
            const { message: saved }: { message: Message } = await res.json();
            messages = messages.map((m) =>
                m.id === optimistic.id
                    ? { ...m, id: saved.id, created_at: saved.created_at }
                    : m,
            );
        }
    }
    function onTyping() {
        whisperTyping(thread.conversation.id, currentUserId);
    }
    function markRead(id: number) {
        fetch(`/conversations/${id}/read`, {
            method: 'POST',
            headers: jsonHeaders(),
        });
    }
    function schedule() {
        router.visit(
            `/${rolePrefix}/meetings?pairing=${thread.conversation.pairing_id}`,
        );
    }
    function jsonHeaders() {
        return {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        };
    }
</script>

<div class="flex h-full w-full min-w-0 flex-col bg-surface">
    <header
        class="flex shrink-0 items-center justify-between gap-3 border-b border-line px-5 py-3"
    >
        <div class="flex min-w-0 items-center gap-3">
            <span class="relative shrink-0">
                <span
                    class="flex size-9 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent"
                >
                    {thread.conversation.other.initials}
                </span>
                {#if online}
                    <span
                        class="absolute right-0 bottom-0 size-2.5 rounded-full border-2 border-surface bg-emerald-500"
                    ></span>
                {/if}
            </span>
            <div class="min-w-0 leading-tight">
                <p class="truncate text-sm font-semibold text-ink">
                    {thread.conversation.other.name}
                </p>
                <p class="text-xs {online ? 'text-emerald-500' : 'text-faint'}">
                    {online ? 'Active now' : 'Offline'}
                </p>
            </div>
        </div>
        <button
            onclick={schedule}
            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/40"
        >
            <CalendarPlus class="size-3.5" strokeWidth={1.75} />
            Schedule a call
        </button>
    </header>

    <div
        bind:this={scrollEl}
        onscroll={onScroll}
        class="custom-scrollbar flex-1 overflow-y-auto"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-col px-5 pt-4 pb-6">
            {#if loadingOlder}
                <div class="flex justify-center py-2 text-xs text-faint">
                    Loading earlier messages…
                </div>
            {/if}
            {#each items as it (it.m.id)}
                {#if it.showDay}
                    <div class="my-4 flex items-center gap-3">
                        <span class="h-px flex-1 bg-line"></span>
                        <span class="shrink-0 text-xs font-medium text-faint">
                            {dayLabel(it.m.created_at)}
                        </span>
                        <span class="h-px flex-1 bg-line"></span>
                    </div>
                {/if}
                <MessageBubble
                    message={it.m}
                    mine={it.m.sender_id === currentUserId}
                    grouped={it.grouped}
                    showTime={it.showTime}
                    seen={it.m.id === lastMineId &&
                        otherLastRead !== null &&
                        otherLastRead >= it.m.id}
                />
            {/each}
            {#if typing}
                <div class="mt-3 flex items-center gap-1.5 px-1 text-faint">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="ml-1 text-xs"
                        >{thread.conversation.other.name} is typing</span
                    >
                </div>
            {/if}
        </div>
    </div>

    <Composer
        disabled={!thread.conversation.is_active}
        onsend={send}
        ontyping={onTyping}
    />
</div>

<style>
    .typing-dot {
        width: 6px;
        height: 6px;
        border-radius: 9999px;
        background: currentColor;
        animation: typing 1.2s infinite ease-in-out;
    }
    .typing-dot:nth-child(2) {
        animation-delay: 0.15s;
    }
    .typing-dot:nth-child(3) {
        animation-delay: 0.3s;
    }
    @keyframes typing {
        0%,
        60%,
        100% {
            opacity: 0.3;
            transform: translateY(0);
        }
        30% {
            opacity: 1;
            transform: translateY(-2px);
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .typing-dot {
            animation: none;
            opacity: 0.6;
        }
    }
</style>
