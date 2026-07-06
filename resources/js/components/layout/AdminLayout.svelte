<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        LayoutGrid,
        Users,
        Calendar,
        FileText,
        Send,
    } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppNavbar from './AppNavbar.svelte';

    /**
     * Admin shell: dark themed, top navbar with the admin link set, and a
     * scrollable content region. Wrap any admin page in this.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();

    const links = [
        {
            label: 'Dashboard',
            href: '/admin/dashboard',
            icon: LayoutGrid,
            enabled: true,
        },
        { label: 'Users', href: '/admin/users', icon: Users, enabled: true },
        { label: 'Meetings', href: '/admin/meetings', icon: Calendar },
        { label: 'Reports', href: '/admin/reports', icon: FileText },
        {
            label: 'Invitations',
            href: '/admin/invitations',
            icon: Send,
            enabled: true,
        },
    ];
</script>

<AppHead {title} />

<div
    class="flex h-screen flex-col overflow-hidden bg-canvas font-sans text-ink"
>
    <AppNavbar {links} home="/admin/dashboard" />
    <main class="custom-scrollbar flex flex-1 flex-col overflow-y-auto">
        {@render children()}
    </main>
</div>
