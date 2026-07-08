<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import { Plus, Trash2, Video, MapPin, Clock } from '@lucide/svelte';
    import MentorLayout from '@/components/layout/MentorLayout.svelte';
    import * as Select from '@/components/ui/select';
    import { toast } from '@/components/ui/sonner';
    import { cn } from '@/lib/utils';

    type Slot = {
        id: number;
        dayOfWeek: number;
        startTime: string;
        endTime: string;
        sessionType: 'virtual' | 'in_person';
        location: string | null;
        meetingLink: string | null;
    };

    let { slots = [] }: { slots: Slot[] } = $props();

    const DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
    const field =
        'h-9 w-full rounded-lg border border-line bg-surface px-3 text-sm text-ink outline-none focus-visible:border-accent focus-visible:ring-3 focus-visible:ring-accent/30';

    // Slots grouped by weekday, in Mon–Sun order.
    const byDay = $derived(
        DAYS.map((label, day) => ({
            label,
            day,
            slots: slots.filter((s) => s.dayOfWeek === day),
        })).filter((d) => d.slots.length > 0),
    );

    const form = useForm<{
        day_of_week: number;
        start_time: string;
        end_time: string;
        session_type: 'virtual' | 'in_person';
        location: string;
        meeting_link: string;
    }>({
        day_of_week: 0,
        start_time: '09:00',
        end_time: '10:00',
        session_type: 'virtual',
        location: '',
        meeting_link: '',
    });

    function submit(e: Event) {
        e.preventDefault();
        form.post('/mentor/availability', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Availability added.');
                form.reset('location', 'meeting_link');
            },
        });
    }

    function remove(slot: Slot) {
        router.delete(`/mentor/availability/${slot.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Availability removed.'),
        });
    }
</script>

<MentorLayout title="Availability">
    <div class="mx-auto w-full max-w-7xl px-6 py-8">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">
                Availability
            </h1>
            <p class="mt-1.5 max-w-2xl text-[15px] text-muted">
                Set the weekly times you're open to meet. Your mentees book
                sessions from these slots.
            </p>
        </div>

        <!-- Add a slot -->
        <form
            onsubmit={submit}
            class="mt-8 rounded-2xl border border-line bg-panel/40 p-6"
        >
            <h2 class="text-base font-semibold text-ink">Add a time slot</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span class="mb-1.5 block text-xs font-medium text-muted"
                        >Day</span
                    >
                    <Select.Root
                        type="single"
                        value={String(form.day_of_week)}
                        onValueChange={(v) =>
                            (form.day_of_week = Number(v ?? 0))}
                    >
                        <Select.Trigger size="sm" class="w-full">
                            {DAYS[form.day_of_week]}
                        </Select.Trigger>
                        <Select.Content>
                            {#each DAYS as label, day (day)}
                                <Select.Item value={String(day)} {label}>
                                    {label}
                                </Select.Item>
                            {/each}
                        </Select.Content>
                    </Select.Root>
                </div>

                <div>
                    <label
                        for="start_time"
                        class="mb-1.5 block text-xs font-medium text-muted"
                        >Starts</label
                    >
                    <input
                        id="start_time"
                        type="time"
                        bind:value={form.start_time}
                        class={field}
                    />
                </div>
                <div>
                    <label
                        for="end_time"
                        class="mb-1.5 block text-xs font-medium text-muted"
                        >Ends</label
                    >
                    <input
                        id="end_time"
                        type="time"
                        bind:value={form.end_time}
                        class={field}
                    />
                    {#if form.errors.end_time}
                        <p class="mt-1 text-xs text-danger-strong">
                            {form.errors.end_time}
                        </p>
                    {/if}
                </div>

                <div>
                    <span class="mb-1.5 block text-xs font-medium text-muted"
                        >Type</span
                    >
                    <Select.Root
                        type="single"
                        value={form.session_type}
                        onValueChange={(v) =>
                            (form.session_type =
                                (v as 'virtual' | 'in_person') ?? 'virtual')}
                    >
                        <Select.Trigger size="sm" class="w-full">
                            {form.session_type === 'virtual'
                                ? 'Virtual'
                                : 'In person'}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item value="virtual" label="Virtual">
                                Virtual
                            </Select.Item>
                            <Select.Item value="in_person" label="In person">
                                In person
                            </Select.Item>
                        </Select.Content>
                    </Select.Root>
                </div>
            </div>

            <div class="mt-4">
                {#if form.session_type === 'virtual'}
                    <label
                        for="meeting_link"
                        class="mb-1.5 block text-xs font-medium text-muted"
                        >Meeting link (optional)</label
                    >
                    <input
                        id="meeting_link"
                        type="url"
                        bind:value={form.meeting_link}
                        placeholder="https://meet.google.com/…"
                        class={field}
                    />
                    {#if form.errors.meeting_link}
                        <p class="mt-1 text-xs text-danger-strong">
                            {form.errors.meeting_link}
                        </p>
                    {/if}
                {:else}
                    <label
                        for="location"
                        class="mb-1.5 block text-xs font-medium text-muted"
                        >Location</label
                    >
                    <input
                        id="location"
                        type="text"
                        bind:value={form.location}
                        placeholder="Office address or venue"
                        class={field}
                    />
                    {#if form.errors.location}
                        <p class="mt-1 text-xs text-danger-strong">
                            {form.errors.location}
                        </p>
                    {/if}
                {/if}
            </div>

            <div class="mt-5">
                <button
                    type="submit"
                    disabled={form.processing}
                    class={cn(
                        'inline-flex items-center gap-2 rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong disabled:opacity-60',
                        focusRing,
                    )}
                >
                    <Plus class="size-4" strokeWidth={2} />
                    Add slot
                </button>
            </div>
        </form>

        <!-- Current slots -->
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-ink">
                Your weekly availability
            </h2>

            {#if byDay.length === 0}
                <div
                    class="mt-4 flex flex-col items-center justify-center rounded-2xl border border-line bg-panel/40 px-6 py-14 text-center"
                >
                    <div
                        class="mb-4 flex size-12 items-center justify-center rounded-full bg-accent-soft text-accent"
                    >
                        <Clock class="size-6" strokeWidth={1.75} />
                    </div>
                    <h3 class="text-base font-semibold text-ink">
                        No availability yet
                    </h3>
                    <p class="mt-1.5 max-w-sm text-[15px] text-muted">
                        Add a few weekly slots above so your mentees can book
                        time with you.
                    </p>
                </div>
            {:else}
                <div class="mt-4 space-y-5">
                    {#each byDay as group (group.day)}
                        <div>
                            <h3
                                class="text-xs font-medium tracking-wide text-faint uppercase"
                            >
                                {group.label}
                            </h3>
                            <div class="mt-2 space-y-2">
                                {#each group.slots as slot (slot.id)}
                                    <div
                                        class="flex items-center gap-3 rounded-xl border border-line bg-panel/40 px-4 py-3"
                                    >
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-elevated text-muted"
                                        >
                                            {#if slot.sessionType === 'virtual'}
                                                <Video
                                                    class="size-4"
                                                    strokeWidth={1.75}
                                                />
                                            {:else}
                                                <MapPin
                                                    class="size-4"
                                                    strokeWidth={1.75}
                                                />
                                            {/if}
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="text-sm font-medium text-ink tabular-nums"
                                            >
                                                {slot.startTime} – {slot.endTime}
                                            </p>
                                            <p
                                                class="truncate text-xs text-muted"
                                            >
                                                {slot.sessionType === 'virtual'
                                                    ? 'Virtual'
                                                    : (slot.location ??
                                                      'In person')}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onclick={() => remove(slot)}
                                            aria-label="Remove slot"
                                            class={cn(
                                                'ml-auto inline-flex size-8 items-center justify-center rounded-lg text-muted transition-colors hover:bg-elevated hover:text-danger-strong',
                                                focusRing,
                                            )}
                                        >
                                            <Trash2
                                                class="size-4"
                                                strokeWidth={1.75}
                                            />
                                        </button>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
    </div>
</MentorLayout>
