<script lang="ts">
    import Logo from '@/components/Logo.svelte';

    /** Auth-screen brand lockup — the shared Logo at display size. */
    let { name = 'Tree Of Life Fund' }: { name?: string } = $props();
</script>

<Logo size="lg" {name} />
