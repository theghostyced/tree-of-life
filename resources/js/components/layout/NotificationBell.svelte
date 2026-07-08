<script lang="ts">
    import { onMount, onDestroy } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import {
        Bell,
        Calendar,
        FileText,
        CircleCheck,
        Check,
        X,
    } from '@lucide/svelte';
    import { fade } from 'svelte/transition';
    import { subscribeNotifications } from '@/lib/chat';
    import { cn } from '@/lib/utils';
    import type { AppNotification } from '@/types/global';
    import type { NotificationCategory } from '@/types/enums';

    const currentUserId = $derived(page.props.auth.user.id);

    let items = $state<AppNotification[]>(page.props.notifications.recent);
    let unreadCount = $state(page.props.notifications.unreadCount);
    let open = $state(false);
    let loadingOlder = $state(false);
    let hasMore = $state(page.props.notifications.recent.length >= 15);
    let teardown: () => void = () => {};

    // Resync from the server prop on navigation (it is authoritative and already
    // includes anything that arrived over the wire and was persisted).
    $effect(() => {
        items = page.props.notifications.recent;
        unreadCount = page.props.notifications.unreadCount;
    });

    onMount(() => {
        teardown = subscribeNotifications(currentUserId, (p) => {
            if (items.some((n) => n.id === p.id)) return;
            items = [
                {
                    id: p.id,
                    category: p.category,
                    title: p.title,
                    body: p.body,
                    actions: p.actions,
                    illustration: p.illustration,
                    read: false,
                    createdAt: Date.now(),
                },
                ...items,
            ];
            unreadCount += 1;
        });
    });
    onDestroy(() => teardown());

    const ICONS: Record<NotificationCategory, typeof Bell> = {
        meeting: Calendar,
        report: FileText,
        onboarding: CircleCheck,
    };

    const rows = $derived(
        items.map((n, i) => {
            const day = new Date(n.createdAt).toDateString();
            const prev = items[i - 1];
            const showDay =
                !prev || new Date(prev.createdAt).toDateString() !== day;
            return { n, showDay };
        }),
    );

    function dayLabel(ms: number): string {
        const d = new Date(ms);
        const today = new Date();
        const yest = new Date();
        yest.setDate(today.getDate() - 1);
        if (d.toDateString() === today.toDateString()) return 'Today';
        if (d.toDateString() === yest.toDateString()) return 'Yesterday';
        return new Intl.DateTimeFormat(undefined, {
            month: 'long',
            day: 'numeric',
        }).format(d);
    }

    function rel(ms: number): string {
        const secs = (Date.now() - ms) / 1000;
        if (secs < 60) return 'now';
        if (secs < 3600) return `${Math.floor(secs / 60)}m`;
        if (secs < 86400) return `${Math.floor(secs / 3600)}h`;
        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
        }).format(new Date(ms));
    }

    function headers() {
        return {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(
                document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
            ),
        };
    }

    async function markRead(n: AppNotification) {
        if (n.read) return;
        n.read = true;
        unreadCount = Math.max(0, unreadCount - 1);
        await fetch(`/notifications/${n.id}/read`, {
            method: 'POST',
            headers: headers(),
        });
    }

    async function markAllRead() {
        if (unreadCount === 0) return;
        items = items.map((n) => ({ ...n, read: true }));
        unreadCount = 0;
        await fetch('/notifications/read-all', {
            method: 'POST',
            headers: headers(),
        });
    }

    function activate(n: AppNotification, url: string) {
        markRead(n);
        open = false;
        router.visit(url);
    }

    async function loadOlder() {
        if (loadingOlder || !hasMore || items.length === 0) return;
        loadingOlder = true;
        const before = items[items.length - 1].createdAt;
        try {
            const res = await fetch(`/notifications?before=${before}`, {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const { notifications }: { notifications: AppNotification[] } =
                await res.json();
            const existing = new Set(items.map((n) => n.id));
            const fresh = notifications.filter((n) => !existing.has(n.id));
            items = [...items, ...fresh];
            if (notifications.length < 20) hasMore = false;
        } finally {
            loadingOlder = false;
        }
    }

    function onScroll(e: Event) {
        const el = e.currentTarget as HTMLElement;
        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 60) loadOlder();
    }
</script>

<div class="relative">
    <button
        type="button"
        onclick={() => (open = !open)}
        aria-label="Notifications"
        aria-expanded={open}
        class="relative flex size-8 items-center justify-center rounded-md text-muted outline-none transition-colors hover:bg-elevated hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/60"
    >
        <Bell class="size-[18px]" strokeWidth={1.75} />
        {#if unreadCount > 0}
            <span
                class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-semibold text-on-accent tabular-nums"
            >
                {unreadCount > 9 ? '9+' : unreadCount}
            </span>
        {/if}
    </button>
</div>

{#if open}
    <button
        type="button"
        aria-label="Close notifications"
        onclick={() => (open = false)}
        transition:fade={{ duration: 150 }}
        class="fixed inset-x-0 top-14 bottom-0 z-40 cursor-default bg-black/40"
    ></button>

    <div
        transition:fade={{ duration: 150 }}
        class="fixed top-16 right-2 left-2 z-50 flex max-h-[75vh] flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-xl lg:top-14 lg:right-0 lg:left-auto lg:h-[calc(100dvh-3.5rem)] lg:max-h-none lg:w-[400px] lg:rounded-none lg:border-y-0 lg:border-r-0 lg:border-l lg:border-line-strong lg:shadow-2xl"
    >
        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-line px-4 py-3"
        >
            <h2 class="text-sm font-semibold text-ink">Notifications</h2>
            <div class="flex items-center gap-1">
                {#if unreadCount > 0}
                    <button
                        type="button"
                        onclick={markAllRead}
                        class="rounded-md px-2 py-1 text-xs font-medium text-accent outline-none transition-colors hover:bg-accent-soft focus-visible:ring-2 focus-visible:ring-accent/50"
                    >
                        Mark all read
                    </button>
                {/if}
                <button
                    type="button"
                    onclick={() => (open = false)}
                    aria-label="Close"
                    class="flex size-7 items-center justify-center rounded-md text-muted outline-none transition-colors hover:bg-elevated hover:text-ink focus-visible:ring-2 focus-visible:ring-accent/50"
                >
                    <X class="size-4" strokeWidth={1.75} />
                </button>
            </div>
        </div>

        <div
            class="custom-scrollbar flex-1 overflow-y-auto"
            onscroll={onScroll}
        >
            {#if items.length === 0}
                <div
                    class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center"
                >
                    <div
                        class="flex size-12 items-center justify-center rounded-full bg-elevated text-muted"
                    >
                        <Bell class="size-6" strokeWidth={1.5} />
                    </div>
                    <p class="text-sm font-medium text-ink">
                        You are all caught up
                    </p>
                    <p class="max-w-[15rem] text-xs text-muted">
                        Bookings, reminders, and reports will show up here.
                    </p>
                </div>
            {:else}
                {#each rows as { n, showDay } (n.id)}
                    {#if showDay}
                        <p
                            class="bg-surface px-4 pt-3 pb-1 text-[11px] font-medium tracking-wide text-faint uppercase"
                        >
                            {dayLabel(n.createdAt)}
                        </p>
                    {/if}
                    {@const Icon = ICONS[n.category] ?? Bell}
                    <div
                        class={cn(
                            'flex gap-3 border-b border-line/60 px-4 py-3 transition-colors',
                            n.read ? 'bg-transparent' : 'bg-accent-soft/40',
                        )}
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent"
                        >
                            <Icon class="size-4" strokeWidth={1.75} />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-[13px] font-semibold text-ink">
                                    {n.title}
                                </p>
                                <span
                                    class="shrink-0 text-[11px] whitespace-nowrap text-faint tabular-nums"
                                >
                                    {rel(n.createdAt)}
                                </span>
                            </div>
                            <p
                                class="mt-0.5 text-[13px] leading-snug text-muted"
                            >
                                {n.body}
                            </p>
                            {#if n.actions.length}
                                <div class="mt-2 flex flex-wrap gap-2">
                                    {#each n.actions as action (action.url + action.label)}
                                        <button
                                            type="button"
                                            onclick={() =>
                                                activate(n, action.url)}
                                            class="inline-flex items-center gap-1 rounded-lg border border-line bg-surface px-2.5 py-1 text-xs font-medium text-ink outline-none transition-colors hover:border-accent hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/50"
                                        >
                                            {action.label}
                                        </button>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                        {#if !n.read}
                            <button
                                type="button"
                                onclick={() => markRead(n)}
                                aria-label="Mark read"
                                title="Mark read"
                                class="flex size-6 shrink-0 items-center justify-center self-start rounded-full text-faint outline-none transition-colors hover:bg-elevated hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/50"
                            >
                                <Check class="size-3.5" strokeWidth={2} />
                            </button>
                        {/if}
                    </div>
                {/each}
                {#if loadingOlder}
                    <p class="py-3 text-center text-xs text-faint">Loading…</p>
                {/if}
            {/if}
        </div>
    </div>
{/if}
