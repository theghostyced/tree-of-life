<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import { ArrowLeft, ChevronRight, Send } from '@lucide/svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import { cn } from '@/lib/utils';
    import type { Auth } from '@/types/auth';
    import { createColumns } from '@/components/users/columns';
    import DataTable from '@/components/invitations/data-table.svelte';
    import type { UserRow } from '@/components/users/types';

    let { users = [] }: { users: UserRow[] } = $props();

    const auth = $derived(page.props.auth as Auth);
    const currentUserId = $derived(auth.user?.id ?? 0);

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    type RoleTab = 'all' | 'admin' | 'entrepreneur' | 'mentor' | 'employee';
    const tabs: { value: RoleTab; label: string }[] = [
        { value: 'all', label: 'All' },
        { value: 'admin', label: 'Admins' },
        { value: 'entrepreneur', label: 'Entrepreneurs' },
        { value: 'mentor', label: 'Mentors' },
        { value: 'employee', label: 'Employees' },
    ];
    let activeTab = $state<RoleTab>('all');

    const counts = $derived.by(() => {
        const c = {
            all: users.length,
            admin: 0,
            entrepreneur: 0,
            mentor: 0,
            employee: 0,
        };
        for (const u of users) c[u.role]++;
        return c;
    });

    const visible = $derived(
        activeTab === 'all' ? users : users.filter((u) => u.role === activeTab),
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
                    Everyone with an account on Tolfund. Manage access — revoke,
                    restore, or remove — and open a profile for the full
                    picture.
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

        <!-- Role filter tabs -->
        <div
            class="custom-scrollbar mt-8 flex items-center gap-7 overflow-x-auto border-b border-line"
        >
            {#each tabs as tab (tab.value)}
                {@const active = activeTab === tab.value}
                <button
                    type="button"
                    onclick={() => (activeTab = tab.value)}
                    class={cn(
                        'group flex shrink-0 items-center gap-2 rounded-none pb-3 text-[15px] font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:outline-none',
                        active
                            ? 'border-b-2 border-accent text-accent'
                            : 'border-b-2 border-transparent text-muted hover:text-ink',
                    )}
                >
                    {tab.label}
                    <span
                        class={cn(
                            'rounded-none px-1.5 py-0.5 text-[11px] font-semibold tabular-nums transition-colors',
                            active
                                ? 'bg-accent/15 text-accent'
                                : 'bg-elevated text-faint group-hover:text-muted',
                        )}
                    >
                        {counts[tab.value]}
                    </span>
                </button>
            {/each}
        </div>
    </div>

    <!-- Content -->
    <div class="flex flex-1 flex-col px-6 pt-6 pb-10">
        <DataTable
            data={visible}
            {columns}
            noun={{ one: 'user', many: 'users' }}
            defaultSort={{ id: 'joinedAt', desc: true }}
            emptyMessage="No users match your search."
        />
    </div>

    <!-- Toast -->
    {#if toast}
        <div class="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2">
            <div
                class="flex items-center gap-2.5 rounded-lg border border-line-strong bg-elevated px-4 py-2.5 text-sm text-ink shadow-card"
            >
                {toast}
            </div>
        </div>
    {/if}
</AdminLayout>
