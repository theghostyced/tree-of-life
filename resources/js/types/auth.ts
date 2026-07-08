export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

import type { UserRole } from './enums';

export type { UserRole };

export type Auth = {
    user: User;
    /** Role of the authenticated viewer (mirrors {@link \App\Enums\UserRole}). */
    role: UserRole;
    /** Total unread messages across the viewer's conversations. */
    unreadMessages: number;
};
