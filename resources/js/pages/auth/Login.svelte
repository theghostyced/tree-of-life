<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Logo from '@/components/Logo.svelte';
    import AuthBrandPanel from '@/components/auth/AuthBrandPanel.svelte';
    import LoginForm from '@/components/auth/LoginForm.svelte';
    import MfaForm from '@/components/auth/MfaForm.svelte';
    import AuthFooter from '@/components/auth/AuthFooter.svelte';

    type Step = 'credentials' | 'mfa';
    let step = $state<Step>('credentials');

    function completeLogin() {
        router.visit('/admin/dashboard');
    }
</script>

<AppHead title="Sign in">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    />
</AppHead>

<div
    class="flex h-screen w-full overflow-hidden bg-canvas text-ink selection:bg-accent selection:text-on-accent"
    style="font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;"
>
    <AuthBrandPanel />

    <div class="relative flex w-full flex-col bg-canvas lg:w-[55%]">
        <!-- Mobile brand header -->
        <div
            class="absolute top-0 right-0 left-0 z-20 flex items-center justify-between border-b border-line bg-panel/50 p-6 backdrop-blur-md lg:hidden"
        >
            <Logo size="sm" />
        </div>

        <div
            class="relative z-10 flex flex-1 flex-col items-center justify-center p-6 sm:p-12"
        >
            <div
                class="pointer-events-none absolute inset-0"
                style="background: radial-gradient(ellipse at top right, rgba(163,177,138,0.03), transparent 50%);"
                aria-hidden="true"
            ></div>

            <div class="w-full max-w-[420px]">
                {#if step === 'credentials'}
                    <div class="animate-fade-in">
                        <LoginForm oncontinue={() => (step = 'mfa')} />
                    </div>
                {:else}
                    <div class="animate-fade-in">
                        <MfaForm
                            onback={() => (step = 'credentials')}
                            onverify={completeLogin}
                        />
                    </div>
                {/if}
            </div>
        </div>

        <AuthFooter class="lg:hidden" />
    </div>
</div>
