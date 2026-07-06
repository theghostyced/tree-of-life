<script lang="ts">
    import { Link, router, useForm, usePoll } from '@inertiajs/svelte';
    import {
        ArrowLeft,
        ChevronRight,
        Plus,
        X,
        Send,
        Upload,
        Download,
        FileText,
    } from '@lucide/svelte';
    import { cubicOut } from 'svelte/easing';
    import { fly, fade, scale } from 'svelte/transition';
    import { createColumns } from '@/components/invitations/columns';
    import DataTable from '@/components/invitations/data-table.svelte';
    import type {
        Invitation,
        Role,
        Status,
    } from '@/components/invitations/types';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import * as Select from '@/components/ui/select';
    import { cn } from '@/lib/utils';
    import { invitableRoles, userRoleLabel } from '@/types/enums';

    type ActiveImport = {
        id: number;
        filename: string;
        status: 'pending' | 'processing' | 'completed' | 'failed';
        totalRows: number;
        invitedCount: number;
        skippedCount: number;
        invalidCount: number;
        rowErrors: { row: number; email: string; reason: string }[];
    };

    let {
        invitations = [],
        activeImport = null,
    }: {
        invitations: Invitation[];
        activeImport?: ActiveImport | null;
    } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const tabs: { value: 'all' | Status; label: string }[] = [
        { value: 'all', label: 'All' },
        { value: 'pending', label: 'Pending' },
        { value: 'accepted', label: 'Accepted' },
        { value: 'expired', label: 'Expired' },
        { value: 'revoked', label: 'Revoked' },
    ];
    let activeTab = $state<'all' | Status>('all');

    const counts = $derived.by(() => {
        const c = {
            all: invitations.length,
            pending: 0,
            accepted: 0,
            revoked: 0,
            expired: 0,
        };

        for (const inv of invitations) {
            c[inv.status]++;
        }

        return c;
    });

    const visible = $derived(
        activeTab === 'all'
            ? invitations
            : invitations.filter((i) => i.status === activeTab),
    );

    // ── Row actions ────────────────────────────────────────────────────
    function resend(inv: Invitation) {
        router.post(
            `/admin/invitations/${inv.id}/resend`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toastMsg(`Invitation resent to ${inv.email}`),
            },
        );
    }
    function revoke(inv: Invitation) {
        router.delete(`/admin/invitations/${inv.id}`, {
            preserveScroll: true,
            onSuccess: () => toastMsg(`Invitation to ${inv.email} revoked`),
        });
    }
    const columns = createColumns({ onResend: resend, onRevoke: revoke });

    // ── Invite slide-over ──────────────────────────────────────────────
    let inviteOpen = $state(false);
    const form = useForm<{
        email: string;
        role: Role;
        name: string;
        note: string;
    }>({
        email: '',
        role: 'entrepreneur',
        name: '',
        note: '',
    });
    let emailEl = $state<HTMLInputElement | null>(null);

    const reduce =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const flyMs = reduce ? 0 : 240;
    const fadeMs = reduce ? 0 : 180;

    function openInvite() {
        form.reset();
        form.clearErrors();
        inviteOpen = true;
        setTimeout(() => emailEl?.focus(), 60);
    }
    function closeInvite() {
        inviteOpen = false;
    }
    function submitInvite(e: Event) {
        e.preventDefault();
        const email = form.email;
        form.post('/admin/invitations', {
            preserveScroll: true,
            onSuccess: () => {
                toastMsg(`Invitation sent to ${email}`);
                form.reset();
                closeInvite();
            },
        });
    }

    // ── CSV import slide-over ──────────────────────────────────────────
    let importOpen = $state(false);
    let importDismissed = $state(false);
    let dragging = $state(false);
    const importForm = useForm<{ file: File | null }>({ file: null });

    // Live progress while a queued import runs: refresh the strip and the
    // table every 2s, stopping automatically at a terminal status.
    const importRunning = $derived(
        activeImport !== null &&
            (activeImport.status === 'pending' ||
                activeImport.status === 'processing'),
    );
    const importPoll = usePoll(
        2000,
        { only: ['activeImport', 'invitations'], async: true },
        { autoStart: false, keepAlive: true },
    );

    $effect(() => {
        if (importRunning) {
            importDismissed = false;
            importPoll.start();
        } else {
            importPoll.stop();
        }
    });

    function openImport() {
        importForm.reset();
        importForm.clearErrors();
        importDismissed = false;
        importOpen = true;
    }
    function closeImport() {
        importOpen = false;
    }
    function pickFile(file: File | null | undefined) {
        importForm.file = file ?? null;
        importForm.clearErrors('file');
    }
    function onDrop(e: DragEvent) {
        e.preventDefault();
        dragging = false;
        pickFile(e.dataTransfer?.files?.[0]);
    }
    function submitImport(e: Event) {
        e.preventDefault();
        importForm.post('/admin/invitations/import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toastMsg('CSV processed — see the summary below.');
                importForm.reset();
                closeImport();
            },
        });
    }

    const importTone: Record<ActiveImport['status'], string> = {
        pending: 'bg-glow-amber',
        processing: 'bg-glow-amber',
        completed: 'bg-accent',
        failed: 'bg-error',
    };
    const importStatusLabel: Record<ActiveImport['status'], string> = {
        pending: 'Queued',
        processing: 'Processing…',
        completed: 'Completed',
        failed: 'Failed',
    };

    // ── Toast ──────────────────────────────────────────────────────────
    let toast = $state<string | null>(null);
    let toastTimer: ReturnType<typeof setTimeout> | undefined;
    function toastMsg(m: string) {
        toast = m;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toast = null), 3400);
    }

    function onKeydown(e: KeyboardEvent) {
        if (e.key !== 'Escape') {
            return;
        }

        if (inviteOpen) {
            closeInvite();
        }

        if (importOpen) {
            closeImport();
        }
    }
