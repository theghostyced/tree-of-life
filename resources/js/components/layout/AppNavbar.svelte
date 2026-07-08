<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import { LayoutGrid, Search, Menu, X, LogOut } from '@lucide/svelte';
    import { fade, fly } from 'svelte/transition';
    import Logo from '@/components/Logo.svelte';
    import { RoleBadge } from '@/components/ui/role-badge';
    import { cn } from '@/lib/utils';
    import type { Auth } from '@/types/auth';
    import UserMenu from './UserMenu.svelte';
    import NotificationBell from './NotificationBell.svelte';

    /**
     * Primary top navigation, reused across roles. The link set and logo home
     * are supplied by the layout. Adapts across three stages: a hamburger drawer
     * below lg, a compact icon-only bar from lg, and full icon + label from xl.
     * Only wired routes are Inertia links.
     */
    type NavLink = {
        label: string;
        href: string;
        icon: typeof LayoutGrid;
        enabled?: boolean;
        badgeKey?: string;
    };

    let {
        links,
        home = '/',
        unread = 0,
    }: {
        links: NavLink[];
        home?: string;
        unread?: number;
    } = $props();

    const utilities = [{ label: 'Search', icon: Search }];

    const currentUrl = $derived(page.url);
    const auth = $derived(page.props.auth as Auth);

    function isActive(href: string): boolean {
        return currentUrl === href || currentUrl.startsWith(href + '/');
    }

    let mobileOpen = $state(false);
    let reduceMotion = $state(false);

    $effect(() => {
        reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
    });

    const dur = $derived(reduceMotion ? 0 : 200);

    function closeMobile() {
        mobileOpen = false;
    }

    function onKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') mobileOpen = false;
    }

    const linkClass = (active: boolean) =>
        cn(
            'flex items-center gap-2 rounded-md px-3 py-1.5 whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-accent/60',
            active
                ? 'bg-accent-soft text-accent'
                : 'text-muted hover:bg-elevated hover:text-accent',
        );

    const iconButtonClass =
        'flex size-8 items-center justify-center rounded-md outline-none transition-colors hover:bg-elevated hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/60';

    const mobileRowClass = (active: boolean) =>
        cn(
            'flex items-center gap-3 rounded-lg px-3 py-3 text-[15px] transition-colors outline-none focus-visible:ring-2 focus-visible:ring-accent/60',
            active
                ? 'bg-accent-soft font-medium text-accent'
                : 'text-muted hover:bg-elevated hover:text-ink',
        );
</script>

<svelte:window onkeydown={onKeydown} />

<header
    class="relative z-50 flex h-14 shrink-0 items-center gap-6 border-b border-line bg-surface px-4 shadow-bar sm:px-6"
