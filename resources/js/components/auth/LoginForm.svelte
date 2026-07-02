<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    let {
        oncontinue,
    }: {
        /** Fired when the user submits valid-looking credentials. */
        oncontinue?: () => void;
    } = $props();

    let email = $state('');
    let password = $state('');
    let showPassword = $state(false);

    // Shared field styling — the dark "input-field" look from the design.
    const fieldClass =
        'h-auto rounded-lg border-[#232B27] bg-[#141816] px-4 py-3 text-sm text-[#E2E8E4] shadow-none placeholder-sage-700 focus-visible:border-[#A3B18A] focus-visible:ring-0';

    function submit() {
        oncontinue?.();
    }
</script>

<div class="mb-8 text-center">
    <h2 class="mb-2 text-xl font-semibold text-white">Welcome back</h2>
    <p class="text-sm text-white">
        Enter your credentials to access your dashboard.
    </p>
</div>

<form
    class="space-y-6"
    onsubmit={(e) => {
        e.preventDefault();
        submit();
    }}
>
    <div class="space-y-2">
        <Label for="email" class="ml-1 text-[13px] font-medium text-white">
            Email address
        </Label>
        <Input
            id="email"
            type="email"
            autocomplete="email"
            placeholder="name@company.com"
            bind:value={email}
            class="w-full {fieldClass}"
        />
    </div>

    <div class="space-y-2">
        <div class="ml-1 flex items-center justify-between">
            <Label for="password" class="text-[13px] font-medium text-white">
                Password
            </Label>
            <a href="#" class="text-[12px] text-[#A3B18A] hover:underline">
                Forgot password?
            </a>
        </div>
        <div class="relative">
            <Input
                id="password"
                type={showPassword ? 'text' : 'password'}
                autocomplete="current-password"
                placeholder="••••••••"
                bind:value={password}
                class="w-full pr-11 {fieldClass}"
            />
            <button
                type="button"
                onclick={() => (showPassword = !showPassword)}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                aria-pressed={showPassword}
                class="absolute top-1/2 right-4 -translate-y-1/2 text-sage-600 transition-colors hover:text-sage-400"
            >
                {#if showPassword}
                    <svg
                        class="size-[18px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                        <path
                            d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"
                        />
                        <path
                            d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"
                        />
                        <line x1="2" x2="22" y1="2" y2="22" />
                    </svg>
                {:else}
                    <svg
                        class="size-[18px]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path
                            d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"
                        />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                {/if}
            </button>
        </div>
    </div>

    <Button
        type="submit"
        class="mt-4 h-auto w-full rounded-lg bg-[#A3B18A] py-3.5 text-sm font-semibold text-[#0A0C0B] shadow-none hover:bg-[#A3B18A] hover:opacity-90"
    >
        Continue
    </Button>
</form>
