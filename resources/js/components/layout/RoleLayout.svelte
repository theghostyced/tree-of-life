<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import type { Auth } from '@/types/auth';
    import AdminLayout from './AdminLayout.svelte';
    import EntrepreneurLayout from './EntrepreneurLayout.svelte';
    import MentorLayout from './MentorLayout.svelte';

    /**
     * Wraps pages every role can open (profile, help) in that role's own
     * chrome, chosen from the shared auth prop rather than the URL.
     */
    let { title = '', children }: { title?: string; children: Snippet } =
        $props();

    const role = $derived((page.props.auth as Auth).role);
    const Layout = $derived(
        role === 'admin'
            ? AdminLayout
            : role === 'mentor'
              ? MentorLayout
              : EntrepreneurLayout,
    );
</script>

<Layout {title}>
    {@render children()}
</Layout>
