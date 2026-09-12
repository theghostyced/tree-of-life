<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { Lock, Trash2, Upload } from '@lucide/svelte';
    import { fade } from 'svelte/transition';
    import RoleLayout from '@/components/layout/RoleLayout.svelte';
    import { Button } from '@/components/ui/button';
    import { toast } from '@/components/ui/sonner';
    import { initialsOf } from '@/lib/utils';

    type Profile = { name: string; email: string; avatarUrl: string | null };
    type Bio = { label: string; hint: string; value: string | null };

    let { profile, bio = null }: { profile: Profile; bio: Bio | null } =
        $props();

    const form = useForm<{
        name: string;
        bio: string;
        avatar: File | null;
        remove_avatar: boolean;
    }>({
        name: profile.name,
        bio: bio?.value ?? '',
        avatar: null,
        remove_avatar: false,
    });

    // Local preview of a picked file; revoked when replaced, removed, or rejected.
    let preview = $state<string | null>(null);

    function clearPreview() {
        if (preview) {
            URL.revokeObjectURL(preview);
        }

        preview = null;
    }

    function pickAvatar(e: Event) {
        const input = e.target as HTMLInputElement;
        const file = input.files?.[0] ?? null;

        clearPreview();
        preview = file ? URL.createObjectURL(file) : null;
        form.avatar = file;
        form.remove_avatar = false;
        form.clearErrors('avatar');
        input.value = '';
    }

    function removeAvatar() {
        clearPreview();
        form.avatar = null;
        form.remove_avatar = true;
    }

    const shownAvatar = $derived(
        form.remove_avatar ? null : (preview ?? profile.avatarUrl),
    );

    const reduceMotion = $derived(
        typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    );

    const fadeMs = $derived(reduceMotion ? 0 : 180);

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.patch('/profile', {
            forceFormData: form.avatar !== null,
            preserveScroll: true,
            onSuccess: () => {
                clearPreview();
                form.avatar = null;
                form.remove_avatar = false;
                form.defaults();
            },
            onError: (errors) => {
                // A rejected picture must not keep masquerading as the new one.
                if (errors.avatar) {
                    clearPreview();
                    form.avatar = null;
                }

                toast.error('Please fix the highlighted fields.');
            },
        });
    }

    const field =
        'h-11 w-full rounded-lg border border-line bg-surface px-3.5 text-[15px] text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus-visible:ring-2 focus-visible:ring-accent/50';
    const labelClass = 'text-sm font-medium text-muted';
    const secondaryButton =
        'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-line px-3.5 text-sm font-medium text-muted transition-colors hover:border-accent hover:text-ink focus-within:ring-2 focus-within:ring-accent/60 focus-visible:ring-2 focus-visible:ring-accent/60 outline-none disabled:cursor-not-allowed disabled:opacity-50 has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-50';
</script>

<RoleLayout title="Your profile">
    <div class="mx-auto w-full max-w-3xl px-6 py-8">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">
            Your profile
        </h1>
        <p class="mt-1.5 text-[15px] text-muted">
            How you appear to the people you work with on the programme.
        </p>

        <form onsubmit={submit} class="mt-8 space-y-8">
            <section class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <div
                    class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-accent-soft text-xl font-semibold text-accent"
                >
                    {#key shownAvatar}
                        {#if shownAvatar}
                            <img
                                src={shownAvatar}
                                alt=""
                                class="absolute inset-0 size-full object-cover"
                                in:fade={{ duration: fadeMs }}
                            />
                        {:else}
                            <span in:fade={{ duration: fadeMs }}>
                                {initialsOf(
                                    form.name || profile.name,
                                    profile.email,
                                )}
                            </span>
                        {/if}
                    {/key}
                </div>
                <div class="min-w-0 space-y-2">
                    <p class="text-sm font-medium text-ink">Profile picture</p>
                    <p class="text-sm text-muted">
                        JPG or PNG, at least 128×128, up to 2 MB.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <label class={secondaryButton}>
                            <Upload class="size-4" strokeWidth={1.75} />
                            {shownAvatar ? 'Change photo' : 'Upload photo'}
                            <input
                                type="file"
                                class="sr-only"
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                disabled={form.processing}
                                onchange={pickAvatar}
                            />
                        </label>
                        {#if shownAvatar}
                            <button
                                type="button"
                                onclick={removeAvatar}
                                disabled={form.processing}
                                class={secondaryButton}
                            >
                                <Trash2 class="size-4" strokeWidth={1.75} />
                                Remove
                            </button>
                        {/if}
                    </div>
                    {#if form.errors.avatar}
                        <p class="text-sm text-error">{form.errors.avatar}</p>
                    {/if}
                </div>
            </section>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="name" class={labelClass}>Name</label>
                    <input
                        id="name"
                        class={field}
                        bind:value={form.name}
                        autocomplete="name"
                        aria-invalid={form.errors.name ? 'true' : undefined}
                    />
                    {#if form.errors.name}
                        <p class="text-sm text-error">{form.errors.name}</p>
                    {/if}
                </div>
                <div class="space-y-2">
                    <label for="email" class={labelClass}>Email</label>
                    <div class="relative">
                        <input
                            id="email"
                            class="{field} cursor-not-allowed border-line/60 bg-elevated pr-10 text-muted focus:border-line/60 focus-visible:ring-0"
                            value={profile.email}
                            readonly
                            aria-describedby="email-note"
                        />
                        <Lock
                            class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-faint"
                            strokeWidth={1.75}
                            aria-hidden="true"
                        />
                    </div>
                    <p id="email-note" class="text-xs text-pretty text-faint">
                        Tied to your invitation. Ask an admin to change it.
                    </p>
                </div>
            </div>

            {#if bio}
                <div class="space-y-2">
                    <label for="bio" class={labelClass}>{bio.label}</label>
                    <textarea
                        id="bio"
                        rows="4"
                        bind:value={form.bio}
                        aria-describedby="bio-hint"
                        aria-invalid={form.errors.bio ? 'true' : undefined}
                        class="w-full resize-none rounded-lg border border-line bg-surface px-3.5 py-2.5 text-[15px] text-ink outline-none transition-colors placeholder:text-faint focus:border-accent focus-visible:ring-2 focus-visible:ring-accent/50"
                    ></textarea>
                    <p id="bio-hint" class="text-xs text-pretty text-faint">
                        {bio.hint}
                    </p>
                    {#if form.errors.bio}
                        <p class="text-sm text-error">{form.errors.bio}</p>
                    {/if}
                </div>
            {/if}

            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-6"
            >
                <p class="text-sm text-faint" aria-live="polite">
                    {#if form.processing}
                        Saving…
                    {:else if form.isDirty}
                        You have unsaved changes.
                    {:else}
                        Everything is saved.
                    {/if}
                </p>
                <Button
                    type="submit"
                    disabled={form.processing || !form.isDirty}
                    class="inline-flex items-center gap-2 rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:bg-accent"
                >
                    {form.processing ? 'Saving…' : 'Save changes'}
                </Button>
            </div>
        </form>
    </div>
</RoleLayout>
