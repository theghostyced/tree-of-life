<script lang="ts">
    import { type Role, roleLabel } from './types';

    let { role }: { role: Role } = $props();
</script>

<span
    class="inline-flex items-center rounded-md border border-line bg-elevated px-2 py-0.5 text-xs font-medium text-muted"
>
    {roleLabel[role]}
</span>
