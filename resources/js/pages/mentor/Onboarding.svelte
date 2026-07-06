<script lang="ts">
    import { Link, useForm, router } from '@inertiajs/svelte';
    import {
        Upload,
        Check,
        FileText,
        ChevronRight,
        ChevronLeft,
        ChevronDown,
    } from '@lucide/svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import { Stepper } from '@/components/ui/stepper';
    import { OptionChecklist } from '@/components/ui/option-checklist';
    import { Button } from '@/components/ui/button';
    import { Toaster, toast } from '@/components/ui/sonner';
    import {
        INDUSTRIES,
        YEAR_RANGES,
        EXPERTISE_AREAS,
        AVAILABILITY,
    } from '@/lib/onboarding-options';

    type Doc = { type: string; label: string; uploaded: string | null };
    type Profile = {
        primary_expertise: string | null;
        industry_focus: string[];
        years_experience: number | null;
        afcfta_knowledge: string | null;
        availability: string | null;
    };
    type Progress = {
        status: string;
        total: number;
        completed: number;
        remaining: number;
        isComplete: boolean;
        missing: string[];
    };

    let {
        profile,
        requiredDocuments = [],
        progress,
    }: {
        profile: Profile;
        requiredDocuments: Doc[];
        progress: Progress;
    } = $props();

    const form = useForm<{
        primary_expertise: string;
        industry_focus: string[];
        years_experience: number | null;
        afcfta_knowledge: string;
        availability: string;
    }>({
        primary_expertise: profile.primary_expertise ?? '',
        industry_focus: profile.industry_focus ?? [],
        years_experience: profile.years_experience,
        afcfta_knowledge: profile.afcfta_knowledge ?? '',
        availability: profile.availability ?? '',
    });

    // Primary expertise: a single choice from a list, or a custom "Other" value.
    const OTHER = '__other__';
    let expertiseChoice = $state(
        profile.primary_expertise
            ? EXPERTISE_AREAS.includes(profile.primary_expertise)
                ? profile.primary_expertise
                : OTHER
            : '',
    );
    let expertiseOther = $state(
        profile.primary_expertise &&
            !EXPERTISE_AREAS.includes(profile.primary_expertise)
            ? profile.primary_expertise
            : '',
    );

    function syncExpertise() {
        form.primary_expertise =
            expertiseChoice === OTHER ? expertiseOther : expertiseChoice;
    }

    const steps = $derived([
        {
            label: 'Expertise',
            complete: !!(
                profile.primary_expertise &&
                (profile.industry_focus?.length ?? 0) > 0
            ),
        },
        {
            label: 'Experience',
            complete: !!(
                profile.years_experience != null &&
                profile.afcfta_knowledge &&
                profile.availability
            ),
        },
        {
            label: 'Documents',
            complete:
                requiredDocuments.length > 0 &&
                requiredDocuments.every((d) => d.uploaded),
        },
        { label: 'Review', complete: progress.isComplete },
    ]);

    let current = $state(0);

    function goto(index: number) {
        current = Math.max(0, Math.min(index, steps.length - 1));
    }

    function saveAndContinue() {
        form.patch('/mentor/profile', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Progress saved.');
                goto(current + 1);
            },
            onError: () => toast.error('Please fix the highlighted fields.'),
        });
    }

    function uploadDoc(type: string, e: Event) {
        const input = e.target as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;
        router.post(
            '/onboarding/documents',
            { document_type: type, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => toast.success('Document uploaded.'),
                onError: (errs) =>
                    toast.error(
                        errs.file ?? errs.document_type ?? 'Upload failed.',
                    ),
            },
        );
        input.value = '';
    }

    const field =
        'h-11 w-full rounded-lg border border-line bg-surface px-3.5 text-[15px] text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus-visible:ring-2 focus-visible:ring-accent/50';
    const labelClass = 'text-sm font-medium text-muted';
</script>

