<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import {
        LayoutGrid,
        ClipboardList,
        Award,
        Flag,
        FolderKanban,
        Users,
        Send,
        Search,
        Bell,
        Settings,
    } from '@lucide/svelte';
    import Logo from '@/components/Logo.svelte';
    import { cn } from '@/lib/utils';

    /**
     * Primary admin navigation. Replaces the design's sidebar with a top navbar,
     * tailored to Tolfund's admin surfaces. Only routes that exist are wired as
     * Inertia links; the rest render as placeholders until their views are built.
     */
    type NavLink = {
        label: string;
        href: string;
        icon: typeof LayoutGrid;
        enabled?: boolean;
    };

    const links: NavLink[] = [
        { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutGrid, enabled: true },
        { label: 'Applications', href: '/admin/applications', icon: ClipboardList },
        { label: 'Awards', href: '/admin/awards', icon: Award },
        { label: 'Milestones', href: '/admin/milestones', icon: Flag },
        { label: 'Programs', href: '/admin/programs', icon: FolderKanban },
        { label: 'Users', href: '/admin/users', icon: Users },
        { label: 'Invitations', href: '/admin/invitations', icon: Send },
    ];

    const currentUrl = $derived(page.url);

    function isActive(href: string): boolean {
        return currentUrl === href || currentUrl.startsWith(href + '/');
    }

    const linkClass = (active: boolean) =>
        cn(
            'flex items-center gap-2 whitespace-nowrap rounded-md px-3 py-1.5 outline-none transition-colors focus-visible:ring-2 focus-visible:ring-accent/60',
            active
                ? 'bg-accent-soft text-accent'
                : 'text-muted hover:bg-elevated hover:text-accent',
        );

    const iconButtonClass =
        'flex size-8 items-center justify-center rounded-md outline-none transition-colors hover:bg-elevated hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/60';
</script>

<header
    class="flex h-14 shrink-0 items-center gap-6 border-b border-line bg-surface px-6 shadow-bar"
>
    <Link
        href="/admin/dashboard"
        class="shrink-0 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
    >
        <Logo size="sm" />
    </Link>

    <nav
        class="custom-scrollbar flex items-center gap-1 overflow-x-auto text-[13px]"
    >
        {#each links as link (link.href)}
            {@const Icon = link.icon}
            {#if link.enabled}
                <Link href={link.href} class={linkClass(isActive(link.href))}>
                    <Icon class="size-4" strokeWidth={1.75} />
                    {link.label}
                </Link>
            {:else}
                <button type="button" class={linkClass(isActive(link.href))}>
                    <Icon class="size-4" strokeWidth={1.75} />
                    {link.label}
                </button>
            {/if}
        {/each}
    </nav>

    <div class="ml-auto flex shrink-0 items-center gap-1 text-muted">
        <button type="button" aria-label="Search" class={iconButtonClass}>
            <Search class="size-[18px]" strokeWidth={1.75} />
        </button>
        <button type="button" aria-label="Notifications" class={iconButtonClass}>
            <Bell class="size-[18px]" strokeWidth={1.75} />
        </button>
        <button type="button" aria-label="Settings" class={iconButtonClass}>
            <Settings class="size-[18px]" strokeWidth={1.75} />
        </button>
        <div
            class="ml-2 flex size-8 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
            aria-hidden="true"
        >
            AD
        </div>
    </div>
</header>
