<script lang="ts">
    import type { Snippet } from 'svelte';
    import { LayoutGrid, UserRound, Calendar, FileText } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
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
        { label: 'My mentor', href: '/entrepreneur/mentor', icon: UserRound },
        { label: 'Meetings', href: '/entrepreneur/meetings', icon: Calendar },
        { label: 'Reports', href: '/entrepreneur/reports', icon: FileText },
    ];
</script>

<AppHead {title} />

<div
    class="flex h-screen flex-col overflow-hidden bg-canvas font-sans text-ink"
>
    <AppNavbar {links} home="/entrepreneur/dashboard" />
    <main class="custom-scrollbar flex flex-1 flex-col overflow-y-auto">
        {@render children()}
    </main>
</div>
