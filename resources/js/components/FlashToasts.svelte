<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { Toaster, toast } from '@/components/ui/sonner';

    // The app's single sonner Toaster plus a bridge that surfaces server-side
    // flash messages (success / error / info) as toasts. Mounted once per layout
    // so every page is covered — pages just call `toast(...)` or flash from the
    // server. A signature guard prevents re-toasting when unrelated props change.
    let lastSig = '';

    $effect(() => {
        const flash = page.props.flash ?? {};
        const sig = `${flash.success ?? ''}|${flash.error ?? ''}|${flash.info ?? ''}`;
        if (sig === '||' || sig === lastSig) return;
        lastSig = sig;
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
        if (flash.info) toast(flash.info);
    });
</script>

<Toaster position="top-center" />
