<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Card } from '@/components/ui/card';
    import AuthBackground from '@/components/auth/AuthBackground.svelte';
    import BrandMark from '@/components/auth/BrandMark.svelte';
    import LoginForm from '@/components/auth/LoginForm.svelte';
    import MfaForm from '@/components/auth/MfaForm.svelte';
    import AuthFooter from '@/components/auth/AuthFooter.svelte';

    type Step = 'credentials' | 'mfa';
    let step = $state<Step>('credentials');

    function completeLogin() {
        router.visit('/admin/dashboard');
    }
</script>

<AppHead title="Login">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    />
</AppHead>

<div
    class="relative flex min-h-screen w-full items-center justify-center overflow-hidden bg-[#0A0C0B] p-6 text-[#E2E8E4]"
    style="font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;"
>
    <AuthBackground />

    <div class="z-10 flex w-full max-w-[440px] flex-col items-center">
        <div class="mb-10">
            <BrandMark name="Tree Of Life Fund" />
        </div>

        <Card
            class="w-full rounded-2xl border-[#1A1F1C] bg-[#0D100E] p-6 shadow-2xl gap-0 sm:p-10"
        >
            {#if step === 'credentials'}
                <LoginForm oncontinue={() => (step = 'mfa')} />
            {:else}
                <MfaForm
                    onback={() => (step = 'credentials')}
                    onverify={completeLogin}
                />
            {/if}
        </Card>

        <div class="mt-8">
            <AuthFooter />
        </div>
    </div>
</div>
