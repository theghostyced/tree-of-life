<script lang="ts">
    import type { UserRole } from '@/types/auth';
    import { cn } from '@/lib/utils';

    let {
        role,
        class: className = '',
    }: { role: UserRole; class?: string } = $props();

    /**
     * Label + status-dot tone per role. The pill itself stays neutral; only the
     * small dot carries colour, keeping the marker calm and on the accent palette.
     * Typed as a full Record<UserRole, …> so adding a role fails to compile
     * until it has a label and a tone.
     */
    const config: Record<UserRole, { label: string; dot: string }> = {
        admin: { label: 'Admin', dot: 'bg-accent' },
        mentor: { label: 'Mentor', dot: 'bg-accent' },
        entrepreneur: { label: 'Entrepreneur', dot: 'bg-success' },
        employee: { label: 'Employee', dot: 'bg-accent-strong' },
    };

    const current = $derived(config[role] ?? config.entrepreneur);
</script>

<span
    class={cn(
        'inline-flex items-center gap-1.5 rounded-full border border-line bg-elevated px-2.5 py-1 text-xs font-medium text-muted',
        className,
    )}
>
    <span
        class={cn('size-1.5 rounded-full', current.dot)}
        aria-hidden="true"
    ></span>
    <span>{current.label}</span>
</span>
