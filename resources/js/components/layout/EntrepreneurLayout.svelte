<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        LayoutGrid,
        FolderKanban,
        ClipboardList,
        Flag,
        Users,
    } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppNavbar from './AppNavbar.svelte';

    /**
     * Entrepreneur shell: same top-navbar chrome as the admin, tilted to the
     * entrepreneur's areas. Navigable regardless of onboarding status.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();

    const links = [
        { label: 'Dashboard', href: '/entrepreneur/dashboard', icon: LayoutGrid, enabled: true },
        { label: 'Programs', href: '/entrepreneur/programs', icon: FolderKanban },
        { label: 'Applications', href: '/entrepreneur/applications', icon: ClipboardList },
        { label: 'Milestones', href: '/entrepreneur/milestones', icon: Flag },
        { label: 'Mentorship', href: '/entrepreneur/mentorship', icon: Users },
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
