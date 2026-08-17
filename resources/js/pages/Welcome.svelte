<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import CtaBand from '@/components/landing/CtaBand.svelte';
    import FaqItem from '@/components/landing/FaqItem.svelte';
    import LandingFooter from '@/components/landing/LandingFooter.svelte';
    import LandingHero from '@/components/landing/LandingHero.svelte';
    import LandingNav from '@/components/landing/LandingNav.svelte';
    import ProcessStep from '@/components/landing/ProcessStep.svelte';
    import { cn } from '@/lib/utils';

    /**
     * Public landing page. The only page a signed-out visitor sees besides the
     * login and invitation-acceptance screens, so it explains what TLF is
     * and routes invited people to sign in. There is no public registration.
     */

    /** The real product loop, in the order a participant actually meets it. */
    const steps = [
        {
            title: 'An admin invites you',
            body: 'TLF is invitation-only. Your invitation arrives by email carrying a single-use link, and it stays valid for seven days.',
        },
        {
            title: 'You set up your profile',
            body: 'A guided wizard collects your details and documents, and shows exactly what is still missing, so nobody is left guessing whether they are ready.',
        },
        {
            title: 'You choose your mentor',
            body: 'Entrepreneurs browse approved mentors by expertise and industry and choose who to work with, several if that is what the business needs. Nobody is assigned a mentor without choosing them.',
        },
        {
            title: 'You book real availability',
            body: 'Mentors publish the hours they are free. Bookings land inside those hours, and every confirmed meeting carries its own joining link.',
        },
        {
            title: 'You talk between meetings',
            body: 'A private thread for each pairing keeps questions, context, and follow-ups in one place rather than scattered across inboxes.',
        },
        {
            title: 'The meeting leaves a report',
            body: 'The mentor writes a short written record afterwards. It is what both sides revisit later, and how the programme knows a mentorship is genuinely running.',
        },
    ];

    const faqs = [
        {
            question: 'Who can join TLF?',
            answer: 'TLF is invitation-only. A programme admin invites entrepreneurs and mentors by email, and each invitation carries a single-use link. There is no public sign-up form.',
            open: true,
        },
        {
            question: 'How are entrepreneurs and mentors paired?',
            answer: 'Entrepreneurs browse the approved mentors and choose who they want to work with. The mentor sees their new mentee on their dashboard straight away. Nobody is assigned a mentor without choosing them.',
            open: false,
        },
        {
            question: 'What happens after each meeting?',
            answer: 'The mentor writes a short report. It becomes part of the record both sides can revisit, and it is how the programme knows the mentorship is genuinely running rather than quietly stalling.',
            open: false,
        },
        {
            question: 'What if my invitation link has expired?',
            answer: 'Invitation links last seven days and can only be used once. If yours has lapsed or has already been used, ask your programme admin to send a fresh one.',
            open: false,
        },
    ];

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas';
</script>

<AppHead title="TLF: where founders meet their mentors" />

