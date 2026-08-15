import { echo } from '@/echo';
import type { Message } from '@/pages/messages/types';

/** Realtime is optional; without it these subscriptions are no-ops. */
const noop = (): void => {};

type MessageSentPayload = {
    message: Message;
    conversation: {
        id: number;
        last_message_preview: string;
        last_message_at: string;
    };
    recipient_unread_count: number;
};
type MessageReadPayload = {
    reader_id: number;
    last_read_message_id: number | null;
};

export function subscribeConversation(
    id: number,
    handlers: {
        onMessage: (p: MessageSentPayload) => void;
        onRead: (p: MessageReadPayload) => void;
        onTyping: (p: { user_id: number }) => void;
    },
): () => void {
    const client = echo();

    if (!client) {
        return noop;
    }

    const channel = client.private(`conversation.${id}`);
    channel.listen('.message.sent', handlers.onMessage);
    channel.listen('.message.read', handlers.onRead);
    channel.listenForWhisper('typing', handlers.onTyping);
    return () => client.leave(`conversation.${id}`);
}

export function subscribeUser(
    id: number,
    onMessage: (p: MessageSentPayload) => void,
): () => void {
    const client = echo();

    if (!client) {
        return noop;
    }

    client.private(`user.${id}`).listen('.message.sent', onMessage);
    return () => client.leave(`user.${id}`);
}

export type NotificationBroadcast = {
    id: string;
    type: string;
    category: App.Enums.NotificationCategory;
    title: string;
    body: string;
    actions: { label: string; url: string }[];
    illustration: string | null;
};

/** Listen for Laravel broadcast notifications on the user's private channel. */
export function subscribeNotifications(
    userId: number,
    onNotification: (p: NotificationBroadcast) => void,
): () => void {
    const client = echo();

    if (!client) {
        return noop;
    }

    client.private(`App.Models.User.${userId}`).notification(onNotification);
    return () => client.leave(`App.Models.User.${userId}`);
}

export function joinPresence(handlers: {
    here: (users: { id: number }[]) => void;
    joining: (u: { id: number }) => void;
    leaving: (u: { id: number }) => void;
}): () => void {
    const client = echo();

    if (!client) {
        return noop;
    }

    client
        .join('online')
        .here(handlers.here)
        .joining(handlers.joining)
        .leaving(handlers.leaving);
    return () => client.leave('online');
}

export function whisperTyping(id: number, userId: number): void {
    echo()
        ?.private(`conversation.${id}`)
        .whisper('typing', { user_id: userId });
}
