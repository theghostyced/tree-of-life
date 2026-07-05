<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        LayoutGrid,
        Users,
        Calendar,
        Clock,
        ClipboardList,
    } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppNavbar from './AppNavbar.svelte';

    /**
     * Mentor shell: same top-navbar chrome as the admin and entrepreneur,
     * tilted to the mentor's areas. Navigable regardless of onboarding status.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();

    const links = [
        { label: 'Dashboard', href: '/mentor/dashboard', icon: LayoutGrid, enabled: true },
        { label: 'Mentees', href: '/mentor/mentees', icon: Users },
        { label: 'Sessions', href: '/mentor/sessions', icon: Calendar },
        { label: 'Availability', href: '/mentor/availability', icon: Clock },
        { label: 'Reviews', href: '/mentor/reviews', icon: ClipboardList },
    ];
</script>

<AppHead {title} />

<div
    class="flex h-screen flex-col overflow-hidden bg-canvas font-sans text-ink"
>
    <AppNavbar {links} home="/mentor/dashboard" />
    <main class="custom-scrollbar flex flex-1 flex-col overflow-y-auto">
        {@render children()}
    </main>
</div>