<div class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <LandingNav />
    <LandingHero />

    <!-- Mission band. Tight against the hero on purpose: it reads as the hero's
         closing thought, and the long run of air comes after it. The Fund's own
         mission statement, stated plainly rather than dressed up as a quote. -->
    <section class="bg-accent px-6 py-14 lg:px-12 lg:py-16">
        <div class="mx-auto max-w-4xl text-center">
            <!-- White on the accent band is 5.02:1. Grey would both fail and
                 read washed out; text on a coloured band takes that band's hue. -->
            <p
                class="text-xl leading-relaxed text-balance text-white md:text-2xl"
            >
                Our mission is to build the African middle class through the
                economic empowerment of African women entrepreneurs.
            </p>
        </div>
    </section>

    <!-- The Fund itself: what it is, and which half of it this portal is.
         Two halves, not a card pair. The second carries an accent rule and a
         "You are here" marker because it is the half the visitor is standing
         in, which is information rather than decoration. -->
    <section id="fund" class="scroll-mt-24 px-6 py-24 lg:px-12 lg:py-28">
        <div class="mx-auto w-full max-w-7xl">
            <div class="mb-14 max-w-3xl">
                <h2
                    class="mb-6 text-4xl font-semibold tracking-[-0.02em] text-balance text-ink md:text-5xl"
                >
                    Not a traditional equity fund.
                </h2>
                <p
                    class="max-w-[65ch] text-lg leading-relaxed text-pretty text-muted"
                >
                    The Tree of Life Fund is an impact fund that provides
                    African women entrepreneurs with the finance and the
                    technical support required to grow their businesses through
                    intra-African trade.
                </p>
            </div>

            <!-- Spans the full 7xl column, like the hero above it, so the two
                 hairlines run the page's whole measure. The prose inside keeps
                 its own cap; only the rules take the extra width. -->
            <div class="grid gap-10 md:grid-cols-2 md:gap-16 lg:gap-24">
                <div class="border-t border-line pt-6">
                    <p class="mb-3 text-sm font-semibold text-muted">
                        Elsewhere in the Fund
                    </p>
                    <h3 class="mb-3 text-xl font-semibold text-ink">
                        Access to finance and credit
                    </h3>
                    <p
                        class="max-w-[62ch] text-[15px] leading-relaxed text-pretty text-muted"
                    >
                        The Fund sets out to plug the gap in access to finance
                        and credit for African women entrepreneurs, so capital
                        stops being the reason a good business cannot scale.
                    </p>
                </div>

                <div class="border-t border-accent/60 pt-6">
                    <p class="mb-3 text-sm font-semibold text-accent">
                        You are here
                    </p>
                    <h3 class="mb-3 text-xl font-semibold text-ink">
                        Access to technical expertise
                    </h3>
                    <p
                        class="max-w-[62ch] text-[15px] leading-relaxed text-pretty text-muted"
                    >
                        Specialist teams of instructors support the growth and
                        scale of those companies. This portal is where that
                        second half runs: the pairings, the meetings, and the
                        written record of every one.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works: an ordered sequence, not a tile grid. -->
    <section
        id="how-it-works"
        class="scroll-mt-24 px-6 py-24 lg:px-12 lg:py-32"
    >
        <div class="mx-auto w-full max-w-7xl">
            <div class="mb-14 max-w-2xl">
                <h2
                    class="mb-4 text-4xl font-semibold tracking-[-0.02em] text-balance text-ink md:text-5xl"
                >
                    How TLF works
                </h2>
                <p class="max-w-[65ch] text-lg text-pretty text-muted">
                    Six steps from an invitation to a written record, the same
                    path for every founder and every mentor on the programme.
                </p>
            </div>

            <!-- Runs the full 7xl column, matching the hero and the section
                 above; ProcessStep caps its own prose, so the extra width goes
                 to the hairlines rather than to the line length. -->
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

    <!-- Inside the programme -->
    <section id="programme" class="scroll-mt-24 px-6 pb-24 lg:px-12 lg:pb-32">
        <div
            class="mx-auto flex w-full max-w-7xl flex-col items-center gap-12 lg:flex-row lg:gap-16"
        >
            <div class="w-full lg:w-1/2">
                <div
                    class="relative isolate aspect-[4/3] overflow-hidden rounded-xl border border-line"
                >
                    <!-- NOTE: remote Unsplash asset; replace with an owned,
                         licensed image before launch. Dimmed and desaturated to
                         match the hero so a bright photograph does not punch a
                         hole in the canvas. -->
                    <img
                        src="https://images.unsplash.com/photo-1744973149087-179e3ed54eae?auto=format&fit=crop&w=1400&q=80"
                        alt="Two women listening intently at a programme gathering, lit in warm gold"
                        width="1400"
                        height="1050"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover brightness-[.82] saturate-[.6]"
                    />
                    <div
                        class="absolute inset-0 bg-accent/10 mix-blend-soft-light"
                        aria-hidden="true"
                    ></div>
                </div>
            </div>

            <div class="w-full lg:w-1/2">
                <h2
                    class="mb-6 text-4xl leading-tight font-semibold tracking-[-0.02em] text-balance text-ink md:text-5xl"
                >
                    Built for the women building what's next.
                </h2>
                <p
                    class="mb-8 max-w-[65ch] text-lg leading-relaxed text-pretty text-muted"
                >
                    We want to change the narrative about the capacity of
                    African women's businesses to grow, to scale, and to adapt
                    to technology, so they can generate financial resources of
                    their own and take their place in the AfCFTA marketplace.
                </p>
                <p
                    class="mb-8 max-w-[65ch] text-lg leading-relaxed text-pretty text-muted"
                >
                    That takes more than good intentions. TLF keeps the whole
                    relationship in one place: who is paired with whom, when
                    they are meeting next, and what came out of the last
                    conversation, so mentors, founders, and programme admins are
                    all working from the same picture.
                </p>

                <!-- Two commitments, stated as claims rather than dressed up as
                     metrics; invented headline numbers would be worse than none. -->
                <dl
                    class="mb-10 grid gap-x-8 gap-y-4 border-t border-line pt-6 sm:grid-cols-2"
                >
                    <div>
                        <dt class="mb-1 font-semibold text-ink">
                            You pick your mentor
                        </dt>
                        <dd class="text-[15px] text-muted">
                            Never assigned one
                        </dd>
                    </div>
                    <div>
                        <dt class="mb-1 font-semibold text-ink">
                            Every meeting is written up
                        </dt>
                        <dd class="text-[15px] text-muted">
                            Reviewable long afterwards
                        </dd>
                    </div>
                </dl>

                <Link
                    href="/login"
                    class={cn(
                        'group inline-flex min-h-11 items-center gap-2 border-b-2 border-accent pb-1 font-medium text-ink transition-colors hover:text-accent',
                        focusRing,
                    )}
                >
                    Sign in to your workspace
                    <ArrowRight
                        class="size-4 transition-transform duration-200 group-hover:translate-x-0.5"
                        strokeWidth={2}
                    />
                </Link>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section
        id="faq"
        class="scroll-mt-24 border-t border-line px-6 py-24 lg:px-12 lg:py-28"
    >
        <div class="mx-auto w-full max-w-3xl">
            <h2
                class="mb-12 text-center text-3xl font-semibold tracking-[-0.02em] text-balance text-ink md:text-4xl"
            >
                Questions, answered
            </h2>

            <div class="space-y-4">
                {#each faqs as faq (faq.question)}
                    <FaqItem
                        question={faq.question}
                        answer={faq.answer}
                        open={faq.open}
                    />
                {/each}
            </div>
        </div>
    </section>

    <CtaBand />
    <LandingFooter />
</div>
