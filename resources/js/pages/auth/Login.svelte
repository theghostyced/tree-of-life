<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Logo from '@/components/Logo.svelte';
    import AuthBrandPanel from '@/components/auth/AuthBrandPanel.svelte';
    import LoginForm from '@/components/auth/LoginForm.svelte';
    import AuthFooter from '@/components/auth/AuthFooter.svelte';
    import { Toaster } from '@/components/ui/sonner';
</script>

<AppHead title="Sign in" />
<Toaster position="top-center" />

<div
    class="flex h-screen w-full overflow-hidden bg-canvas font-sans text-ink selection:bg-accent selection:text-on-accent"
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
            <div class="relative z-10 w-full max-w-[420px] animate-fade-in">
                <LoginForm />
            </div>
        </div>

        <AuthFooter class="lg:hidden" />
    </div>
</div>
