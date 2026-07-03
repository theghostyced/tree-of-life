<script lang="ts">
    import { Toaster as Sonner, type ToasterProps } from 'svelte-sonner';

    let { ...restProps }: ToasterProps = $props();
</script>

<Sonner
    theme="dark"
    richColors
    closeButton
    class="toaster group"
    {...restProps}
/>
