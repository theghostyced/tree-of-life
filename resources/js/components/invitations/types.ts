import type { UserRole, InvitationStatus } from '@/types/enums';

// Role/Status come from the generated enums (source of truth: app/Enums).
export type Role = UserRole;
export type Status = InvitationStatus;

export { userRoleLabel as roleLabel } from '@/types/enums';

export type Invitation = {
    id: number;
    name: string | null;
    email: string;
    role: Role;
    status: Status;
    invitedBy: string;
    sentAt: number;
    expiresAt: number;
};

// Calm, semantic tones. Status is always paired with a dot + label so it never
// relies on colour alone (colour-blind safety).
export const statusMeta: Record<
    Status,
    { label: string; dot: string; text: string; chip: string }
> = {
    pending: {
        label: 'Pending',
        dot: 'bg-[#d3a24f]',
        text: 'text-[#e0b877]',
        chip: 'bg-[#d3a24f]/10 border-[#d3a24f]/25',
    },
    accepted: {
        label: 'Accepted',
        dot: 'bg-[#6fbe74]',
        text: 'text-[#93cf97]',
        chip: 'bg-[#6fbe74]/10 border-[#6fbe74]/25',
    },
    revoked: {
        label: 'Revoked',
        dot: 'bg-faint',
        text: 'text-muted',
        chip: 'bg-elevated border-line-strong',
    },
    expired: {
        label: 'Expired',
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

    if (d === 0) {
        return 'Today';
    }

    const unit = d === 1 ? 'day' : 'days';

    return past ? `${d} ${unit} ago` : `in ${d} ${unit}`;
}

export function initials(inv: Invitation): string {
    const base = inv.name ?? inv.email;
    const parts = base.split(/[\s.@]+/).filter(Boolean);

    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
}
