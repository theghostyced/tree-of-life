<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    /**
     * Closing call to action, on a solid dark sage band drawn from the same
     * family as the primary button fill. Every colour here was contrast-checked
     * against sage-800 (#363c30): white heading 11.38:1, sage-200 body 7.86:1,
     * sage-500 button border 3.60:1 for the non-text boundary. Note that muted
     * (3.82:1) and line-strong (1.15:1) both FAIL on this surface, which is why
     * the body copy and the outlined button do not use them here.
     */
    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-sage-800';
</script>

<section class="bg-sage-800 px-6 py-24 lg:px-12">
    <div class="mx-auto max-w-3xl text-center">
        <h2
            class="mb-6 text-4xl leading-tight font-semibold tracking-[-0.02em] text-balance text-white md:text-5xl"
        >
            Already invited to Tolfund?
        </h2>
        <p class="mx-auto mb-10 max-w-2xl text-lg text-pretty text-sage-200">
            Your invitation email carries a single-use link to set up your
            account. Once you're in, your mentor, your meetings, and every
            report live in one place.
        </p>
        <div
            class="flex flex-col items-center justify-center gap-4 sm:flex-row"
        >
            <Link
                href="/login"
                class={cn(
                    'inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-accent px-8 py-3.5 font-semibold text-on-accent shadow-btn transition-all duration-200 hover:-translate-y-px hover:bg-accent-strong hover:shadow-btn-strong active:translate-y-0 sm:w-auto',
                    focusRing,
                )}
            >
                Sign in to Tolfund
            </Link>
            <a
                href="#faq"
                class={cn(
                    'inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-sage-500 px-8 py-3.5 font-medium text-white transition-colors duration-200 hover:bg-white/10 sm:w-auto',
                    focusRing,
                )}
            >
                Read the questions
            </a>
        </div>
    </div>
</section>
