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

export type UserRole = 'admin' | 'mentor' | 'entrepreneur';

export type Auth = {
    user: User;
    /**
     * Role of the authenticated viewer. Placeholder until a backed
     * `UserRole` enum + `role` column land server-side.
     */
    role: UserRole;
};
