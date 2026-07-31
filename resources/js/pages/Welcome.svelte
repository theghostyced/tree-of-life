<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        ArrowRight,
        CalendarClock,
        ClipboardList,
        GraduationCap,
        Handshake,
        MessagesSquare,
        ShieldCheck,
    } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import CtaBand from '@/components/landing/CtaBand.svelte';
    import FaqItem from '@/components/landing/FaqItem.svelte';
    import LandingFooter from '@/components/landing/LandingFooter.svelte';
    import LandingHero from '@/components/landing/LandingHero.svelte';
    import LandingNav from '@/components/landing/LandingNav.svelte';
    import OfferingCard from '@/components/landing/OfferingCard.svelte';
    import { cn } from '@/lib/utils';

    /**
     * Public landing page. The only page a signed-out visitor sees besides the
     * login and invitation-acceptance screens, so it explains what Tolfund is
     * and routes invited people to sign in — there is no public registration.
     */
    const offerings = [
        {
            icon: Handshake,
            title: 'Choose your mentor',
            body: 'Browse approved mentors by expertise and industry, and choose the ones you want to work with — several, if that is what your business needs.',
        },
        {
            icon: CalendarClock,
            title: 'Shared availability',
            body: 'Mentors publish the hours they are free. Entrepreneurs book straight into them, and every confirmed meeting carries its own joining link.',
        },
        {
            icon: ClipboardList,
            title: 'A report for every meeting',
            body: 'Each meeting leaves a short written record, so what was discussed and decided stays reviewable long after the conversation ends.',
        },
        {
            icon: MessagesSquare,
            title: 'Messages between meetings',
            body: 'A private thread for each pairing keeps questions, context, and follow-ups in one place rather than scattered across inboxes.',
        },
        {
            icon: GraduationCap,
            title: 'Guided onboarding',
            body: 'A step-by-step profile and document wizard shows exactly what is still missing, so nobody is left guessing whether they are ready.',
        },
        {
            icon: ShieldCheck,
            title: 'Programme oversight',
            body: 'Admins can see who is paired, whether meetings are happening, and whether reports are being captured — without chasing anyone by email.',
        },
    ];

    const faqs = [
        {
            question: 'Who can join Tolfund?',
            answer: 'Tolfund is invitation-only. A programme admin invites entrepreneurs and mentors by email, and each invitation carries a single-use link. There is no public sign-up form.',
            open: true,
        },
        {
            question: 'How are entrepreneurs and mentors paired?',
            answer: 'Entrepreneurs browse the approved mentors and choose who they want to work with. The mentor sees their new mentee on their dashboard straight away — nobody is assigned a mentor without choosing them.',
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

<AppHead title="Tolfund — where founders meet their mentors" />

<div class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <LandingNav />
    <LandingHero />

    <!-- Mission band -->
    <section class="border-y border-line bg-panel px-6 py-16 lg:px-12">
        <div class="mx-auto max-w-4xl text-center">
            <p
                class="text-xl leading-relaxed text-balance text-ink/90 md:text-2xl"
            >
                “Tolfund exists so that a mentorship is never left to chance.
                When founders and mentors both know their next meeting and their
                next step, the relationship does what it was meant to do.”
            </p>
        </div>
    </section>

    <!-- How it works -->
    <section id="how-it-works" class="scroll-mt-24 px-6 py-24 lg:px-12">
        <div class="mx-auto w-full max-w-7xl">
            <div class="mx-auto mb-16 max-w-2xl text-center">
                <h2
                    class="mb-4 text-4xl font-semibold tracking-tight text-balance text-ink md:text-5xl"
                >
                    How Tolfund works
                </h2>
                <p class="text-lg text-muted">
                    Everything a mentorship needs to stay on track — pairing,
                    scheduling, and a clear record of every conversation.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                {#each offerings as offering (offering.title)}
                    <OfferingCard
                        icon={offering.icon}
                        title={offering.title}
                        body={offering.body}
                    />
                {/each}
            </div>
        </div>
    </section>

    <!-- Inside the programme -->
    <section id="programme" class="scroll-mt-24 px-6 py-24 lg:px-12">
        <div
            class="mx-auto flex w-full max-w-7xl flex-col items-center gap-16 lg:flex-row"
        >
            <div class="w-full lg:w-1/2">
                <div
                    class="relative isolate aspect-[4/3] overflow-hidden rounded-xl border border-line"
                >
                    <!-- NOTE: remote Unsplash asset from the source design;
                         replace with an owned, licensed image before launch.
                         Dimmed and desaturated to match the hero treatment so a
                         bright photograph does not punch a hole in the canvas. -->
                    <img
                        src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&auto=format&fit=crop&w=1740&q=80"
                        alt=""
                        class="absolute inset-0 size-full object-cover brightness-[.82] saturate-[.6]"
                    />
                    <div
                        class="absolute inset-0 bg-accent/10 mix-blend-soft-light"
                        aria-hidden="true"
                    ></div>
                </div>
            </div>

            <div class="w-full lg:w-1/2">
                <span
                    class="mb-4 block text-xs font-semibold tracking-[0.04em] text-accent uppercase"
                >
                    Inside the programme
                </span>
                <h2
                    class="mb-6 text-4xl leading-tight font-semibold tracking-tight text-balance text-ink md:text-5xl"
                >
                    Built for the women building what's next.
                </h2>
                <p class="mb-8 text-lg leading-relaxed text-muted">
                    Spreadsheets and email lose the thread. Tolfund keeps the
                    whole relationship in one place — who is paired with whom,
                    when they are meeting next, and what came out of the last
                    conversation — so mentors, founders, and programme admins
                    are all working from the same picture.
                </p>

                <div class="mb-10 grid grid-cols-2 gap-8">
                    <div>
                        <p class="mb-2 text-3xl font-semibold text-accent">
                            1:1
                        </p>
                        <p class="text-sm font-medium text-muted">
                            Mentor–entrepreneur pairings
                        </p>
                    </div>
                    <div>
                        <p class="mb-2 text-3xl font-semibold text-accent">
                            Every meeting
                        </p>
                        <p class="text-sm font-medium text-muted">
                            Leaves a written report
                        </p>
                    </div>
                </div>

                <Link
                    href="/login"
                    class={cn(
                        'inline-flex items-center gap-2 border-b-2 border-accent pb-1 font-medium text-ink transition-colors hover:text-accent',
                        focusRing,
                    )}
                >
                    Sign in to your workspace
                    <ArrowRight class="size-4" strokeWidth={2} />
                </Link>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="scroll-mt-24 px-6 py-24 lg:px-12">
        <div class="mx-auto w-full max-w-3xl">
            <h2
                class="mb-12 text-center text-3xl font-semibold tracking-tight text-balance text-ink md:text-4xl"
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
