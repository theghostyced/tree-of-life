<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    /**
     * Closing call to action on a solid accent band. The primary button inverts
     * here — white fill, accent label — because the band is already the accent,
     * so the usual green fill would vanish into it.
     *
     * Contrast against the band (#15803d): white heading 5.02:1, accent-stroke
     * body 4.14:1, clearing the 3:1 bar its 18px size sits under. On the button:
     * accent label 5.02:1 on white, 4.79:1 on the accent-soft hover. Muted and
     * line-strong both FAIL on this surface, which is why the body copy and the
     * outlined button do not use them here.
     */
    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-accent';
</script>

<section class="bg-accent px-6 py-24 lg:px-12">
    <div class="mx-auto max-w-3xl text-center">
        <h2
            class="mb-6 text-4xl leading-tight font-semibold tracking-[-0.02em] text-balance text-white md:text-5xl"
        >
            Already invited to TOL?
        </h2>
        <p
            class="mx-auto mb-10 max-w-2xl text-lg text-pretty text-accent-stroke"
        >
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
                    'inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-white px-8 py-3.5 font-semibold text-accent transition-all duration-200 hover:-translate-y-px hover:bg-accent-soft active:translate-y-0 sm:w-auto',
                    focusRing,
                )}
            >
                Sign in to TOL
            </Link>
            <a
                href="#faq"
                class={cn(
                    'inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-accent-stroke/50 px-8 py-3.5 font-medium text-white transition-colors duration-200 hover:bg-white/10 sm:w-auto',
                    focusRing,
                )}
            >
                Read the questions
            </a>
        </div>
    </div>
</section>
