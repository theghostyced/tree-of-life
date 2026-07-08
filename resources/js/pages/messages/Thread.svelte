<script lang="ts">
    import { onDestroy } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { CalendarPlus } from '@lucide/svelte';
    import { subscribeConversation, whisperTyping } from '@/lib/chat';
    import type { Message, Thread } from './types';
    import MessageBubble from './MessageBubble.svelte';
    import Composer from './Composer.svelte';

    let { thread, currentUserId, rolePrefix }:
        { thread: Thread; currentUserId: number; rolePrefix: string } = $props();

    let messages = $state<Message[]>(thread.messages);
    let otherLastRead = $state<number | null>(thread.conversation.other_last_read_message_id);
    let typing = $state(false);
    let typingTimer: ReturnType<typeof setTimeout>;
    let teardown: () => void = () => {};

    // Re-subscribe whenever the open conversation changes.
    $effect(() => {
        const id = thread.conversation.id;
        messages = thread.messages;
        otherLastRead = thread.conversation.other_last_read_message_id;
        markRead(id);
        teardown();
        teardown = subscribeConversation(id, {
            onMessage: ({ message }) => {
                if (message.sender_id !== currentUserId) { messages = [...messages, message]; markRead(id); }
            },
            onRead: ({ last_read_message_id }) => (otherLastRead = last_read_message_id),
            onTyping: () => { typing = true; clearTimeout(typingTimer); typingTimer = setTimeout(() => (typing = false), 3000); },
        });
        return () => teardown();
    });
    onDestroy(() => teardown());

    async function send(body: string) {
        const optimistic: Message = { id: Date.now(), conversation_id: thread.conversation.id, sender_id: currentUserId, type: 'text', body, created_at: new Date().toISOString() };
        messages = [...messages, optimistic];
        const res = await fetch(`/conversations/${thread.conversation.id}/messages`, {
            method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ body }),
        });
        if (res.ok) {
            const { message: saved }: { message: Message } = await res.json();
            messages = messages.map((m) => m.id === optimistic.id ? { ...m, id: saved.id, created_at: saved.created_at } : m);
        }
    }
    function onTyping() { whisperTyping(thread.conversation.id, currentUserId); }
    function markRead(id: number) { fetch(`/conversations/${id}/read`, { method: 'POST', headers: jsonHeaders() }); }
    function schedule() { router.visit(`/${rolePrefix}/meetings?mentor=${thread.conversation.pairing_id}`); }
    function jsonHeaders() {
        return { 'Content-Type': 'application/json', Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') };
    }
    const lastMineId = $derived(messages.filter((m) => m.sender_id === currentUserId).at(-1)?.id ?? -1);
</script>

<div class="flex h-full flex-col">
    <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <div class="flex items-center gap-3">
            <div class="flex size-9 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent">{thread.conversation.other.initials}</div>
            <p class="text-sm font-semibold text-ink">{thread.conversation.other.name}</p>
        </div>
        <button onclick={schedule} class="inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-1.5 text-xs font-medium text-ink transition-colors hover:bg-elevated">
            <CalendarPlus class="size-3.5" strokeWidth={1.75} /> Schedule a call
        </button>
    </header>

    <div class="flex flex-1 flex-col gap-1.5 overflow-y-auto px-4 py-4">
        {#each messages as m (m.id)}
            <MessageBubble message={m} mine={m.sender_id === currentUserId} seen={m.id === lastMineId && otherLastRead !== null && otherLastRead >= m.id} />
        {/each}
        {#if typing}<p class="px-1 text-xs text-faint">{thread.conversation.other.name} is typing…</p>{/if}
    </div>

    <Composer disabled={!thread.conversation.is_active} onsend={send} ontyping={onTyping} />
</div>
