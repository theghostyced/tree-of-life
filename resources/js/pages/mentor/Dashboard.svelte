<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight, ListChecks, LineChart } from '@lucide/svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import { EmptyState } from '@/components/ui/empty-state';
    import { cn } from '@/lib/utils';

    type Onboarding = {
        status: string;
        total: number;
        completed: number;
        remaining: number;
        isComplete: boolean;
        missing: string[];
    };

    let { onboarding }: { onboarding: Onboarding } = $props();

    const pct = $derived(
        onboarding.total === 0
            ? 0
            : Math.round((onboarding.completed / onboarding.total) * 100),
    );

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
</script>

<MentorLayout title="Dashboard">
    <div class="px-6 pt-10">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight text-ink">
                Dashboard
            </h1>
            <p class="mt-1.5 text-[15px] text-muted">
                Your mentorship workspace at a glance.
            </p>
        </div>

        {#if !onboarding.isComplete}
            <div
                class="mt-8 rounded-2xl border border-line bg-panel/50 p-6 sm:p-7"
            >
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-line bg-accent-soft text-accent"
                        >
                            <ListChecks class="size-5" strokeWidth={1.75} />
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-ink">
                                Complete your profile
                            </p>
                            <p class="mt-1 text-[15px] text-muted">
                                {onboarding.remaining} of {onboarding.total} items
                                left to finish setting up your mentor profile.
                            </p>
                        </div>
                    </div>
                    <Link
                        href="/mentor/onboarding"
                        class={cn(
                            'inline-flex shrink-0 items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong',
                            focusRing,
                        )}
                    >
                        Continue onboarding
                        <ArrowRight class="size-4" strokeWidth={2} />
                    </Link>
                </div>
                <div class="mt-5 flex items-center gap-3">
                    <div
                        class="h-1.5 flex-1 overflow-hidden rounded-full bg-elevated"
                    >
                        <div
                            class="h-full rounded-full bg-accent transition-all"
                            style="width: {pct}%"
                        ></div>
                    </div>
                    <span class="shrink-0 text-sm font-medium text-muted"
                        >{pct}%</span
                    >
                </div>
            </div>
        {/if}
    </div>

    <div
        class="mt-8 flex flex-1 items-center justify-center border-t border-line"
    >
        <EmptyState
            title="Your workspace is taking shape"
            description="Your mentees, sessions, and reviews will appear here as the programme gets underway."
        >
            {#snippet icon()}
                <LineChart class="size-6" strokeWidth={1.75} />
            {/snippet}
        </EmptyState>
    </div>
</MentorLayout>