>
    <Link
        href={home}
        class="shrink-0 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
    >
        <Logo size="sm" />
    </Link>

    <!-- Desktop nav: icon-only from lg, icon + label from xl -->
    <nav class="hidden items-center gap-1 text-[13px] lg:flex">
        {#each links as link (link.href)}
            {@const Icon = link.icon}
            {@const active = isActive(link.href)}
            {#if link.enabled}
                <Link
                    href={link.href}
                    aria-label={link.label}
                    title={link.label}
                    class={linkClass(active)}
                >
                    <Icon class="size-4" strokeWidth={1.75} />
                    <span class="hidden xl:inline">{link.label}</span>
                    {#if link.badgeKey === 'messages' && unread > 0}
                        <span
                            class="ml-auto flex min-w-5 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-semibold text-on-accent"
                            >{unread}</span
                        >
                    {/if}
                </Link>
            {:else}
                <button
                    type="button"
                    aria-label={link.label}
                    title={link.label}
                    class={linkClass(active)}
                >
                    <Icon class="size-4" strokeWidth={1.75} />
                    <span class="hidden xl:inline">{link.label}</span>
                </button>
            {/if}
        {/each}
    </nav>

    <!-- Right cluster: the bell shows at every size; search, role, and menu on
         desktop; the hamburger on mobile. -->
    <div class="ml-auto flex shrink-0 items-center gap-1 text-muted">
        <button
            type="button"
            aria-label="Search"
            class={cn(iconButtonClass, 'hidden lg:flex')}
        >
            <Search class="size-[18px]" strokeWidth={1.75} />
        </button>

        <NotificationBell />

        <div class="hidden items-center gap-1 lg:flex">
            <RoleBadge role={auth.role} class="ml-1" />
            <UserMenu />
        </div>

        <button
            type="button"
            onclick={() => (mobileOpen = !mobileOpen)}
            aria-label={mobileOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={mobileOpen}
            aria-controls="mobile-nav"
            class="-mr-1 flex size-11 items-center justify-center rounded-md outline-none transition-colors hover:bg-elevated hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/60 lg:hidden"
        >
            {#if mobileOpen}
                <X class="size-5" strokeWidth={1.75} />
            {:else}
                <Menu class="size-5" strokeWidth={1.75} />
            {/if}
        </button>
    </div>
</header>

{#if mobileOpen}
    <div class="lg:hidden">
        <button
            type="button"
            aria-label="Close menu"
            onclick={closeMobile}
            transition:fade={{ duration: dur }}
            class="fixed inset-0 top-14 z-30 bg-black/50"
        ></button>

        <div
            id="mobile-nav"
            transition:fly={{ y: -8, duration: dur }}
            class="fixed inset-x-0 top-14 z-40 max-h-[calc(100vh-3.5rem)] overflow-y-auto border-b border-line bg-surface shadow-lg"
        >
            <nav class="flex flex-col gap-1 p-3">
                {#each links as link (link.href)}
                    {@const Icon = link.icon}
                    {@const active = isActive(link.href)}
                    {#if link.enabled}
                        <Link
                            href={link.href}
                            onclick={closeMobile}
                            class={mobileRowClass(active)}
                        >
                            <Icon class="size-5" strokeWidth={1.75} />
                            {link.label}
                            {#if link.badgeKey === 'messages' && unread > 0}
                                <span
                                    class="ml-auto flex min-w-5 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-semibold text-on-accent"
                                    >{unread}</span
                                >
                            {/if}
                        </Link>
                    {:else}
                        <button
                            type="button"
                            onclick={closeMobile}
                            class={mobileRowClass(active)}
                        >
                            <Icon class="size-5" strokeWidth={1.75} />
                            {link.label}
                        </button>
                    {/if}
                {/each}
            </nav>

            <div class="flex flex-col gap-1 border-t border-line p-3">
                {#each utilities as util (util.label)}
                    {@const Icon = util.icon}
                    <button
                        type="button"
                        onclick={closeMobile}
                        class={mobileRowClass(false)}
                    >
                        <Icon class="size-5" strokeWidth={1.75} />
                        {util.label}
                    </button>
                {/each}

                <div class="mt-1 flex items-center gap-3 px-3 py-3">
                    <div
                        class="flex size-9 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
                        aria-hidden="true"
                    >
                        {auth.user.name
                            .trim()
                            .split(/\s+/)
                            .map((part) => part[0])
                            .slice(0, 2)
                            .join('')
                            .toUpperCase() ||
                            auth.user.email.charAt(0).toUpperCase()}
                    </div>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium text-ink">
                            {auth.user.name}
                        </p>
                        <p class="truncate text-xs text-faint">
                            {auth.user.email}
                        </p>
                    </div>
                    <RoleBadge role={auth.role} class="ml-auto shrink-0" />
                </div>

                <button
                    type="button"
                    onclick={() => router.post('/logout')}
                    class={mobileRowClass(false)}
                >
                    <LogOut class="size-5" strokeWidth={1.75} />
                    Sign out
                </button>
            </div>
        </div>
    </div>
{/if}
