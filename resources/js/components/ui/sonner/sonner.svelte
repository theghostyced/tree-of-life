<script lang="ts">
    import { Toaster as Sonner, type ToasterProps } from 'svelte-sonner';

    let { ...restProps }: ToasterProps = $props();
</script>

<Sonner
    theme="dark"
    closeButton
    class="toaster group"
    toastOptions={{
        classes: {
            toast: '!bg-panel !text-ink !rounded-xl !border !shadow-lg !text-[13px]',
            title: '!font-medium !text-ink',
            description: '!text-muted',
            closeButton: '!bg-elevated !text-muted !border-line hover:!text-ink',
            actionButton: '!bg-accent !text-on-accent !rounded-lg',
            cancelButton: '!bg-elevated !text-muted !rounded-lg',
            default: '!border-accent/40 [&_[data-icon]]:!text-accent',
            success: '!border-accent/40 [&_[data-icon]]:!text-accent',
            info: '!border-accent/40 [&_[data-icon]]:!text-accent',
            error: '!border-danger/40 [&_[data-icon]]:!text-danger',
            warning: '!border-accent-orange/40 [&_[data-icon]]:!text-accent-orange',
        },
    }}
    {...restProps}
/>
