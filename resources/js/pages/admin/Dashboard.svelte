<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import { ArrowRight } from '@lucide/svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import AccountStatusChip from '@/components/users/account-status.svelte';
    import { initials, relative } from '@/components/users/types';
    import { userRoleLabel } from '@/types/enums';
    import { cn } from '@/lib/utils';
    import type { Auth } from '@/types/auth';
    import type { UserRole, AccountStatus } from '@/types/enums';

    type Stats = {
        people: number;
        deactivated: number;
        mentors: number;
        entrepreneurs: number;
        pendingInvites: number;
    };
    type Readiness = {
        mentorsReady: number;
        mentorsTotal: number;
        entrepreneursReady: number;
        entrepreneursTotal: number;
    };
    type RecentUser = {
        id: number;
        name: string;
        role: UserRole;
        status: AccountStatus;
        joinedAt: number;
    };
    type PendingInvite = {
        id: number;
        name: string | null;
        email: string;
        role: UserRole;
        sentAt: number;
    };

    let {
        stats,
        readiness,
        recent = [],
        pendingInvitations = [],
    }: {
        stats: Stats;
        readiness: Readiness;
        recent: RecentUser[];
        pendingInvitations: PendingInvite[];
    } = $props();

    const auth = $derived(page.props.auth as Auth);
    const firstName = $derived((auth.user?.name ?? '').split(' ')[0]);

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const statItems = $derived([
        {
            label: 'People',
            value: stats.people,
            sub:
                stats.deactivated > 0
                    ? `${stats.deactivated} deactivated`
                    : 'all active',
        },
        { label: 'Mentors', value: stats.mentors, sub: null },
        { label: 'Entrepreneurs', value: stats.entrepreneurs, sub: null },
        { label: 'Pending invites', value: stats.pendingInvites, sub: null },
    ]);

    const readinessRows = $derived([
        {
            label: 'Mentors',
            ready: readiness.mentorsReady,
            total: readiness.mentorsTotal,
        },
        {
            label: 'Entrepreneurs',
            ready: readiness.entrepreneursReady,
            total: readiness.entrepreneursTotal,
        },
    ]);

    const pct = (ready: number, total: number) =>
        total === 0 ? 0 : Math.round((ready / total) * 100);
</script>

