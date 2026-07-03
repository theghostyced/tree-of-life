<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { Mail, Lock, Eye, EyeOff, ArrowRight } from '@lucide/svelte';
    import { Button } from '@/components/ui/button';
    import { toast } from '@/components/ui/sonner';
    import IconInput from './IconInput.svelte';

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    let showPassword = $state(false);

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/login', {
            onError: (errors) => {
                toast.error(
                    errors.email ??
                        errors.password ??
                        'Unable to sign in. Please try again.',
                );
            },
            onFinish: () => form.reset('password'),
        });
    }
</script>

<div class="mb-10">
    <h2 class="mb-3 text-3xl font-semibold tracking-tight text-ink">
        Welcome back
    </h2>
    <p class="text-[15px] text-muted">Sign in to your account to continue.</p>
</div>

<form class="space-y-5" onsubmit={submit}>
    <div class="space-y-2">
        <label for="email" class="block text-sm font-medium text-muted">
            Email address
        </label>
        <IconInput
            id="email"
            type="email"
            autocomplete="email"
            placeholder="name@company.com"
            aria-invalid={!!form.errors.email}
            bind:value={form.email}
        >
            {#snippet icon()}
                <Mail class="size-[18px]" strokeWidth={1.75} />
            {/snippet}
        </IconInput>
        {#if form.errors.email}
            <p class="text-sm text-error">{form.errors.email}</p>
        {/if}
    </div>

    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <label for="password" class="text-sm font-medium text-muted">
                Password
            </label>
            <a
                href="#"
                class="rounded-sm text-sm font-medium text-accent transition-colors hover:text-accent-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50"
            >
                Forgot password?
            </a>
        </div>
        <IconInput
            id="password"
            type={showPassword ? 'text' : 'password'}
            autocomplete="current-password"
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
                    onclick={() => (showPassword = !showPassword)}
                    aria-label={showPassword
                        ? 'Hide password'
                        : 'Show password'}
                    aria-pressed={showPassword}
                    class="rounded-lg p-2 text-muted transition-colors hover:bg-surface hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50"
                >
                    {#if showPassword}
                        <EyeOff class="size-[18px]" strokeWidth={1.75} />
                    {:else}
                        <Eye class="size-[18px]" strokeWidth={1.75} />
                    {/if}
                </button>
            {/snippet}
        </IconInput>
        {#if form.errors.password}
            <p class="text-sm text-error">{form.errors.password}</p>
        {/if}
    </div>

    <div class="flex items-center pt-2">
        <input
            type="checkbox"
            id="remember"
            bind:checked={form.remember}
            class="size-4 cursor-pointer rounded border-line bg-surface accent-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
        />
        <label
            for="remember"
            class="ml-2 block cursor-pointer text-sm text-muted transition-colors hover:text-ink"
        >
            Remember this device for 30 days
        </label>
    </div>

    <div class="pt-4">
        <Button
            type="submit"
            disabled={form.processing}
            class="group h-auto w-full rounded-xl bg-accent py-3.5 text-[15px] font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong hover:shadow-btn-strong active:translate-y-0 active:shadow-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-70"
        >
            {form.processing ? 'Signing in…' : 'Continue'}
            {#if !form.processing}
                <ArrowRight
                    class="size-[18px] transition-transform group-hover:translate-x-1"
                    strokeWidth={2}
                />
            {/if}
        </Button>
    </div>
</form>
