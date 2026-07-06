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
    import EntrepreneurLayout from '@/components/layout/EntrepreneurLayout.svelte';
    import { Stepper } from '@/components/ui/stepper';
    import { OptionChecklist } from '@/components/ui/option-checklist';
    import { Button } from '@/components/ui/button';
    import { Toaster, toast } from '@/components/ui/sonner';
    import {
        SECTORS,
        YEAR_RANGES,
        EMPLOYEE_RANGES,
    } from '@/lib/onboarding-options';

    type Doc = { type: string; label: string; uploaded: string | null };
    type Profile = {
        business_name: string | null;
        business_description: string | null;
        business_email: string | null;
        business_phone_number: string | null;
        sector: string[];
        years_in_operation: number | null;
        employee_count: number | null;
    };
    type Progress = {
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
        business_name: string;
        business_description: string;
        business_email: string;
        business_phone_number: string;
        sector: string[];
        years_in_operation: number | null;
        employee_count: number | null;
    }>({
        business_name: profile.business_name ?? '',
        business_description: profile.business_description ?? '',
        business_email: profile.business_email ?? '',
        business_phone_number: profile.business_phone_number ?? '',
        sector: profile.sector ?? [],
        years_in_operation: profile.years_in_operation,
        employee_count: profile.employee_count,
    });

    // Step completeness reflects the *saved* profile, so ticks appear after Save.
    const steps = $derived([
        {
            label: 'Business',
            complete: !!(
                profile.business_name &&
                profile.business_description &&
                (profile.sector?.length ?? 0) > 0
            ),
        },
        {
            label: 'Contact & scale',
            complete: !!(
                profile.business_email &&
                profile.business_phone_number &&
                profile.years_in_operation != null &&
                profile.employee_count != null
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
        form.patch('/entrepreneur/profile', {
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

<EntrepreneurLayout title="Complete your profile">
    <Toaster position="top-center" />

    <!-- Breadcrumb -->
    <div
        class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-6 text-sm text-muted"
    >
        <Link
            href="/entrepreneur/dashboard"
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
                <h2 class="text-lg font-semibold text-ink">
                    About your business
                </h2>
                <div class="mt-5 space-y-5">
                    <div class="space-y-2">
                        <label for="bn" class={labelClass}>Business name</label>
                        <input
                            id="bn"
                            class={field}
                            bind:value={form.business_name}
                            placeholder="Acme Textiles"
                        />
                        {#if form.errors.business_name}
                            <p class="text-sm text-error">
                                {form.errors.business_name}
                            </p>
                        {/if}
                    </div>
                    <div class="space-y-2">
                        <label for="bd" class={labelClass}
                            >What does your business do?</label
                        >
                        <textarea
                            id="bd"
                            rows="3"
                            bind:value={form.business_description}
                            placeholder="A short description of your business."
                            class="w-full resize-none rounded-lg border border-line bg-surface px-3.5 py-2.5 text-[15px] text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus-visible:ring-2 focus-visible:ring-accent/50"
                        ></textarea>
                    </div>
                    <div class="space-y-2">
                        <span class={labelClass}>Sectors</span>
                        <OptionChecklist
                            options={SECTORS}
                            bind:value={form.sector}
                        />
                    </div>
                </div>
            {:else if current === 1}
                <h2 class="text-lg font-semibold text-ink">
                    Contact &amp; scale
                </h2>
                <div class="mt-5 space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label for="be" class={labelClass}
                                >Business email</label
                            >
                            <input
                                id="be"
                                type="email"
                                class={field}
                                bind:value={form.business_email}
                                placeholder="hello@acme.co"
                            />
                            {#if form.errors.business_email}
                                <p class="text-sm text-error">
                                    {form.errors.business_email}
                                </p>
                            {/if}
                        </div>
                        <div class="space-y-2">
                            <label for="bp" class={labelClass}
                                >Business phone</label
                            >
                            <input
                                id="bp"
                                type="tel"
                                class={field}
                                bind:value={form.business_phone_number}
                                placeholder="+254 700 000 000"
                            />
                            {#if form.errors.business_phone_number}
                                <p class="text-sm text-error">
                                    {form.errors.business_phone_number}
                                </p>
                            {/if}
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-2">
                            <label for="yo" class={labelClass}
                                >Years in operation</label
                            >
                            <div class="relative">
                                <select
                                    id="yo"
                                    class="{field} appearance-none pr-10"
                                    style="color-scheme: dark;"
                                    bind:value={form.years_in_operation}
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
                            {#if form.errors.years_in_operation}
                                <p class="text-sm text-error">
                                    {form.errors.years_in_operation}
                                </p>
                            {/if}
                        </div>
                        <div class="space-y-2">
                            <label for="ec" class={labelClass}
                                >Number of employees</label
                            >
                            <div class="relative">
                                <select
                                    id="ec"
                                    class="{field} appearance-none pr-10"
                                    style="color-scheme: dark;"
                                    bind:value={form.employee_count}
                                >
                                    <option value={null} disabled
                                        >Select…</option
                                    >
                                    {#each EMPLOYEE_RANGES as r (r.value)}
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
                            {#if form.errors.employee_count}
                                <p class="text-sm text-error">
                                    {form.errors.employee_count}
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>
            {:else if current === 2}
                <h2 class="text-lg font-semibold text-ink">
                    Required documents
                </h2>
                <p class="mt-1 text-sm text-muted">
                    PDF, JPG, PNG or DOCX, up to 5 MB each.
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

        <!-- Navigation -->
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
                    href="/entrepreneur/dashboard"
                    class="inline-flex items-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/60"
                >
                    Go to dashboard
                    <ChevronRight class="size-4" strokeWidth={2} />
                </Link>
            {/if}
        </div>
    </div>
</EntrepreneurLayout>