<AdminLayout title="Dashboard">
    <div class="mx-auto w-full max-w-7xl space-y-8 px-6 py-8">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Overview
            </h1>
            <p class="mt-1 text-sm text-muted">
                Welcome back{firstName ? `, ${firstName}` : ''}. Here's how the
                mentorship program looks today.
            </p>
        </div>

        <!-- Stat strip -->
        <div
            class="grid grid-cols-2 divide-x divide-y divide-line overflow-hidden rounded-2xl border border-line bg-panel/40 sm:grid-cols-4 sm:divide-y-0"
        >
            {#each statItems as stat (stat.label)}
                <div class="p-5">
                    <p
                        class="text-3xl font-semibold tracking-tight text-ink tabular-nums"
                    >
                        {stat.value}
                    </p>
                    <p class="mt-1 text-sm text-muted">{stat.label}</p>
                    <p class="mt-0.5 text-xs text-faint">
                        {stat.sub ?? ' '}
                    </p>
                </div>
            {/each}
        </div>

        <!-- Readiness + recent -->
        <div class="grid gap-5 lg:grid-cols-2">
            <!-- Ready to pair -->
            <section class="rounded-2xl border border-line bg-panel/40 p-6">
                <h2 class="text-sm font-semibold text-ink">Ready to pair</h2>
                <p class="mt-1 text-xs text-muted">
                    Active accounts that have completed their profile.
                </p>
                <div class="mt-5 space-y-5">
                    {#each readinessRows as row (row.label)}
                        <div>
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-ink">{row.label}</span>
                                <span class="text-muted tabular-nums">
                                    {row.ready} of {row.total}
                                </span>
                            </div>
                            <div
                                class="mt-2 h-1.5 overflow-hidden rounded-full bg-elevated"
                            >
                                <div
                                    class="h-full rounded-full bg-accent transition-all"
                                    style="width: {pct(row.ready, row.total)}%"
                                ></div>
                            </div>
                        </div>
                    {/each}
                </div>
            </section>

            <!-- Recent people -->
            <section class="rounded-2xl border border-line bg-panel/40 p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink">
                        Recent people
                    </h2>
                    <Link
                        href="/admin/users"
                        class={cn(
                            'rounded text-xs text-muted transition-colors hover:text-accent',
                            focusRing,
                        )}
                    >
                        View all
                    </Link>
                </div>
                {#if recent.length}
                    <ul class="mt-3 divide-y divide-line">
                        {#each recent as user (user.id)}
                            <li class="flex items-center gap-3 py-2.5">
                                <div
                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-[11px] font-semibold text-accent"
                                >
                                    {initials(user.name, '')}
                                </div>
                                <Link
                                    href={`/admin/users/${user.id}`}
                                    class={cn(
                                        'min-w-0 flex-1 rounded',
                                        focusRing,
                                    )}
                                >
                                    <p
                                        class="truncate text-sm font-medium text-ink"
                                    >
                                        {user.name}
                                    </p>
                                    <p class="truncate text-xs text-muted">
                                        {userRoleLabel[user.role]} · joined {relative(
                                            user.joinedAt,
                                        )}
                                    </p>
                                </Link>
                                <AccountStatusChip status={user.status} />
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="mt-4 text-sm text-muted">
                        No one has joined yet.
                    </p>
                {/if}
            </section>
        </div>

        <!-- Pending invitations -->
        <section class="rounded-2xl border border-line bg-panel/40 p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-ink">
                    Pending invitations
                </h2>
                <Link
                    href="/admin/invitations"
                    class={cn(
                        'inline-flex items-center gap-1 rounded text-xs text-muted transition-colors hover:text-accent',
                        focusRing,
                    )}
                >
                    Manage
                    <ArrowRight class="size-3.5" strokeWidth={2} />
                </Link>
            </div>
            {#if pendingInvitations.length}
                <ul class="mt-3 divide-y divide-line">
                    {#each pendingInvitations as inv (inv.id)}
                        <li
                            class="flex items-center justify-between gap-3 py-2.5"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm text-ink">
                                    {inv.name ?? inv.email}
                                </p>
                                {#if inv.name}
                                    <p class="truncate text-xs text-muted">
                                        {inv.email}
                                    </p>
                                {/if}
                            </div>
                            <div
                                class="flex shrink-0 items-center gap-4 text-xs"
                            >
                                <span class="text-muted"
                                    >{userRoleLabel[inv.role]}</span
                                >
                                <span class="text-faint"
                                    >sent {relative(inv.sentAt)}</span
                                >
                            </div>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p class="mt-4 text-sm text-muted">
                    No pending invitations — everyone invited has joined.
                </p>
            {/if}
        </section>

        <!-- Meetings & reports (next to build) -->
        <section
            class="flex flex-col gap-6 rounded-2xl border border-line bg-accent-soft/40 p-6 sm:flex-row sm:items-center sm:justify-between sm:gap-8"
        >
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-base font-semibold text-ink">
                        Meetings &amp; reports
                    </h2>
                    <span
                        class="rounded-full border border-line bg-elevated px-2 py-0.5 text-[11px] font-medium text-muted"
                    >
                        Coming soon
                    </span>
                </div>
                <p class="mt-2 max-w-xl text-sm text-muted">
                    The heart of Tolfund: pair each entrepreneur with a mentor,
                    schedule their meetings, and capture a short report after
                    every one. This is the next area to build.
                </p>
            </div>
            <img
                src="/images/illustrations/social-talk.svg"
                alt=""
                class="h-36 w-auto shrink-0 self-center opacity-90 sm:h-44 lg:h-52"
            />
        </section>
    </div>
</AdminLayout>
