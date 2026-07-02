<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppNavbar from './AppNavbar.svelte';

    /**
     * Reusable admin shell: dark themed, Instrument Sans typeface, top navbar
     * and a scrollable content region. Wrap any admin page in this.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();
</script>

<AppHead {title} />

<div
    class="flex h-screen flex-col overflow-hidden bg-canvas font-sans text-ink"
>
    <AppNavbar />
    <main class="custom-scrollbar flex flex-1 flex-col overflow-y-auto">
        {@render children()}
    </main>
</div>
