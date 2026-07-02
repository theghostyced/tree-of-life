<script lang="ts">
    /**
     * Segmented one-time-code input. Renders `length` single-digit boxes with a
     * dash separator after `groupAfter` boxes (e.g. 3 — 3). Auto-advances on
     * entry and steps back on backspace. `value` is two-way bindable.
     */
    let {
        value = $bindable(''),
        length = 6,
        groupAfter = 3,
    }: {
        value?: string;
        length?: number;
        groupAfter?: number;
    } = $props();

    let digits = $state<string[]>(
        Array.from({ length }, (_, i) => value[i] ?? ''),
    );
    let inputs: HTMLInputElement[] = [];

    // Keep the bound value in sync with the individual boxes.
    $effect(() => {
        value = digits.join('');
    });

    function handleInput(index: number, event: Event) {
        const target = event.target as HTMLInputElement;
        const digit = target.value.replace(/\D/g, '').slice(-1);
        digits[index] = digit;
        target.value = digit;
        if (digit && index < length - 1) {
            inputs[index + 1]?.focus();
            inputs[index + 1]?.select();
        }
    }

    function handleKeydown(index: number, event: KeyboardEvent) {
        if (event.key === 'Backspace' && !digits[index] && index > 0) {
            event.preventDefault();
            inputs[index - 1]?.focus();
            digits[index - 1] = '';
        } else if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault();
            inputs[index - 1]?.focus();
        } else if (event.key === 'ArrowRight' && index < length - 1) {
            event.preventDefault();
            inputs[index + 1]?.focus();
        }
    }

    function handlePaste(event: ClipboardEvent) {
        event.preventDefault();
        const pasted = (event.clipboardData?.getData('text') ?? '')
            .replace(/\D/g, '')
            .slice(0, length);
        for (let i = 0; i < length; i++) {
            digits[i] = pasted[i] ?? '';
        }
        inputs[Math.min(pasted.length, length - 1)]?.focus();
    }
</script>

<div class="flex w-full items-center justify-between gap-2">
    {#each digits as digit, index (index)}
        {#if index === groupAfter}
            <div class="flex shrink-0 items-center justify-center px-1">
                <div class="h-0.5 w-2 rounded-full bg-faint"></div>
            </div>
        {/if}
        <input
            bind:this={inputs[index]}
            type="text"
            inputmode="numeric"
            maxlength="1"
            aria-label={`Digit ${index + 1}`}
            value={digit}
            oninput={(e) => handleInput(index, e)}
            onkeydown={(e) => handleKeydown(index, e)}
            onpaste={handlePaste}
            onfocus={(e) => (e.target as HTMLInputElement).select()}
            class="auth-input h-14 min-w-0 max-w-12 flex-1 rounded-xl text-center text-2xl font-semibold text-ink"
        />
    {/each}
</div>
