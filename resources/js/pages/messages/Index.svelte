<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import { subscribeUser, joinPresence } from '@/lib/chat';
    import type { MessagePageProps } from './types';
    import ConversationList from './ConversationList.svelte';
    import ThreadPane from './Thread.svelte';

    let { currentUserId, conversations, selectedId, thread }: MessagePageProps = $props();

    const rolePrefix = window.location.pathname.split('/')[1]; // 'entrepreneur' | 'mentor'
    const Layout = rolePrefix === 'mentor' ? MentorLayout : EntrepreneurLayout;

    let list = $state(conversations);
    let onlineIds = $state(new Set<number>());
    let userTeardown: () => void = () => {};
    let presenceTeardown: () => void = () => {};

    $effect(() => { list = conversations; });

    onMount(() => {
        userTeardown = subscribeUser(currentUserId, ({ message, conversation, recipient_unread_count }) => {
            list = list.map((c) => c.id === conversation.id
                ? { ...c, last_message_preview: conversation.last_message_preview, last_message_at: conversation.last_message_at,
                    unread_count: c.id === selectedId ? 0 : recipient_unread_count }
                : c).sort((a, b) => (b.last_message_at ?? '').localeCompare(a.last_message_at ?? ''));
        });
        presenceTeardown = joinPresence({
            here: (users) => (onlineIds = new Set(users.map((u) => u.id))),
            joining: (u) => (onlineIds = new Set([...onlineIds, u.id])),
            leaving: (u) => { const n = new Set(onlineIds); n.delete(u.id); onlineIds = n; },
        });
    });
    onDestroy(() => { userTeardown(); presenceTeardown(); });
</script>

<Layout title="Messages">
    <div class="mx-auto flex h-[calc(100vh-3.5rem)] w-full max-w-7xl">
        <aside class="flex w-full max-w-sm flex-col border-r border-line bg-panel {thread ? 'hidden md:flex' : 'flex'}">
            <div class="border-b border-line px-4 py-4"><h1 class="text-lg font-semibold text-ink">Messages</h1></div>
            <ConversationList conversations={list} {selectedId} {onlineIds} {rolePrefix} />
        </aside>
        <section class="flex-1 {thread ? 'flex' : 'hidden md:flex'}">
            {#if thread}
                {#key thread.conversation.id}
                    <ThreadPane {thread} {currentUserId} {rolePrefix} />
                {/key}
            {:else}
                <div class="flex flex-1 items-center justify-center text-sm text-muted">Select a conversation to start messaging.</div>
            {/if}
        </section>
    </div>
</Layout>
