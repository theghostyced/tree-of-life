<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppNavbar from './AppNavbar.svelte';

    /**
     * Reusable admin shell: dark themed, Inter typeface, top navbar and a
     * scrollable content region. Wrap any admin page in this.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();
</script>

<AppHead {title}>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    />
</AppHead>

<div
    class="flex h-screen flex-col overflow-hidden bg-[#0A0C0B] text-[#E2E8E4]"
    style="font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;"
>
    <AppNavbar />
    <main class="custom-scrollbar flex flex-1 flex-col overflow-y-auto">
        {@render children()}
    </main>
</div>
