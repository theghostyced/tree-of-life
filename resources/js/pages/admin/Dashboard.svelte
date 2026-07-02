<script lang="ts">
    import {
        ArrowLeft,
        ChevronRight,
        ChevronDown,
        MessageSquare,
        LineChart,
    } from '@lucide/svelte';
    import { Link, page } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import { Tabs, type TabItem } from '@/components/ui/tabs';
    import { EmptyState } from '@/components/ui/empty-state';
    import { RoleBadge } from '@/components/ui/role-badge';
    import type { Auth } from '@/types/auth';
    import { cn } from '@/lib/utils';

    // Role of the viewer, from the shared Inertia `auth` prop.
    const role = $derived((page.props.auth as Auth).role);

    // Shared sage-green keyboard-focus ring.
    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const tabs: TabItem[] = [
        { label: 'Overview', value: 'overview' },
        { label: 'Reports', value: 'reports' },
        { label: 'Transactions', value: 'transactions' },
        { label: 'Risk Controls', value: 'risk' },
        { label: 'Insights', value: 'insights' },
    ];
    let activeTab = $state('overview');

    const ranges = ['3m', '6m', '1y', '2y'];
    let activeRange = $state('6m');
</script>

<AdminLayout title="Performance">
    <!-- Breadcrumb bar -->
    <div
        class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-6 text-[13px] text-muted"
    >
        <button
            type="button"
            aria-label="Go back"
            class={cn(
                '-ml-1 flex items-center justify-center rounded p-1 text-muted transition-colors hover:text-accent',
                focusRing,
            )}
        >
            <ArrowLeft class="size-4" strokeWidth={1.75} />
        </button>
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
        <span class="text-ink">Performance</span>
    </div>

    <div class="px-6 pt-8">
        <!-- Page header -->
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold tracking-tight text-ink">
                        Performance
                    </h1>
                    <RoleBadge {role} />
                </div>
                <p class="mt-1 text-sm text-muted">
                    Track and analyze key performance indicators to monitor
                    financial health and growth.
                </p>
            </div>
            <button
                type="button"
                class={cn(
                    '-mr-2 flex shrink-0 items-center gap-2 whitespace-nowrap rounded px-2 py-1 text-sm text-muted transition-colors hover:text-accent',
                    focusRing,
                )}
            >
                <MessageSquare class="size-4" strokeWidth={1.75} />
                <span>Give feedback</span>
            </button>
        </div>

        <!-- Tabs -->
        <div class="mt-8">
            <Tabs items={tabs} bind:active={activeTab} />
        </div>

        <!-- Toolbar -->
        <div
            class="flex flex-col gap-4 py-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div
                class="flex items-center gap-1 rounded-lg border border-line bg-elevated p-1"
            >
                {#each ranges as range (range)}
                    <button
                        type="button"
                        onclick={() => (activeRange = range)}
                        class={cn(
                            'rounded px-3 py-1 text-xs font-medium transition-colors',
                            focusRing,
                            activeRange === range
                                ? 'border border-line-strong bg-surface text-ink shadow-raised'
                                : 'text-muted hover:text-ink',
                        )}
                    >
                        {range}
                    </button>
                {/each}
                <div class="mx-1 h-4 w-px bg-line-strong"></div>
                <button
                    type="button"
                    class={cn(
                        'flex items-center gap-1 rounded px-3 py-1 text-xs font-medium text-muted transition-colors hover:text-ink',
                        focusRing,
                    )}
                >
                    By Month
                    <ChevronDown class="size-3.5" strokeWidth={1.75} />
                </button>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs text-muted">Compared to</span>
                <button
                    type="button"
                    class={cn(
                        'flex items-center gap-4 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink transition-colors hover:border-line-strong',
                        focusRing,
                    )}
                >
                    Similar Businesses
                    <ChevronDown
                        class="size-3.5 text-muted"
                        strokeWidth={1.75}
                    />
                </button>
            </div>
        </div>
    </div>

    <!-- Charts region → empty state, centred in the remaining space -->
    <div class="flex flex-1 items-center justify-center border-t border-line">
        <EmptyState
            title="No performance data yet"
            description="Once applications, awards, and disbursements start flowing, your performance metrics and charts will appear here."
        >
            {#snippet icon()}
                <LineChart class="size-6" strokeWidth={1.75} />
            {/snippet}
            {#snippet action()}
                <button
                    type="button"
                    class={cn(
                        'rounded-lg border border-line-strong bg-surface px-4 py-2 text-sm font-medium text-ink shadow-raised transition-colors hover:border-accent hover:text-accent',
                        focusRing,
                    )}
                >
                    Learn more
                </button>
            {/snippet}
        </EmptyState>
    </div>
</AdminLayout>