</script>

<svelte:window onkeydown={onKeydown} />

<AdminLayout title="Invitations">
    <!-- Breadcrumb -->
    <div
        class="flex h-14 shrink-0 items-center gap-2 border-b border-line px-6 text-[13px] text-muted"
    >
        <Link
            href="/admin/dashboard"
            class={cn(
                '-ml-1 flex items-center justify-center rounded p-1 text-muted transition-colors hover:text-accent',
                focusRing,
            )}
            aria-label="Back to dashboard"
        >
            <ArrowLeft class="size-4" strokeWidth={1.75} />
        </Link>
        <Link
            href="/admin/dashboard"
            class={cn(
                'rounded-sm transition-colors hover:text-accent',
                focusRing,
            )}
        >
            Dashboard
        </Link>
        <ChevronRight class="size-3.5" strokeWidth={1.75} aria-hidden="true" />
        <span class="text-ink">Invitations</span>
    </div>

    <div class="px-6 pt-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-ink">
                    Invitations
                </h1>
                <p class="mt-1 max-w-xl text-sm text-muted">
                    Tolfund is invitation-only. Invite founders, mentors and
                    admins by email, and track who has joined.
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2.5">
                <button
                    type="button"
                    onclick={openImport}
                    class={cn(
                        'inline-flex items-center gap-2 rounded-lg border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-accent hover:text-accent',
                        focusRing,
                    )}
                >
                    <Upload class="size-4" strokeWidth={2} />
                    Import CSV
                </button>
                <button
                    type="button"
                    onclick={openInvite}
                    class={cn(
                        'inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong active:translate-y-0',
                        focusRing,
                    )}
                >
                    <Plus class="size-4" strokeWidth={2.25} />
                    Invite people
                </button>
            </div>
        </div>

        <!-- Status filter tabs (square, no radius) -->
        <div
            class="custom-scrollbar mt-8 flex items-center gap-7 overflow-x-auto border-b border-line"
        >
            {#each tabs as tab (tab.value)}
                {@const active = activeTab === tab.value}
                <button
                    type="button"
                    onclick={() => (activeTab = tab.value)}
                    class={cn(
                        'group flex shrink-0 items-center gap-2 rounded-none pb-3 text-[15px] font-medium transition-colors focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:outline-none',
                        active
                            ? 'border-b-2 border-accent text-accent'
                            : 'border-b-2 border-transparent text-muted hover:text-ink',
                    )}
                >
                    {tab.label}
                    <span
                        class={cn(
                            'rounded-none px-1.5 py-0.5 text-[11px] font-semibold tabular-nums transition-colors',
                            active
                                ? 'bg-accent/15 text-accent'
                                : 'bg-elevated text-faint group-hover:text-muted',
                        )}
                    >
                        {counts[tab.value]}
                    </span>
                </button>
            {/each}
        </div>
    </div>

    <!-- Content -->
    <div class="flex flex-1 flex-col px-6 pt-6 pb-10">
        {#if activeImport && !importDismissed}
            <div
                class="mb-6 rounded-xl border border-line bg-panel/50 p-4 sm:p-5"
            >
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class={cn(
                                'size-2 shrink-0 rounded-full',
                                importTone[activeImport.status],
                                activeImport.status === 'processing' &&
                                    'animate-pulse',
                            )}
                        ></span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">
                                {activeImport.filename}
                            </p>
                            <p class="text-xs text-muted">
                                {importStatusLabel[activeImport.status]} · {activeImport.totalRows}
                                rows
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-sm tabular-nums">
                        <span class="text-muted"
                            ><span class="font-semibold text-ink"
                                >{activeImport.invitedCount}</span
                            > invited</span
                        >
                        <span class="text-muted"
                            ><span class="font-semibold text-ink"
                                >{activeImport.skippedCount}</span
                            > skipped</span
                        >
                        <span class="text-muted"
                            ><span class="font-semibold text-ink"
                                >{activeImport.invalidCount}</span
                            > invalid</span
                        >
                        <button
                            type="button"
                            onclick={() => (importDismissed = true)}
                            aria-label="Dismiss import summary"
                            class={cn(
                                'rounded-md p-1 text-muted transition-colors hover:bg-elevated hover:text-ink',
                                focusRing,
                            )}
                        >
                            <X class="size-4" strokeWidth={1.75} />
                        </button>
                    </div>
                </div>
                {#if activeImport.rowErrors.length > 0}
                    <details class="mt-3 border-t border-line pt-3">
                        <summary
                            class="cursor-pointer text-xs font-medium text-muted transition-colors hover:text-ink"
                        >
                            {activeImport.rowErrors.length} row{activeImport
                                .rowErrors.length === 1
                                ? ''
                                : 's'} skipped or invalid — view details
                        </summary>
                        <ul
                            class="custom-scrollbar mt-3 max-h-56 space-y-1.5 overflow-y-auto text-xs"
                        >
                            {#each activeImport.rowErrors as err (err.row)}
                                <li class="flex gap-2">
                                    <span
                                        class="shrink-0 tabular-nums text-faint"
                                        >Row {err.row}</span
                                    >
                                    <span class="truncate text-ink"
                                        >{err.email || '—'}</span
                                    >
                                    <span class="text-muted"
                                        >— {err.reason}</span
                                    >
                                </li>
                            {/each}
                        </ul>
                    </details>
                {/if}
            </div>
        {/if}
        {#if invitations.length === 0}
            <div
                class="flex flex-1 flex-col items-center justify-center py-16 text-center"
            >
                <img
                    src="/images/illustrations/social-envelope.svg"
                    alt=""
                    class="mb-6 size-40 opacity-90"
                />
                <h3 class="text-lg font-semibold text-ink">
                    No invitations yet
                </h3>
                <p class="mt-2 max-w-sm text-[15px] text-muted">
                    Everyone on Tolfund starts with an invitation. Send your
                    first one to bring a founder, mentor or admin on board.
                </p>
                <button
                    type="button"
                    onclick={openInvite}
                    class={cn(
                        'mt-6 inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong active:translate-y-0',
                        focusRing,
                    )}
                >
                    <Plus class="size-4" strokeWidth={2.25} />
                    Invite your first person
                </button>
            </div>
        {:else}
            <DataTable data={visible} {columns} />
        {/if}
    </div>

    <!-- Invite modal -->
    {#if inviteOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                class="absolute inset-0 bg-black/50"
                transition:fade={{ duration: fadeMs }}
                onclick={closeInvite}
                aria-hidden="true"
            ></div>
            <div
                class="relative z-10 flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-line bg-panel shadow-card"
                transition:scale={{
                    start: 0.97,
                    opacity: 0,
                    duration: flyMs,
                    easing: cubicOut,
                }}
                role="dialog"
                aria-modal="true"
                aria-labelledby="invite-title"
            >
                <div
                    class="flex shrink-0 items-start justify-between border-b border-line px-6 py-5"
                >
                    <div>
                        <h2
                            id="invite-title"
                            class="text-lg font-semibold text-ink"
                        >
                            Invite people
                        </h2>
                        <p class="mt-1 text-sm text-muted">
                            They’ll get a single-use link to set up their
                            account.
                        </p>
                    </div>
                    <button
                        type="button"
                        onclick={closeInvite}
                        aria-label="Close"
                        class={cn(
                            'rounded-lg p-1.5 text-muted transition-colors hover:bg-elevated hover:text-ink',
                            focusRing,
                        )}
                    >
                        <X class="size-5" strokeWidth={1.75} />
                    </button>
                </div>

                <form
                    onsubmit={submitInvite}
                    class="flex min-h-0 flex-1 flex-col"
                >
                    <div class="flex-1 space-y-5 overflow-y-auto px-6 py-6">
                        <div class="space-y-2">
                            <label
                                for="inv-email"
                                class="block text-sm font-medium text-ink"
                            >
                                Email address <span class="text-[#d7a1a1]"
                                    >*</span
                                >
                            </label>
                            <input
                                id="inv-email"
                                bind:this={emailEl}
                                type="email"
                                bind:value={form.email}
                                oninput={() => form.clearErrors('email')}
                                placeholder="name@company.com"
                                aria-invalid={form.errors.email
                                    ? 'true'
                                    : undefined}
                                class={cn(
                                    'h-11 w-full rounded-lg border bg-surface px-3.5 text-[15px] text-ink transition-colors placeholder:text-faint focus:border-accent',
                                    focusRing,
                                    form.errors.email
                                        ? 'border-[#cf8b8b]'
                                        : 'border-line',
                                )}
                            />
                            {#if form.errors.email}
                                <p class="text-xs text-[#d7a1a1]">
                                    {form.errors.email}
                                </p>
                            {/if}
                        </div>

                        <div class="space-y-2">
                            <label
                                for="inv-role"
                                class="block text-sm font-medium text-ink"
                                >Role</label
                            >
                            <Select.Root
                                type="single"
                                value={form.role}
                                onValueChange={(v) => (form.role = v as Role)}
                            >
                                <Select.Trigger
                                    id="inv-role"
                                    class="w-full px-3.5 text-[15px]"
                                >
                                    {userRoleLabel[form.role]}
                                </Select.Trigger>
                                <Select.Content>
                                    {#each invitableRoles as role (role)}
                                        <Select.Item
                                            value={role}
                                            label={userRoleLabel[role]}
                                            class="p-2.5"
                                        >
                                            {userRoleLabel[role]}
                                        </Select.Item>
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                            <p class="text-xs text-faint">
                                The role is locked to this invitation and can’t
                                be changed by the invitee.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label
                                for="inv-name"
                                class="block text-sm font-medium text-ink"
                            >
                                Name <span class="font-normal text-faint"
                                    >(optional)</span
                                >
                            </label>
                            <input
                                id="inv-name"
                                type="text"
                                bind:value={form.name}
                                placeholder="Full name"
                                class={cn(
                                    'h-11 w-full rounded-lg border border-line bg-surface px-3.5 text-[15px] text-ink transition-colors placeholder:text-faint focus:border-accent',
                                    focusRing,
                                )}
                            />
                        </div>

                        <div class="space-y-2">
                            <label
                                for="inv-note"
                                class="block text-sm font-medium text-ink"
                            >
                                Note <span class="font-normal text-faint"
                                    >(optional)</span
                                >
                            </label>
                            <textarea
                                id="inv-note"
                                bind:value={form.note}
                                rows="3"
                                placeholder="Add a short, personal note to the email…"
                                class={cn(
                                    'w-full resize-none rounded-lg border border-line bg-surface px-3.5 py-2.5 text-[15px] text-ink transition-colors placeholder:text-faint focus:border-accent',
                                    focusRing,
                                )}
                            ></textarea>
                        </div>
                    </div>

                    <div
                        class="flex shrink-0 items-center justify-end gap-3 border-t border-line px-6 py-4"
                    >
                        <button
                            type="button"
                            onclick={closeInvite}
                            class={cn(
                                'rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:text-ink',
                                focusRing,
                            )}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class={cn(
                                'inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong active:translate-y-0',
                                focusRing,
                            )}
                        >
                            <Send class="size-4" strokeWidth={2} />
                            Send invitation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Import modal -->
    {#if importOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                class="absolute inset-0 bg-black/50"
                transition:fade={{ duration: fadeMs }}
                onclick={closeImport}
                aria-hidden="true"
            ></div>
            <div
                class="relative z-10 flex w-full max-w-lg flex-col overflow-hidden rounded-xl border border-line bg-panel shadow-card"
                transition:scale={{
                    start: 0.97,
                    opacity: 0,
                    duration: flyMs,
                    easing: cubicOut,
                }}
                role="dialog"
                aria-modal="true"
                aria-labelledby="import-title"
            >
                <div
                    class="flex shrink-0 items-start justify-between border-b border-line px-6 py-5"
                >
                    <div>
                        <h2
                            id="import-title"
                            class="text-lg font-semibold text-ink"
                        >
                            Import from CSV
                        </h2>
                        <p class="mt-1 text-sm text-muted">
                            Bulk-invite people from a spreadsheet. Each valid
                            row gets a single-use link.
                        </p>
                    </div>
                    <button
                        type="button"
                        onclick={closeImport}
                        aria-label="Close"
                        class={cn(
                            'rounded-lg p-1.5 text-muted transition-colors hover:bg-elevated hover:text-ink',
                            focusRing,
                        )}
                    >
                        <X class="size-5" strokeWidth={1.75} />
                    </button>
                </div>

                <form onsubmit={submitImport} class="flex flex-col">
                    <div class="space-y-5 px-6 py-6">
                        <a
                            href="/admin/invitations/import/template"
                            class={cn(
                                'flex items-center gap-3 rounded-lg border border-line bg-surface px-4 py-3 text-sm transition-colors hover:border-accent',
                                focusRing,
                            )}
                        >
                            <Download
                                class="size-4 shrink-0 text-accent"
                                strokeWidth={2}
                            />
                            <span class="flex-1 text-ink"
                                >Download the CSV template</span
                            >
                            <span class="text-xs text-faint"
                                >email, role, name</span
                            >
                        </a>

                        <label
                            ondragover={(e) => {
                                e.preventDefault();
                                dragging = true;
                            }}
                            ondragleave={() => (dragging = false)}
                            ondrop={onDrop}
                            class={cn(
                                'flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center transition-colors',
                                dragging
                                    ? 'border-accent bg-accent-soft'
                                    : 'border-line bg-surface hover:border-accent',
                            )}
                        >
                            {#if importForm.file}
                                <FileText
                                    class="size-6 text-accent"
                                    strokeWidth={1.75}
                                />
                                <span class="text-sm font-medium text-ink"
                                    >{importForm.file.name}</span
                                >
                                <span class="text-xs text-faint"
                                    >{(importForm.file.size / 1024).toFixed(0)} KB
                                    · click to replace</span
                                >
                            {:else}
                                <Upload
                                    class="size-6 text-muted"
                                    strokeWidth={1.75}
                                />
                                <span class="text-sm font-medium text-ink"
                                    >Choose a CSV file or drag it here</span
                                >
                                <span class="text-xs text-faint"
                                    >CSV up to 10 MB</span
                                >
                            {/if}
                            <input
                                type="file"
                                accept=".csv,text/csv"
                                class="hidden"
                                onchange={(e) =>
                                    pickFile(
                                        (e.target as HTMLInputElement)
                                            .files?.[0],
                                    )}
                            />
                        </label>
                        {#if importForm.errors.file}
                            <p class="text-xs text-error">
                                {importForm.errors.file}
                            </p>
                        {/if}
                        {#if importForm.progress}
                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-elevated"
                            >
                                <div
                                    class="h-full rounded-full bg-accent transition-all"
                                    style="width: {importForm.progress
                                        .percentage}%"
                                ></div>
                            </div>
                        {/if}
                    </div>

                    <div
                        class="flex shrink-0 items-center justify-end gap-3 border-t border-line px-6 py-4"
                    >
                        <button
                            type="button"
                            onclick={closeImport}
                            class={cn(
                                'rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:text-ink',
                                focusRing,
                            )}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={!importForm.file || importForm.processing}
                            class={cn(
                                'inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50',
                                focusRing,
                            )}
                        >
                            <Upload class="size-4" strokeWidth={2} />
                            {importForm.processing
                                ? 'Importing…'
                                : 'Start import'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Toast -->
    {#if toast}
        <div
            class="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2"
            transition:fly={{ y: 16, duration: fadeMs }}
        >
            <div
                class="flex items-center gap-2.5 rounded-lg border border-line-strong bg-elevated px-4 py-2.5 text-sm text-ink shadow-card"
            >
                <span
                    class="flex size-5 items-center justify-center rounded-full bg-accent text-on-accent"
                >
                    <Send class="size-3" strokeWidth={2.25} />
                </span>
                {toast}
            </div>
        </div>
    {/if}
</AdminLayout>
