<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        ChevronRight,
        Ban,
        RotateCcw,
        Trash2,
        BadgeCheck,
        FileText,
        Download,
    } from '@lucide/svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import RoleBadge from '@/components/ui/role-badge/role-badge.svelte';
    import AccountStatusChip from '@/components/users/account-status.svelte';
    import { initials, relative } from '@/components/users/types';
    import { YEAR_RANGES, EMPLOYEE_RANGES } from '@/lib/onboarding-options';
    import { cn } from '@/lib/utils';
    import type { Auth } from '@/types/auth';
    import type { AccountStatus, UserRole } from '@/types/enums';

    type Doc = {
        id: number | null;
        type: string;
        label: string;
        uploaded: string | null;
    };
    type User = {
        id: number;
        name: string;
        email: string;
        role: UserRole;
        status: AccountStatus;
        phone: string | null;
        verified: boolean;
        joinedAt: number;
        statusChangedAt: number | null;
        company: string | null;
        profile: Record<string, unknown> | null;
        documents: Doc[];
    };

    let { user }: { user: User } = $props();

    const auth = $derived(page.props.auth as Auth);
    const isSelf = $derived(user.id === (auth.user?.id ?? 0));

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    // ── Profile field descriptors ──────────────────────────────────────
    function range(
        ranges: { value: number; label: string }[],
        v: unknown,
    ): string | null {
        if (v == null) {
            return null;
        }

        return ranges.find((r) => r.value === v)?.label ?? String(v);
    }
    const asText = (v: unknown) => (v ? String(v) : null);
    const asList = (v: unknown) =>
        Array.isArray(v) && v.length ? v.join(', ') : null;

    type Field = { label: string; value: string | null; emptyLabel?: string };
    const profileFields = $derived.by<Field[]>(() => {
        const p = user.profile;

        if (!p) {
            return [];
        }

        if (user.role === 'entrepreneur') {
            return [
                { label: 'Business name', value: asText(p.business_name) },
                {
                    label: 'What they do',
                    value: asText(p.business_description),
                },
                { label: 'Sectors', value: asList(p.sector) },
                { label: 'Business email', value: asText(p.business_email) },
                {
                    label: 'Business phone',
                    value: asText(p.business_phone_number),
                },
                {
                    label: 'Years in operation',
                    value: range(YEAR_RANGES, p.years_in_operation),
                },
                {
                    label: 'Employees',
                    value: range(EMPLOYEE_RANGES, p.employee_count),
                },
            ];
        }

        if (user.role === 'mentor') {
            return [
                {
                    label: 'Primary expertise',
                    value: asText(p.primary_expertise),
                },
                { label: 'Industry focus', value: asList(p.industry_focus) },
                {
                    label: 'Years of experience',
                    value: range(YEAR_RANGES, p.years_experience),
                },
                { label: 'Availability', value: asText(p.availability) },
                {
                    label: 'AfCFTA knowledge',
                    value: asText(p.afcfta_knowledge),
                },
            ];
        }

        return [];
    });

    const accountRows = $derived<Field[]>([
        { label: 'Phone', value: user.phone },
        { label: 'Company', value: user.company, emptyLabel: 'None' },
        { label: 'Joined', value: relative(user.joinedAt) },
        {
            label: 'Status changed',
            value: user.statusChangedAt ? relative(user.statusChangedAt) : null,
            emptyLabel: 'Never',
        },
    ]);

    // ── Actions ────────────────────────────────────────────────────────
    let confirmingDelete = $state(false);
    let acting = $state(false);
    let timer: ReturnType<typeof setTimeout> | undefined;

    $effect(() => () => clearTimeout(timer));

    function askDelete() {
        confirmingDelete = true;
        clearTimeout(timer);
        timer = setTimeout(() => (confirmingDelete = false), 4000);
    }

    const inFlight = {
        preserveScroll: true,
        onStart: () => (acting = true),
        onFinish: () => (acting = false),
    };

    function deactivate() {
        router.post(`/admin/users/${user.id}/deactivate`, {}, inFlight);
    }
    function reactivate() {
        router.post(`/admin/users/${user.id}/reactivate`, {}, inFlight);
    }
    function remove() {
        router.delete(`/admin/users/${user.id}`, inFlight);
    }

    const actionBtn =
        'inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50';
</script>

