<script lang="ts">
    import { ArrowLeft, ShieldCheck } from '@lucide/svelte';
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

<div class="mb-10">
    <button
        type="button"
        onclick={() => onback?.()}
        aria-label="Back to sign in"
        class="mb-8 flex size-10 items-center justify-center rounded-full border border-line bg-surface text-muted transition-all hover:border-line-strong hover:text-ink"
    >
        <ArrowLeft class="size-[18px]" strokeWidth={1.75} />
    </button>
    <h2 class="mb-3 text-3xl font-semibold tracking-tight text-ink">
        Two-step verification
    </h2>
    <p class="text-[15px] text-muted">
        We've sent a code to your authenticator app. Enter it below to verify
        your identity.
    </p>
</div>

<form class="space-y-8" onsubmit={(e) => { e.preventDefault(); onverify?.(code); }}>
    <OtpInput bind:value={code} />

    <div class="flex flex-col gap-4">
        <Button
            type="submit"
            class="group h-auto w-full rounded-xl bg-accent py-3.5 text-[15px] font-semibold text-on-accent shadow-[0_4px_14px_0_rgba(163,177,138,0.15)] transition-all hover:-translate-y-px hover:bg-accent-strong hover:shadow-[0_6px_20px_0_rgba(163,177,138,0.2)]"
        >
            Verify identity
            <ShieldCheck class="size-[18px]" strokeWidth={1.75} />
        </Button>

        <p class="text-center text-sm text-muted">
            Having trouble?
            <a
                href="#"
                class="font-medium text-accent underline decoration-accent/30 underline-offset-4 transition-colors hover:text-accent-strong"
            >
                Use a recovery code
            </a>
        </p>
    </div>
</form>
