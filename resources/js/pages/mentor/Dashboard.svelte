<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight, Clock, ListChecks, LineChart } from '@lucide/svelte';
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

        {#if onboarding.status === 'pending'}
            <div
                class="mt-8 flex items-start gap-4 rounded-2xl border border-line bg-accent-soft p-6"
            >
                <Clock
                    class="mt-0.5 size-6 shrink-0 text-accent"
                    strokeWidth={1.75}
                />
                <div>
                    <p class="text-lg font-semibold text-ink">
                        Your profile is under review
                    </p>
                    <p class="mt-1 text-[15px] text-muted">
                        We'll email you once an admin has reviewed it. In the
                        meantime, feel free to look around.
                    </p>
                </div>
            </div>
        {:else if onboarding.status !== 'approved'}
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
                                {onboarding.isComplete
                                    ? 'Your profile is ready to submit'
                                    : 'Complete your profile'}
                            </p>
                            <p class="mt-1 text-[15px] text-muted">
                                {onboarding.isComplete
                                    ? 'Submit it for an admin to review and unlock full access.'
                                    : `${onboarding.remaining} of ${onboarding.total} steps left before you can submit for review.`}
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
                        {onboarding.isComplete
                            ? 'Review & submit'
                            : 'Continue onboarding'}
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
            description="Once you're approved, your mentees, sessions, and reviews will appear here."
        >
            {#snippet icon()}
                <LineChart class="size-6" strokeWidth={1.75} />
            {/snippet}
        </EmptyState>
    </div>
</MentorLayout>
