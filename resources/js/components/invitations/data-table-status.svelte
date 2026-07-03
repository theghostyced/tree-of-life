<script lang="ts">
    import { type Status, statusMeta } from './types';
    import { cn } from '@/lib/utils';

    let { status }: { status: Status } = $props();
    const meta = $derived(statusMeta[status]);
</script>

<span
    class={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
        meta.chip,
        meta.text,
    )}
>
    <span class={cn('size-1.5 rounded-full', meta.dot)}></span>
    {meta.label}
</span>
