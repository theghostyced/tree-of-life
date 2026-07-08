<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { MessagesSquare, Search } from '@lucide/svelte';
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import { subscribeUser, joinPresence } from '@/lib/chat';
    import type { MessagePageProps } from './types';
    import ConversationList from './ConversationList.svelte';
    import ThreadPane from './Thread.svelte';

    let { currentUserId, conversations, selectedId, thread }: MessagePageProps =
        $props();

    const rolePrefix = window.location.pathname.split('/')[1]; // 'entrepreneur' | 'mentor'
    const Layout = rolePrefix === 'mentor' ? MentorLayout : EntrepreneurLayout;

    let list = $state(conversations);
    let onlineIds = $state(new Set<number>());
    let query = $state('');
    let userTeardown: () => void = () => {};
    let presenceTeardown: () => void = () => {};

    $effect(() => {
        list = conversations;
    });

    const filtered = $derived.by(() => {
        const q = query.trim().toLowerCase();
        if (!q) return list;
        return list.filter(
            (c) =>
                c.other.name.toLowerCase().includes(q) ||
                (c.last_message_preview ?? '').toLowerCase().includes(q),
        );
    });

    onMount(() => {
        userTeardown = subscribeUser(
            currentUserId,
            ({ conversation, recipient_unread_count }) => {
                list = list
                    .map((c) =>
                        c.id === conversation.id
                            ? {
                                  ...c,
                                  last_message_preview:
                                      conversation.last_message_preview,
                                  last_message_at: conversation.last_message_at,
                                  unread_count:
                                      c.id === selectedId
                                          ? 0
                                          : recipient_unread_count,
                              }
                            : c,
                    )
                    .sort((a, b) =>
                        (b.last_message_at ?? '').localeCompare(
                            a.last_message_at ?? '',
                        ),
                    );
            },
        );
        presenceTeardown = joinPresence({
            here: (users) => (onlineIds = new Set(users.map((u) => u.id))),
            joining: (u) => (onlineIds = new Set([...onlineIds, u.id])),
            leaving: (u) => {
                const n = new Set(onlineIds);
                n.delete(u.id);
                onlineIds = n;
            },
        });
    });
    onDestroy(() => {
        userTeardown();
        presenceTeardown();
    });
</script>

<Layout title="Messages">
    <div class="flex h-[calc(100vh-3.5rem)] w-full overflow-hidden">
        <aside
            class="flex w-full shrink-0 flex-col border-r border-line bg-panel md:w-[360px] {thread
                ? 'hidden md:flex'
                : 'flex'}"
        >
            <div class="shrink-0 border-b border-line px-4 pt-4 pb-3">
                <h1 class="text-lg font-semibold text-ink">Messages</h1>
                <div class="relative mt-3">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-faint"
                        strokeWidth={1.75}
                    />
                    <input
                        bind:value={query}
                        type="text"
                        placeholder="Search conversations"
                        class="h-9 w-full rounded-lg border border-line bg-surface pr-3 pl-9 text-sm text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus:ring-2 focus:ring-accent/25"
                    />
                </div>
            </div>
            <ConversationList
                conversations={filtered}
                {selectedId}
                {onlineIds}
                {rolePrefix}
            />
        </aside>

        <section class="min-w-0 flex-1 {thread ? 'flex' : 'hidden md:flex'}">
            {#if thread}
                {#key thread.conversation.id}
                    <ThreadPane
                        {thread}
                        {currentUserId}
                        {rolePrefix}
                        {onlineIds}
                    />
                {/key}
            {:else}
                <div
                    class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center"
                >
                    <div
                        class="flex size-14 items-center justify-center rounded-2xl bg-elevated text-muted"
                    >
                        <MessagesSquare class="size-7" strokeWidth={1.5} />
                    </div>
                    <div>
                        <p class="text-base font-semibold text-ink">
                            Your conversations
                        </p>
                        <p class="mt-1 max-w-xs text-sm text-muted">
                            Choose a mentor from the list to read your history
                            and pick up where you left off.
                        </p>
                    </div>
                </div>
            {/if}
        </section>
    </div>
</Layout>
