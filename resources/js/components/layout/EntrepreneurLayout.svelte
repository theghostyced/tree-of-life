<script lang="ts">
    import { onMount, type Snippet } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import {
        LayoutGrid,
        UserRound,
        Calendar,
        MessageSquare,
        FileText,
    } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { subscribeUser } from '@/lib/chat';
    import AppNavbar from './AppNavbar.svelte';

    /**
     * Entrepreneur shell: same top-navbar chrome as the admin, tilted to the
     * entrepreneur's areas. Navigable regardless of onboarding status.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();

    const links = [
        {
            label: 'Dashboard',
            href: '/entrepreneur/dashboard',
            icon: LayoutGrid,
            enabled: true,
        },
        {
            label: 'Mentors',
            href: '/entrepreneur/mentors',
            icon: UserRound,
            enabled: true,
        },
        {
            label: 'Meetings',
            href: '/entrepreneur/meetings',
            icon: Calendar,
            enabled: true,
        },
        {
            label: 'Messages',
            href: '/entrepreneur/messages',
            icon: MessageSquare,
            badgeKey: 'messages',
            enabled: true,
        },
        { label: 'Reports', href: '/entrepreneur/reports', icon: FileText },
    ];

    // Live global unread badge: seeded from the shared prop, incremented by the
    // user broadcast channel, and cleared while viewing a messages route.
    let unread = $state(page.props.auth.unreadMessages ?? 0);

    onMount(() =>
        subscribeUser(page.props.auth.user.id, () => (unread += 1)),
    );

    $effect(() => {
        if (page.url.includes('/messages')) unread = 0;
    });
</script>

<AppHead {title} />

<div
    class="flex h-screen flex-col overflow-hidden bg-canvas font-sans text-ink"
>
    <AppNavbar {links} {unread} home="/entrepreneur/dashboard" />
    <main class="custom-scrollbar flex flex-1 flex-col overflow-y-auto">
        {@render children()}
    </main>
</div>
