<script lang="ts">
    import { onMount, type Snippet } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import {
        LayoutGrid,
        Users,
        Calendar,
        Clock,
        MessageSquare,
        FileText,
    } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { subscribeUser } from '@/lib/chat';
    import FlashToasts from '@/components/FlashToasts.svelte';
    import AppNavbar from './AppNavbar.svelte';

    /**
     * Mentor shell: same top-navbar chrome as the admin and entrepreneur,
     * tilted to the mentor's areas. Navigable regardless of onboarding status.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();

    const links = [
        {
            label: 'Dashboard',
            href: '/mentor/dashboard',
            icon: LayoutGrid,
            enabled: true,
        },
        {
            label: 'Mentees',
            href: '/mentor/mentees',
            icon: Users,
            enabled: true,
        },
        {
            label: 'Meetings',
            href: '/mentor/meetings',
            icon: Calendar,
            enabled: true,
        },
        {
            label: 'Availability',
            href: '/mentor/availability',
            icon: Clock,
            enabled: true,
        },
        {
            label: 'Messages',
            href: '/mentor/messages',
            icon: MessageSquare,
            badgeKey: 'messages',
            enabled: true,
        },
        { label: 'Reports', href: '/mentor/reports', icon: FileText },
    ];

    // Live global unread badge: seeded from the shared prop, incremented by the
    // user broadcast channel, and cleared while viewing a messages route.
    let unread = $state(page.props.auth.unreadMessages ?? 0);

    onMount(() => subscribeUser(page.props.auth.user.id, () => (unread += 1)));

    $effect(() => {
        if (page.url.includes('/messages')) unread = 0;
    });
</script>

<AppHead {title} />

<div
    class="flex h-screen flex-col overflow-hidden bg-canvas font-sans text-ink"
>
    <AppNavbar {links} {unread} home="/mentor/dashboard" />
    <main class="custom-scrollbar flex flex-1 flex-col overflow-y-auto">
        {@render children()}
    </main>
    <FlashToasts />
</div>
