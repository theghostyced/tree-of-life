<script lang="ts">
    import { Button } from '@/components/ui/button';
    import OtpInput from './OtpInput.svelte';

    let {
        onback,
        onverify,
    }: {
        /** Fired when the user returns to the credentials step. */
        onback?: () => void;
        /** Fired with the entered code when the user verifies. */
        onverify?: (code: string) => void;
    } = $props();

    // Prefilled to mirror the design's partially-entered state.
    let code = $state('492');
</script>

<div class="mb-8 text-center">
    <div
        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-[#232B27] bg-[#1A1F1C]"
    >
        <svg class="size-5 text-[#A3B18A]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
            <circle cx="12" cy="10" r="1.5" />
            <path d="M12 11.5V15" />
        </svg>
    </div>
    <h2 class="mb-2 text-xl font-semibold text-white">Security verification</h2>
    <p class="text-sm text-white">
        Enter the 6-digit code from your authenticator app.
    </p>
</div>

<div class="mb-8">
    <OtpInput bind:value={code} />
</div>

<Button
    type="button"
    onclick={() => onverify?.(code)}
    class="mb-6 h-auto w-full rounded-lg bg-[#A3B18A] py-3.5 text-sm font-semibold text-[#0A0C0B] shadow-none hover:bg-[#A3B18A] hover:opacity-90"
>
    Verify and login
</Button>

<div class="text-center">
    <button
        type="button"
        onclick={() => onback?.()}
        class="inline-flex items-center text-[13px] text-white transition-colors hover:text-white/80"
    >
        <svg class="mr-1 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m12 19-7-7 7-7" />
            <path d="M19 12H5" />
        </svg>
        Back to login
    </button>
</div>
