<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        ChevronRight,
        Send,
        Ban,
        RotateCcw,
        Trash2,
        X,
    } from '@lucide/svelte';
    import DataTable from '@/components/data-table/data-table.svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import * as Select from '@/components/ui/select';
    import { createColumns } from '@/components/users/columns';
    import type { UserRow } from '@/components/users/types';
    import { cn } from '@/lib/utils';
    import type { Auth } from '@/types/auth';
    import type { AccountStatus, UserRole } from '@/types/enums';

    let { users = [] }: { users: UserRow[] } = $props();

    const auth = $derived(page.props.auth as Auth);
    const currentUserId = $derived(auth.user?.id ?? 0);

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    // ── Filters (role + status, on the search row) ─────────────────────
    type RoleFilter = 'all' | UserRole;
    const roleOptions: { value: RoleFilter; label: string }[] = [
        { value: 'all', label: 'All roles' },
        { value: 'admin', label: 'Admins' },
        { value: 'entrepreneur', label: 'Entrepreneurs' },
        { value: 'mentor', label: 'Mentors' },
        { value: 'employee', label: 'Employees' },
    ];
    let roleFilter = $state<RoleFilter>('all');

    type StatusFilter = 'all' | AccountStatus;
    const statusOptions: { value: StatusFilter; label: string }[] = [
        { value: 'all', label: 'All statuses' },
        { value: 'approved', label: 'Active' },
        { value: 'deactivated', label: 'Deactivated' },
    ];
    let statusFilter = $state<StatusFilter>('all');

    const labelFor = <T extends string>(
        options: { value: T; label: string }[],
        value: T,
    ) => options.find((o) => o.value === value)?.label ?? '';

    const visible = $derived(
        users.filter(
            (u) =>
                (roleFilter === 'all' || u.role === roleFilter) &&
                (statusFilter === 'all' || u.status === statusFilter),
        ),
    );

    // ── Selection ──────────────────────────────────────────────────────
    let rowSelection = $state<Record<string, boolean>>({});
    const selectedIds = $derived(
        Object.keys(rowSelection)
            .filter((k) => rowSelection[k])
            .map(Number),
    );
    const selectedCount = $derived(selectedIds.length);
    const selectedUsers = $derived(
        users.filter((u) => selectedIds.includes(u.id)),
    );
    const approvedSelected = $derived(
        selectedUsers.filter((u) => u.status === 'approved').length,
    );
    const deactivatedSelected = $derived(
        selectedUsers.filter((u) => u.status === 'deactivated').length,
    );

    // ── Toast ──────────────────────────────────────────────────────────
    let toast = $state<string | null>(null);
    let toastTimer: ReturnType<typeof setTimeout> | undefined;
    function toastMsg(m: string) {
        toast = m;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toast = null), 3400);
    }

    // ── Row actions ────────────────────────────────────────────────────
    function deactivate(u: UserRow) {
        router.post(
            `/admin/users/${u.id}/deactivate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    toastMsg(`${u.name}'s access has been revoked`),
            },
        );
    }
    function reactivate(u: UserRow) {
        router.post(
            `/admin/users/${u.id}/reactivate`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    toastMsg(`${u.name}'s access has been restored`),
            },
        );
    }
    function remove(u: UserRow) {
        router.delete(`/admin/users/${u.id}`, {
            preserveScroll: true,
            onSuccess: () => toastMsg(`${u.name} has been removed`),
        });
    }

    const columns = createColumns({
        currentUserId,
        onDeactivate: deactivate,
        onReactivate: reactivate,
        onDelete: remove,
    });

    // ── Bulk actions ───────────────────────────────────────────────────
    let confirmingBulkDelete = $state(false);
    let bulkTimer: ReturnType<typeof setTimeout> | undefined;
    function askBulkDelete() {
        confirmingBulkDelete = true;
        clearTimeout(bulkTimer);
        bulkTimer = setTimeout(() => (confirmingBulkDelete = false), 4000);
    }

    function runBulk(action: 'deactivate' | 'reactivate' | 'delete') {
        const count =
            action === 'deactivate'
                ? approvedSelected
                : action === 'reactivate'
                  ? deactivatedSelected
                  : selectedCount;
        router.post(
            '/admin/users/bulk',
            { action, ids: selectedIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    rowSelection = {};
                    confirmingBulkDelete = false;
                    const noun = count === 1 ? 'account' : 'accounts';
                    const verb =
                        action === 'deactivate'
                            ? 'Revoked access for'
                            : action === 'reactivate'
                              ? 'Restored access for'
                              : 'Removed';
                    toastMsg(`${verb} ${count} ${noun}`);
                },
            },
        );
    }

    const bulkBtn =
        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:outline-none';
    const filterTrigger = 'h-9 min-w-[132px] gap-2 px-3 text-sm';
</script>

