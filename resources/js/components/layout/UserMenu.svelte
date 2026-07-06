<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { LogOut, Settings, UserRound } from '@lucide/svelte';
    import * as DropdownMenu from '@/components/ui/dropdown-menu';
    import { RoleBadge } from '@/components/ui/role-badge';
    import type { Auth } from '@/types/auth';

    /**
     * Navbar avatar button + identity dropdown, fed by the shared Inertia
     * `auth` prop. Profile and Settings stay disabled until their routes
     * exist (same convention as the disabled nav links).
     */
    const auth = $derived(page.props.auth as Auth);

    const initials = $derived.by(() => {
        const name = auth.user.name?.trim();

        if (name) {
            const parts = name.split(/\s+/);
            const first = parts[0][0] ?? '';
            const last =
                parts.length > 1 ? (parts[parts.length - 1][0] ?? '') : '';

            return (first + last).toUpperCase();
        }

        return auth.user.email.charAt(0).toUpperCase();
    });

    function signOut() {
        router.post('/logout');
    }
</script>

<DropdownMenu.Root>
    <DropdownMenu.Trigger
        aria-label="Open user menu"
        class="ml-2 flex size-8 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent outline-none transition-colors hover:border-accent/50 focus-visible:ring-2 focus-visible:ring-accent/60"
    >
        {initials}
    </DropdownMenu.Trigger>
    <DropdownMenu.Content
        align="end"
        sideOffset={8}
        class="w-64 border border-line bg-surface p-1.5 text-ink ring-0"
    >
        <DropdownMenu.Label class="flex items-center gap-3 px-2.5 py-2">
            <div
                class="flex size-9 shrink-0 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
                aria-hidden="true"
            >
                {initials}
            </div>
            <div class="min-w-0 leading-tight">
                <p class="truncate text-sm font-medium text-ink">
                    {auth.user.name}
                </p>
                <p class="truncate text-xs font-normal text-faint">
                    {auth.user.email}
                </p>
                <RoleBadge role={auth.role} class="mt-1.5" />
            </div>
        </DropdownMenu.Label>
        <DropdownMenu.Separator class="bg-line" />
        <DropdownMenu.Item
            disabled
            class="px-2.5 py-2 text-muted focus:bg-elevated focus:text-ink"
        >
            <UserRound class="size-4" strokeWidth={1.75} />
            Profile
        </DropdownMenu.Item>
        <DropdownMenu.Item
            disabled
            class="px-2.5 py-2 text-muted focus:bg-elevated focus:text-ink"
        >
            <Settings class="size-4" strokeWidth={1.75} />
            Settings
        </DropdownMenu.Item>
        <DropdownMenu.Separator class="bg-line" />
        <DropdownMenu.Item
            onSelect={signOut}
            class="px-2.5 py-2 text-muted focus:bg-elevated focus:text-ink"
        >
            <LogOut class="size-4" strokeWidth={1.75} />
            Sign out
        </DropdownMenu.Item>
    </DropdownMenu.Content>
</DropdownMenu.Root>
