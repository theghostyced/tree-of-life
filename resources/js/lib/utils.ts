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
