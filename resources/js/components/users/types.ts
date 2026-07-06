import type { UserRole, AccountStatus } from '@/types/enums';

// Role/Status come from the generated enums (source of truth: app/Enums).
export type { UserRole, AccountStatus };
export { userRoleLabel as roleLabel } from '@/types/enums';

export type UserRow = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    status: AccountStatus;
    verified: boolean;
    joinedAt: number;
};

// Status paired with a dot + label so it never relies on colour alone.
export const statusMeta: Record<
    AccountStatus,
    { label: string; dot: string; text: string; chip: string }
> = {
    approved: {
        label: 'Active',
        dot: 'bg-[#6fbe74]',
        text: 'text-[#93cf97]',
        chip: 'bg-[#6fbe74]/10 border-[#6fbe74]/25',
    },
    deactivated: {
        label: 'Deactivated',
        dot: 'bg-[#cf8b8b]',
        text: 'text-[#d7a1a1]',
        chip: 'bg-[#cf8b8b]/8 border-[#cf8b8b]/20',
    },
};

const DAY = 86_400_000;

export function relative(ms: number): string {
    const diff = ms - Date.now();
    const past = diff < 0;
    const d = Math.round(Math.abs(diff) / DAY);
    if (d === 0) return 'today';
    const unit = d === 1 ? 'day' : 'days';
    return past ? `${d} ${unit} ago` : `in ${d} ${unit}`;
}

export function initials(name: string, email: string): string {
    const base = name || email;
    const parts = base.split(/[\s.@]+/).filter(Boolean);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
}
