<script lang="ts">
    /** Legal/utility links shown beneath the auth card. */
    const links = [
        { label: 'Terms of Service', href: '#' },
        { label: 'Privacy Policy', href: '#' },
        { label: 'Security', href: '#' },
    ];
</script>

<div class="flex gap-6 text-[12px] text-white">
    {#each links as link (link.label)}
        <a href={link.href} class="hover:text-sage-400">{link.label}</a>
    {/each}
</div>
