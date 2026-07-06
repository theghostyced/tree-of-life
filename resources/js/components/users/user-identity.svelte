<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { type UserRow, initials } from './types';

    let { user }: { user: UserRow } = $props();
</script>

<div class="flex min-w-0 items-center gap-2.5">
    <div
        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[11px] font-semibold text-accent"
    >
        {initials(user.name, user.email)}
    </div>
    <div class="min-w-0">
        <Link
            href={`/admin/users/${user.id}`}
            class="block truncate text-sm font-medium text-ink transition-colors hover:text-accent focus-visible:text-accent focus-visible:underline focus-visible:outline-none"
        >
            {user.name}
        </Link>
        <p class="truncate text-xs text-muted">{user.email}</p>
    </div>
</div>