<AdminLayout title={user.name}>
    <!-- Breadcrumb -->
    <div
        class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-6 text-[13px] text-muted"
    >
        <Link
            href="/admin/users"
            class={cn(
                '-ml-1 flex items-center justify-center rounded p-1 text-muted transition-colors hover:text-accent',
                focusRing,
            )}
            aria-label="Back to users"
        >
            <ArrowLeft class="size-4" strokeWidth={1.75} />
        </Link>
        <Link
            href="/admin/users"
            class={cn(
                'rounded-sm transition-colors hover:text-accent',
                focusRing,
            )}
        >
            Users
        </Link>
        <ChevronRight class="size-3.5" strokeWidth={1.75} aria-hidden="true" />
        <span class="truncate text-ink">{user.name}</span>
    </div>

    <div class="mx-auto w-full max-w-3xl px-6 py-8">
        <!-- Identity header -->
        <div
            class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex min-w-0 items-center gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-accent-soft text-lg font-semibold text-accent"
                >
                    {initials(user.name, user.email)}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1
                            class="truncate text-2xl font-semibold tracking-tight text-ink"
                        >
                            {user.name}
                        </h1>
                        <RoleBadge role={user.role} />
                        <AccountStatusChip status={user.status} />
                    </div>
                    <p class="mt-1 flex items-center gap-2 text-sm text-muted">
                        {user.email}
                        {#if user.verified}
                            <span
                                class="inline-flex items-center gap-1 text-xs text-accent"
                            >
                                <BadgeCheck class="size-3.5" strokeWidth={2} />
                                Verified
                            </span>
                        {:else}
                            <span class="text-xs text-muted">Unverified</span>
                        {/if}
                    </p>
                </div>
            </div>

            {#if !isSelf}
                <div class="flex shrink-0 items-center gap-2">
                    {#if user.status === 'approved'}
                        <button
                            type="button"
                            onclick={deactivate}
                            disabled={acting}
                            class={cn(
                                actionBtn,
                                'border-line bg-surface text-muted hover:border-line-strong hover:text-ink',
                                focusRing,
                            )}
                        >
                            <Ban class="size-4" strokeWidth={1.75} />
                            Revoke access
                        </button>
                    {:else}
                        <button
                            type="button"
                            onclick={reactivate}
                            disabled={acting}
                            class={cn(
                                actionBtn,
                                'border-line bg-surface text-accent hover:border-accent',
                                focusRing,
                            )}
                        >
                            <RotateCcw class="size-4" strokeWidth={1.75} />
                            Restore access
                        </button>
                    {/if}
                    <!-- One element for both steps so keyboard focus survives the swap. -->
                    <button
                        type="button"
                        onclick={confirmingDelete ? remove : askDelete}
                        disabled={acting}
                        class={cn(
                            actionBtn,
                            confirmingDelete
                                ? 'border-danger/30 bg-danger/12 font-semibold text-danger-strong hover:bg-danger/20'
                                : 'border-line bg-surface text-muted hover:border-danger/40 hover:text-danger-strong',
                            focusRing,
                        )}
                    >
                        <Trash2
                            class="size-4"
                            strokeWidth={confirmingDelete ? 2 : 1.75}
                        />
                        {confirmingDelete ? 'Confirm delete' : 'Delete'}
                    </button>
                </div>
            {/if}
        </div>

        <!-- Cards -->
        <div class="mt-8 space-y-5">
            <!-- Account -->
            <section class="rounded-xl border border-line bg-panel/40 p-6">
                <h2 class="text-sm font-semibold text-ink">Account</h2>
                <dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    {#each accountRows as row (row.label)}
                        <div>
                            <dt class="text-xs text-muted">{row.label}</dt>
                            <dd
                                class={cn(
                                    'mt-0.5 text-[15px]',
                                    row.value ? 'text-ink' : 'text-muted',
                                )}
                            >
                                {row.value ?? row.emptyLabel ?? 'Not provided'}
                            </dd>
                        </div>
                    {/each}
                </dl>
            </section>

            <!-- Profile -->
            {#if profileFields.length}
                <section class="rounded-xl border border-line bg-panel/40 p-6">
                    <h2 class="text-sm font-semibold text-ink">Profile</h2>
                    <dl class="mt-4 space-y-4">
                        {#each profileFields as field (field.label)}
                            <div>
                                <dt class="text-xs text-muted">
                                    {field.label}
                                </dt>
                                <dd
                                    class={cn(
                                        'mt-0.5 text-[15px] whitespace-pre-line',
                                        field.value ? 'text-ink' : 'text-muted',
                                    )}
                                >
                                    {field.value ?? 'Not provided'}
                                </dd>
                            </div>
                        {/each}
                    </dl>
                </section>
            {/if}

            <!-- Documents -->
            {#if user.documents.length}
                <section class="rounded-xl border border-line bg-panel/40 p-6">
                    <h2 class="text-sm font-semibold text-ink">Documents</h2>
                    <ul class="mt-4 divide-y divide-line">
                        {#each user.documents as doc (doc.type)}
                            <li class="flex items-center gap-3 py-3">
                                <FileText
                                    class={cn(
                                        'size-4 shrink-0',
                                        doc.uploaded
                                            ? 'text-accent'
                                            : 'text-faint',
                                    )}
                                    strokeWidth={1.75}
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-ink">
                                        {doc.label}
                                    </p>
                                    <p class="truncate text-xs text-muted">
                                        {doc.uploaded ?? 'Not uploaded'}
                                    </p>
                                </div>
                                {#if doc.uploaded && doc.id}
                                    <a
                                        href={`/onboarding/documents/${doc.id}`}
                                        class={cn(
                                            'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-elevated hover:text-ink',
                                            focusRing,
                                        )}
                                    >
                                        <Download
                                            class="size-3.5"
                                            strokeWidth={1.75}
                                        />
                                        Download
                                    </a>
                                {/if}
                            </li>
                        {/each}
                    </ul>
                </section>
            {/if}
        </div>
    </div>
</AdminLayout>
