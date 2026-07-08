<script lang="ts">
    type UserSummary = {
        id: number;
        name: string;
        initials: string;
        role: string;
    };

    type ConversationSummary = {
        id: number;
        other: UserSummary;
        last_message_preview: string | null;
        last_message_at: string | null;
        unread_count: number;
        is_active: boolean;
    };

    type Message = {
        id: number;
        conversation_id: number;
        sender_id: number | null;
        type: string;
        body: string | null;
        created_at: string;
    };

    type Thread = {
        conversation: {
            id: number;
            other: UserSummary;
            is_active: boolean;
            pairing_id: number;
            other_last_read_message_id: number | null;
        };
        messages: Message[];
    };

    let {
        conversations = [],
        selectedId = null,
        thread = null,
        currentUserId,
    }: {
        conversations: ConversationSummary[];
        selectedId: number | null;
        thread: Thread | null;
        currentUserId: number;
    } = $props();
</script>

<section>
    <h1>Messages</h1>
    <ul>
        {#each conversations as conversation (conversation.id)}
            <li>
                <span>{conversation.other.name}</span>
                {#if conversation.unread_count > 0}
                    <span aria-label="unread">{conversation.unread_count}</span>
                {/if}
            </li>
        {/each}
    </ul>

    {#if thread}
        <div>
            {#each thread.messages as message (message.id)}
                <p class:mine={message.sender_id === currentUserId}>{message.body}</p>
            {/each}
        </div>
    {/if}
</section>
