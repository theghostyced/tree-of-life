<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeft } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import CtaBand from '@/components/landing/CtaBand.svelte';
    import LandingFooter from '@/components/landing/LandingFooter.svelte';
    import LandingNav from '@/components/landing/LandingNav.svelte';
    import ProcessStep from '@/components/landing/ProcessStep.svelte';
    import { PROGRAMME_STEPS } from '@/lib/how-it-works';
    import { cn } from '@/lib/utils';

    /**
     * The programme loop on its own page, lifted off the landing scroll so the
     * six steps can be read and linked to directly. Shares the landing chrome
     * so it reads as the same site; the header is typographic rather than a
     * photograph, because someone arriving here came to read the steps and a
     * full hero would push all six below the fold.
     */

    const steps = PROGRAMME_STEPS;

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas';
</script>

<AppHead title="How TLF works" />

<div class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <!-- The nav sits over the hero photograph on the landing page and is
         therefore absolutely positioned. Here there is no photograph, so the
         header reserves the bar's height instead of letting it overlap. -->
    <LandingNav />

    <header class="px-6 pt-32 pb-16 lg:px-12 lg:pt-40 lg:pb-20">
        <div class="mx-auto w-full max-w-7xl">
            <Link
                href="/"
                class={cn(
                    'mb-8 inline-flex items-center gap-2 rounded-md text-sm font-medium text-muted transition-colors hover:text-ink',
                    focusRing,
                )}
            >
                <ArrowLeft class="size-4" strokeWidth={1.75} />
                Back to the fund
            </Link>

            <h1
                class="mb-6 max-w-3xl text-[clamp(2.5rem,5.5vw,4rem)] leading-[1.06] font-semibold tracking-[-0.025em] text-balance text-ink"
            >
                How TLF works
            </h1>
            <p
                class="max-w-[65ch] text-lg leading-relaxed text-pretty text-muted sm:text-xl"
            >
                Six steps from an invitation to a written record, the same path
                for every founder and every mentor on the programme. Nothing
                here happens by accident: each step leaves something the next
                one depends on.
            </p>
        </div>
    </header>

    <section class="px-6 pb-24 lg:px-12 lg:pb-32">
        <div class="mx-auto w-full max-w-7xl">
            <!-- Runs the full 7xl column, matching the landing page's sections;
                 ProcessStep caps its own prose, so the extra width goes to the
                 hairlines rather than to the line length. -->
            <ol class="border-b border-line">
                {#each steps as step, i (step.title)}
                    <ProcessStep
                        index={i + 1}
                        title={step.title}
                        body={step.body}
                    />
                {/each}
            </ol>
        </div>
    </section>

    <CtaBand />
    <LandingFooter />
</div>
