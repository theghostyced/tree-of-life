<script lang="ts">
    import { userRoleLabel, type UserRole } from '@/types/enums';

    let { role }: { role: UserRole } = $props();
</script>

<span
    class="inline-flex items-center rounded-md border border-line bg-elevated px-2 py-0.5 text-xs font-medium text-muted"
>
    {userRoleLabel[role]}
</span>
