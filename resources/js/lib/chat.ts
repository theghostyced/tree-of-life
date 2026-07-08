import { echo } from '@/echo';
import type { Message } from '@/pages/messages/types';

type MessageSentPayload = {
    message: Message;
    conversation: { id: number; last_message_preview: string; last_message_at: string };
    recipient_unread_count: number;
};
type MessageReadPayload = { reader_id: number; last_read_message_id: number | null };

export function subscribeConversation(
    id: number,
    handlers: {
        onMessage: (p: MessageSentPayload) => void;
        onRead: (p: MessageReadPayload) => void;
        onTyping: (p: { user_id: number }) => void;
    },
): () => void {
    const channel = echo.private(`conversation.${id}`);
    channel.listen('.message.sent', handlers.onMessage);
    channel.listen('.message.read', handlers.onRead);
    channel.listenForWhisper('typing', handlers.onTyping);
    return () => echo.leave(`conversation.${id}`);
}

export function subscribeUser(id: number, onMessage: (p: MessageSentPayload) => void): () => void {
    echo.private(`user.${id}`).listen('.message.sent', onMessage);
    return () => echo.leave(`user.${id}`);
}

export function joinPresence(handlers: {
    here: (users: { id: number }[]) => void;
    joining: (u: { id: number }) => void;
    leaving: (u: { id: number }) => void;
}): () => void {
    echo.join('online').here(handlers.here).joining(handlers.joining).leaving(handlers.leaving);
    return () => echo.leave('online');
}

export function whisperTyping(id: number, userId: number): void {
    echo.private(`conversation.${id}`).whisper('typing', { user_id: userId });
}
