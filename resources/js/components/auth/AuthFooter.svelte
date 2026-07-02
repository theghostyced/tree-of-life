<script lang="ts">
    import { cn } from '@/lib/utils';

    /** Auth footer: legal links and copyright. Reused on the brand panel and mobile. */
    let { class: className }: { class?: string } = $props();

    const links = [
        { label: 'Terms of Service', href: '#' },
        { label: 'Privacy Policy', href: '#' },
        { label: 'Security', href: '#' },
    ];
</script>

<div
    class={cn(
        'flex flex-col items-center justify-between gap-4 border-t border-line/50 px-8 py-6 text-xs font-medium text-faint sm:flex-row',
        className,
    )}
>
    <div class="flex gap-6">
        {#each links as link (link.label)}
            <a href={link.href} class="transition-colors hover:text-muted"
                >{link.label}</a
            >
        {/each}
    </div>
    <div>© 2026 Tree Of Life Fund</div>
</div>