<MentorLayout title="Complete your profile">
    <Toaster position="top-center" />

    <div
        class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-6 text-sm text-muted"
    >
        <Link
            href="/mentor/dashboard"
            class="rounded-sm outline-none transition-colors hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/60"
        >
            Dashboard
        </Link>
        <ChevronRight class="size-3.5" strokeWidth={1.75} aria-hidden="true" />
        <span class="text-ink">Complete your profile</span>
    </div>

    <div class="mx-auto w-full max-w-2xl px-6 pt-8 pb-24">
        <div class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Complete your profile
            </h1>
            <p class="mt-1.5 text-[15px] text-muted">
                {progress.completed} of {progress.total} items done. Save as you go
                — you can leave and come back anytime.
            </p>
        </div>

        <Stepper {steps} {current} onselect={goto} />

        <div class="mt-8 rounded-2xl border border-line bg-panel/40 p-6 sm:p-8">
            {#if current === 0}
                <h2 class="text-lg font-semibold text-ink">Your expertise</h2>
                <div class="mt-5 space-y-5">
                    <div class="space-y-2">
                        <label for="pe" class={labelClass}
                            >Primary expertise</label
                        >
                        <div class="relative">
                            <select
                                id="pe"
                                class="{field} appearance-none pr-10"
                                style="color-scheme: dark;"
                                bind:value={expertiseChoice}
                                onchange={syncExpertise}
                            >
                                <option value="" disabled>Select…</option>
                                {#each EXPERTISE_AREAS as area (area)}
                                    <option value={area}>{area}</option>
                                {/each}
                                <option value={OTHER}>Other</option>
                            </select>
                            <ChevronDown
                                class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-muted"
                                strokeWidth={1.75}
                            />
                        </div>
                        {#if expertiseChoice === OTHER}
                            <input
                                class={field}
                                bind:value={expertiseOther}
                                oninput={syncExpertise}
                                placeholder="Your primary expertise"
                            />
                        {/if}
                        {#if form.errors.primary_expertise}
                            <p class="text-sm text-error">
                                {form.errors.primary_expertise}
                            </p>
                        {/if}
                    </div>
                    <div class="space-y-2">
                        <span class={labelClass}>Industry focus</span>
                        <OptionChecklist
                            options={INDUSTRIES}
                            bind:value={form.industry_focus}
                        />
                    </div>
                </div>
            {:else if current === 1}
                <h2 class="text-lg font-semibold text-ink">Your experience</h2>
                <div class="mt-5 space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label for="ye" class={labelClass}
                                >Years of experience</label
                            >
                            <div class="relative">
                                <select
                                    id="ye"
                                    class="{field} appearance-none pr-10"
                                    style="color-scheme: dark;"
                                    bind:value={form.years_experience}
                                >
                                    <option value={null} disabled
                                        >Select…</option
                                    >
                                    {#each YEAR_RANGES as r (r.value)}
                                        <option value={r.value}
                                            >{r.label}</option
                                        >
                                    {/each}
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-muted"
                                    strokeWidth={1.75}
                                />
                            </div>
                            {#if form.errors.years_experience}
                                <p class="text-sm text-error">
                                    {form.errors.years_experience}
                                </p>
                            {/if}
                        </div>
                        <div class="space-y-2">
                            <label for="av" class={labelClass}
                                >Availability</label
                            >
                            <div class="relative">
                                <select
                                    id="av"
                                    class="{field} appearance-none pr-10"
                                    style="color-scheme: dark;"
                                    bind:value={form.availability}
                                >
                                    <option value="" disabled>Select…</option>
                                    {#each AVAILABILITY as a (a)}
                                        <option value={a}>{a}</option>
                                    {/each}
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-muted"
                                    strokeWidth={1.75}
                                />
                            </div>
                            {#if form.errors.availability}
                                <p class="text-sm text-error">
                                    {form.errors.availability}
                                </p>
                            {/if}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label for="af" class={labelClass}
                            >Your AfCFTA knowledge</label
                        >
                        <textarea
                            id="af"
                            rows="3"
                            bind:value={form.afcfta_knowledge}
                            placeholder="How your experience relates to the AfCFTA."
                            class="w-full resize-none rounded-lg border border-line bg-surface px-3.5 py-2.5 text-[15px] text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus-visible:ring-2 focus-visible:ring-accent/50"
                        ></textarea>
                    </div>
                </div>
            {:else if current === 2}
                <h2 class="text-lg font-semibold text-ink">
                    Required documents
                </h2>
                <p class="mt-1 text-sm text-muted">
                    PDF, JPG or PNG (DOCX for certifications), up to 2 MB each.
                </p>
                <ul class="mt-5 divide-y divide-line">
                    {#each requiredDocuments as doc (doc.type)}
                        <li class="flex items-center gap-4 py-4">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line {doc.uploaded
                                    ? 'bg-accent-soft text-accent'
                                    : 'bg-surface text-faint'}"
                            >
                                {#if doc.uploaded}
                                    <Check class="size-5" strokeWidth={2.25} />
                                {:else}
                                    <FileText
                                        class="size-5"
                                        strokeWidth={1.75}
                                    />
                                {/if}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-ink">
                                    {doc.label}
                                </p>
                                <p class="truncate text-sm text-muted">
                                    {doc.uploaded ?? 'Not uploaded yet'}
                                </p>
                            </div>
                            <label
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3.5 py-2 text-sm font-medium text-muted transition-colors hover:border-accent hover:text-ink"
                            >
                                <Upload class="size-4" strokeWidth={1.75} />
                                {doc.uploaded ? 'Replace' : 'Upload'}
                                <input
                                    type="file"
                                    class="hidden"
                                    accept=".pdf,.png,.jpg,.jpeg,.docx"
                                    onchange={(e) => uploadDoc(doc.type, e)}
                                />
                            </label>
                        </li>
                    {/each}
                </ul>
            {:else}
                <h2 class="text-lg font-semibold text-ink">Review</h2>
                {#if progress.remaining > 0}
                    <p class="mt-1 text-sm text-muted">
                        A few things still need your attention:
                    </p>
                    <ul class="mt-4 space-y-2">
                        {#each progress.missing as item (item)}
                            <li
                                class="flex items-center gap-2 text-sm text-muted"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-glow-amber"
                                ></span>
                                {item}
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <div
                        class="mt-4 flex items-center gap-3 rounded-xl border border-line bg-accent-soft p-4"
                    >
                        <Check class="size-5 text-accent" strokeWidth={2.25} />
                        <p class="text-[15px] text-ink">
                            You're all set — your profile is complete.
                        </p>
                    </div>
                {/if}
            {/if}
        </div>

        <div class="mt-6 flex items-center justify-between">
            <button
                type="button"
                onclick={() => goto(current - 1)}
                disabled={current === 0}
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2.5 text-sm font-medium text-muted transition-colors hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
            >
                <ChevronLeft class="size-4" strokeWidth={2} />
                Back
            </button>

            {#if current <= 1}
                <Button
                    type="button"
                    onclick={saveAndContinue}
                    disabled={form.processing}
                    class="inline-flex items-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong disabled:opacity-60"
                >
                    {form.processing ? 'Saving…' : 'Save & continue'}
                    <ChevronRight class="size-4" strokeWidth={2} />
                </Button>
            {:else if current === 2}
                <Button
                    type="button"
                    onclick={() => goto(3)}
                    class="inline-flex items-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong"
                >
                    Continue
                    <ChevronRight class="size-4" strokeWidth={2} />
                </Button>
            {:else}
                <Link
                    href="/mentor/dashboard"
                    class="inline-flex items-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
                >
                    Go to dashboard
                    <ChevronRight class="size-4" strokeWidth={2} />
                </Link>
            {/if}
        </div>
    </div>
</MentorLayout>
