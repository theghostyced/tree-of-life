<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { CircleHelp, LogOut, Settings, UserRound } from '@lucide/svelte';
    import * as DropdownMenu from '@/components/ui/dropdown-menu';
    import { RoleBadge } from '@/components/ui/role-badge';
    import type { Auth } from '@/types/auth';
    import { initialsOf } from '@/lib/utils';

    /**
     * Navbar avatar button + identity dropdown, fed by the shared Inertia
     * `auth` prop. Settings stays disabled until its route exists (same
     * convention as the disabled nav links).
     */
    const auth = $derived(page.props.auth as Auth);

    const initials = $derived(initialsOf(auth.user.name, auth.user.email));

    function signOut() {
        router.post('/logout');
    }
</script>

<DropdownMenu.Root>
    <DropdownMenu.Trigger
        aria-label="Open user menu"
        class="ml-2 flex size-8 items-center justify-center overflow-hidden rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent outline-none transition-colors hover:border-accent/50 focus-visible:ring-2 focus-visible:ring-accent/60"
    >
        {#if auth.user.avatarUrl}
            <img
                src={auth.user.avatarUrl}
                alt=""
                class="size-full object-cover"
            />
        {:else}
            {initials}
        {/if}
    </DropdownMenu.Trigger>
    <DropdownMenu.Content
        align="end"
        sideOffset={8}
        class="w-64 border border-line bg-surface p-1.5 text-ink ring-0"
    >
        <DropdownMenu.Label class="flex items-center gap-3 px-2.5 py-2">
            <div
                class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
                aria-hidden="true"
            >
                {#if auth.user.avatarUrl}
                    <img
                        src={auth.user.avatarUrl}
                        alt=""
                        class="size-full object-cover"
                    />
                {:else}
                    {initials}
                {/if}
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
            onSelect={() => router.visit('/profile')}
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
            onSelect={() => router.visit('/help/how-it-works')}
            class="px-2.5 py-2 text-muted focus:bg-elevated focus:text-ink"
        >
            <CircleHelp class="size-4" strokeWidth={1.75} />
            How Tree of Life works
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
