<script lang="ts">
    import { cn } from '@/lib/utils';

    let {
        value,
        tone = 'muted',
    }: { value: string; tone?: 'ink' | 'muted' | 'faint' } = $props();
</script>

<span
    class={cn(
        'text-xs whitespace-nowrap',
        tone === 'ink' && 'text-ink',
        tone === 'muted' && 'text-muted',
        tone === 'faint' && 'text-faint',
    )}
>
    {value}
</span>
