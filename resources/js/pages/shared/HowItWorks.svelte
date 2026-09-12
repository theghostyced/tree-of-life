<script lang="ts">
    import RoleLayout from '@/components/layout/RoleLayout.svelte';
    import { PROGRAMME_STEPS } from '@/lib/how-it-works';
</script>

<RoleLayout title="How Tree of Life works">
    <div class="mx-auto w-full max-w-3xl px-6 py-8">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">
            How Tree of Life works
        </h1>
        <p class="mt-1.5 max-w-[65ch] text-[15px] text-pretty text-muted">
            Six steps from an invitation to a written record, the same path for
            every founder and every mentor on the programme. Each step leaves
            something the next one depends on.
        </p>

        <ol class="mt-8 border-b border-line">
            {#each PROGRAMME_STEPS as step, i (step.title)}
                <li
                    class="grid grid-cols-[2.25rem_minmax(0,1fr)] gap-x-4 border-t border-line py-5"
                >
                    <span
                        class="text-xl leading-7 font-normal text-accent/70 tabular-nums"
                        aria-hidden="true"
                    >
                        {String(i + 1).padStart(2, '0')}
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base leading-7 font-semibold text-ink">
                            {step.title}
                        </h2>
                        <p
                            class="mt-1 max-w-[65ch] text-[15px] leading-relaxed text-pretty text-muted"
                        >
                            {step.body}
                        </p>
                    </div>
                </li>
            {/each}
        </ol>
    </div>
</RoleLayout>
