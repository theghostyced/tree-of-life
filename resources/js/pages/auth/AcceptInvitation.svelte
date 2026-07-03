<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { User, Lock, Eye, EyeOff, ArrowRight } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Logo from '@/components/Logo.svelte';
    import AuthBrandPanel from '@/components/auth/AuthBrandPanel.svelte';
    import AuthFooter from '@/components/auth/AuthFooter.svelte';
    import IconInput from '@/components/auth/IconInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Toaster, toast } from '@/components/ui/sonner';

    let {
        invitation,
        token,
    }: {
        invitation: { email: string; role: string; name: string | null };
        token: string;
    } = $props();

    const form = useForm({
        name: invitation.name ?? '',
        password: '',
        password_confirmation: '',
    });

    let showPassword = $state(false);

    const roleLabel = $derived(
        invitation.role.charAt(0).toUpperCase() + invitation.role.slice(1),
    );

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post(`/invitations/accept/${token}`, {
            onError: (errors) => {
                toast.error(
                    errors.name ??
                        errors.password ??
                        'Please check the form and try again.',
                );
            },
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
</script>

<AppHead title="Accept your invitation" />
<Toaster position="top-center" />

<div
    class="flex h-screen w-full overflow-hidden bg-canvas font-sans text-ink selection:bg-accent selection:text-on-accent"
>
    <AuthBrandPanel />

    <div class="relative flex w-full flex-col bg-canvas lg:w-[55%]">
        <div
            class="absolute top-0 right-0 left-0 z-20 flex items-center justify-between border-b border-line bg-panel/50 p-6 backdrop-blur-md lg:hidden"
        >
            <Logo size="sm" />
        </div>

        <div
            class="relative z-10 flex flex-1 flex-col items-center justify-center p-6 sm:p-12"
        >
            <div class="relative z-10 w-full max-w-[420px] animate-fade-in">
                <div class="mb-10">
                    <h2
                        class="mb-3 text-3xl font-semibold tracking-tight text-ink"
                    >
                        Create your account
                    </h2>
                    <p class="text-[15px] text-muted">
                        You're joining as {roleLabel}. Set a password to finish.
                    </p>
                </div>

                <form class="space-y-5" onsubmit={submit}>
                    <div class="space-y-2">
                        <span class="block text-sm font-medium text-muted"
                            >Email address</span
                        >
                        <div
                            class="flex items-center rounded-xl border border-line bg-surface px-4 py-3.5 text-[15px] text-muted"
                        >
                            {invitation.email}
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label
                            for="name"
                            class="block text-sm font-medium text-muted"
                            >Full name</label
                        >
                        <IconInput
                            id="name"
                            type="text"
                            autocomplete="name"
                            placeholder="Your name"
                            aria-invalid={!!form.errors.name}
                            bind:value={form.name}
                        >
                            {#snippet icon()}
                                <User class="size-[18px]" strokeWidth={1.75} />
                            {/snippet}
                        </IconInput>
                        {#if form.errors.name}
                            <p class="text-sm text-error">{form.errors.name}</p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <label
                            for="password"
                            class="block text-sm font-medium text-muted"
                            >Password</label
                        >
                        <IconInput
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            autocomplete="new-password"
                            placeholder="••••••••"
                            aria-invalid={!!form.errors.password}
                            bind:value={form.password}
                        >
                            {#snippet icon()}
                                <Lock class="size-[18px]" strokeWidth={1.75} />
                            {/snippet}
                            {#snippet trailing()}
                                <button
                                    type="button"
                                    onclick={() =>
                                        (showPassword = !showPassword)}
                                    aria-label={showPassword
                                        ? 'Hide password'
                                        : 'Show password'}
                                    aria-pressed={showPassword}
                                    class="rounded-lg p-2 text-muted transition-colors hover:bg-surface hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50"
                                >
                                    {#if showPassword}
                                        <EyeOff
                                            class="size-[18px]"
                                            strokeWidth={1.75}
                                        />
                                    {:else}
                                        <Eye
                                            class="size-[18px]"
                                            strokeWidth={1.75}
                                        />
                                    {/if}
                                </button>
                            {/snippet}
                        </IconInput>
                        {#if form.errors.password}
                            <p class="text-sm text-error">
                                {form.errors.password}
                            </p>
                        {/if}
                    </div>

                    <div class="space-y-2">
                        <label
                            for="password_confirmation"
                            class="block text-sm font-medium text-muted"
                            >Confirm password</label
                        >
                        <IconInput
                            id="password_confirmation"
                            type={showPassword ? 'text' : 'password'}
                            autocomplete="new-password"
                            placeholder="••••••••"
                            bind:value={form.password_confirmation}
                        >
                            {#snippet icon()}
                                <Lock class="size-[18px]" strokeWidth={1.75} />
                            {/snippet}
                        </IconInput>
                    </div>

                    <div class="pt-4">
                        <Button
                            type="submit"
                            disabled={form.processing}
                            class="group h-auto w-full rounded-xl bg-accent py-3.5 text-[15px] font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong hover:shadow-btn-strong active:translate-y-0 active:shadow-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-70"
                        >
                            {form.processing
                                ? 'Creating account…'
                                : 'Create account'}
                            {#if !form.processing}
                                <ArrowRight
                                    class="size-[18px] transition-transform group-hover:translate-x-1"
                                    strokeWidth={2}
                                />
                            {/if}
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <AuthFooter class="lg:hidden" />
    </div>
</div>
