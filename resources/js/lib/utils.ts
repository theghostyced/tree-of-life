import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

// Type helpers re-exported for shadcn-svelte components (they import these from
// `@/lib/utils`).
export type {
    WithElementRef,
    WithoutChild,
    WithoutChildren,
    WithoutChildrenOrChild,
} from 'bits-ui';

/** Two-letter initials from a full name, falling back to the email's first letter. */
export function initialsOf(
    name: string | null | undefined,
    email = '',
): string {
    const trimmed = name?.trim();

    if (trimmed) {
        const parts = trimmed.split(/\s+/);
        const first = parts[0][0] ?? '';
        const last = parts.length > 1 ? (parts[parts.length - 1][0] ?? '') : '';

        return (first + last).toUpperCase();
    }

    return email.charAt(0).toUpperCase();
}
