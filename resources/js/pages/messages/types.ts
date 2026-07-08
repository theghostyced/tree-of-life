export type ChatUser = { id: number; name: string; initials: string; role: string };

export type Message = {
    id: number;
    conversation_id: number;
    sender_id: number | null;
    type: 'text' | 'system';
    body: string;
    created_at: string;
};

export type ConversationSummary = {
    id: number;
    other: ChatUser;
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    is_active: boolean;
};

export type Thread = {
    conversation: {
        id: number;
        other: ChatUser;
        is_active: boolean;
        pairing_id: number;
        other_last_read_message_id: number | null;
    };
    messages: Message[];
};

export type MessagePageProps = {
    currentUserId: number;
    conversations: ConversationSummary[];
    selectedId: number | null;
    thread: Thread | null;
};
