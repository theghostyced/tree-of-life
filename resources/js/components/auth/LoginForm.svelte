<script lang="ts">
    import { Mail, Lock, Eye, EyeOff, ArrowRight } from '@lucide/svelte';
    import { Button } from '@/components/ui/button';
    import IconInput from './IconInput.svelte';

    let {
        oncontinue,
    }: {
        /** Fired when the user submits their credentials. */
        oncontinue?: () => void;
    } = $props();

    let email = $state('');
    let password = $state('');
    let remember = $state(false);
    let showPassword = $state(false);
</script>

<div class="mb-10">
    <h2 class="mb-3 text-3xl font-semibold tracking-tight text-ink">
        Welcome back
    </h2>
    <p class="text-[15px] text-muted">Sign in to your account to continue.</p>
</div>

<form
    class="space-y-5"
    onsubmit={(e) => {
        e.preventDefault();
        oncontinue?.();
    }}
>
    <div class="space-y-2">
        <label for="email" class="block text-sm font-medium text-muted">
            Email address
        </label>
        <IconInput
            id="email"
            type="email"
            autocomplete="email"
            placeholder="name@company.com"
            bind:value={email}
        >
            {#snippet icon()}
                <Mail class="size-[18px]" strokeWidth={1.75} />
            {/snippet}
        </IconInput>
    </div>

    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <label for="password" class="text-sm font-medium text-muted">
                Password
            </label>
            <a
                href="#"
                class="text-sm font-medium text-accent transition-colors hover:text-accent-strong"
            >
                Forgot password?
            </a>
        </div>
        <IconInput
            id="password"
            type={showPassword ? 'text' : 'password'}
            autocomplete="current-password"
            placeholder="••••••••"
            bind:value={password}
        >
            {#snippet icon()}
                <Lock class="size-[18px]" strokeWidth={1.75} />
            {/snippet}
            {#snippet trailing()}
                <button
                    type="button"
                    onclick={() => (showPassword = !showPassword)}
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                    aria-pressed={showPassword}
                    class="rounded-lg p-2 text-muted transition-colors hover:bg-surface hover:text-ink"
                >
                    {#if showPassword}
                        <EyeOff class="size-[18px]" strokeWidth={1.75} />
                    {:else}
                        <Eye class="size-[18px]" strokeWidth={1.75} />
                    {/if}
                </button>
            {/snippet}
        </IconInput>
    </div>

    <div class="flex items-center pt-2">
        <input
            type="checkbox"
            id="remember"
            bind:checked={remember}
            class="size-4 rounded border-line bg-surface accent-[#a3b18a]"
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
            class="group h-auto w-full rounded-xl bg-accent py-3.5 text-[15px] font-semibold text-on-accent shadow-[0_4px_14px_0_rgba(163,177,138,0.15)] transition-all hover:-translate-y-px hover:bg-accent-strong hover:shadow-[0_6px_20px_0_rgba(163,177,138,0.2)]"
        >
            Continue
            <ArrowRight
                class="size-[18px] transition-transform group-hover:translate-x-1"
                strokeWidth={2}
            />
        </Button>
    </div>
</form>