<AdminLayout title="Users">
    <!-- Breadcrumb -->
    <div
        class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-6 text-[13px] text-muted"
    >
        <Link
            href="/admin/dashboard"
            class={cn(
                '-ml-1 flex items-center justify-center rounded p-1 text-muted transition-colors hover:text-accent',
                focusRing,
            )}
            aria-label="Back to dashboard"
        >
            <ArrowLeft class="size-4" strokeWidth={1.75} />
        </Link>
        <Link
            href="/admin/dashboard"
            class={cn(
                'rounded-sm transition-colors hover:text-accent',
                focusRing,
            )}
        >
            Dashboard
        </Link>
        <ChevronRight class="size-3.5" strokeWidth={1.75} aria-hidden="true" />
        <span class="text-ink">Users</span>
    </div>

    <div class="px-6 pt-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-ink">
                    Users
                </h1>
                <p class="mt-1 max-w-xl text-sm text-muted">
                    Everyone with an account on Tolfund. Revoke, restore, or
                    remove access, and open a profile for the full picture.
                </p>
            </div>
            <Link
                href="/admin/invitations"
                class={cn(
                    'inline-flex shrink-0 items-center gap-2 rounded-lg border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-accent hover:text-accent',
                    focusRing,
                )}
            >
                <Send class="size-4" strokeWidth={2} />
                Invite people
            </Link>
        </div>
    </div>

    <!-- Content -->
    <div class="flex flex-1 flex-col px-6 pt-6 pb-10">
        <!-- Bulk-action bar (only while rows are selected) -->
        {#if selectedCount > 0}
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-accent/30 bg-accent-soft/40 px-4 py-2.5"
            >
                <span class="text-sm font-medium text-ink">
                    {selectedCount} selected
                </span>
                <div class="flex items-center gap-1.5">
                    {#if approvedSelected > 0}
                        <button
                            type="button"
                            onclick={() => runBulk('deactivate')}
                            class={cn(
                                bulkBtn,
                                'text-muted hover:bg-elevated hover:text-ink',
                            )}
                        >
                            <Ban class="size-3.5" strokeWidth={1.75} />
                            Revoke
                        </button>
                    {/if}
                    {#if deactivatedSelected > 0}
                        <button
                            type="button"
                            onclick={() => runBulk('reactivate')}
                            class={cn(
                                bulkBtn,
                                'text-muted hover:bg-elevated hover:text-accent',
                            )}
                        >
                            <RotateCcw class="size-3.5" strokeWidth={1.75} />
                            Restore
                        </button>
                    {/if}
                    {#if confirmingBulkDelete}
                        <button
                            type="button"
                            onclick={() => runBulk('delete')}
                            class={cn(
                                bulkBtn,
                                'bg-danger/12 font-semibold text-danger-strong hover:bg-danger/20',
                            )}
                        >
                            <Trash2 class="size-3.5" strokeWidth={2} />
                            Confirm delete {selectedCount}
                        </button>
                    {:else}
                        <button
                            type="button"
                            onclick={askBulkDelete}
                            class={cn(
                                bulkBtn,
                                'text-muted hover:bg-elevated hover:text-danger-strong',
                            )}
                        >
                            <Trash2 class="size-3.5" strokeWidth={1.75} />
                            Delete
                        </button>
                    {/if}
                    <button
                        type="button"
                        onclick={() => (rowSelection = {})}
                        class={cn(
                            'ml-1 inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-xs text-muted transition-colors hover:text-ink',
                            focusRing,
                        )}
                    >
                        <X class="size-3.5" strokeWidth={1.75} />
                        Clear
                    </button>
                </div>
            </div>
        {/if}

        <DataTable
            data={visible}
            {columns}
            noun={{ one: 'user', many: 'users' }}
            defaultSort={{ id: 'joinedAt', desc: true }}
            emptyMessage="No users to show here."
            selectable
            getRowId={(u) => String(u.id)}
            canSelectRow={(u) => u.id !== currentUserId}
            bind:rowSelection
        >
            {#snippet filters()}
                <Select.Root
                    type="single"
                    value={roleFilter}
                    onValueChange={(v) => (roleFilter = v as RoleFilter)}
                >
                    <Select.Trigger class={filterTrigger}>
                        {labelFor(roleOptions, roleFilter)}
                    </Select.Trigger>
                    <Select.Content>
                        {#each roleOptions as opt (opt.value)}
                            <Select.Item
                                value={opt.value}
                                label={opt.label}
                                class="text-sm"
                            >
                                {opt.label}
                            </Select.Item>
                        {/each}
                    </Select.Content>
                </Select.Root>
                <Select.Root
                    type="single"
                    value={statusFilter}
                    onValueChange={(v) => (statusFilter = v as StatusFilter)}
                >
                    <Select.Trigger class={filterTrigger}>
                        {labelFor(statusOptions, statusFilter)}
                    </Select.Trigger>
                    <Select.Content>
                        {#each statusOptions as opt (opt.value)}
                            <Select.Item
                                value={opt.value}
                                label={opt.label}
                                class="text-sm"
                            >
                                {opt.label}
                            </Select.Item>
                        {/each}
                    </Select.Content>
                </Select.Root>
            {/snippet}
        </DataTable>
    </div>

    <!-- Toast -->
    {#if toast}
        <div class="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2">
            <div
                role="status"
                class="flex items-center gap-2.5 rounded-lg border border-line-strong bg-elevated px-4 py-2.5 text-sm text-ink shadow-card"
            >
                {toast}
            </div>
        </div>
    {/if}
</AdminLayout>
