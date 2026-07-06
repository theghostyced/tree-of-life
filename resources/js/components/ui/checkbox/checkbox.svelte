<script lang="ts">
	import { Checkbox as CheckboxPrimitive } from "bits-ui";
	import { cn, type WithoutChildrenOrChild } from "@/lib/utils.js";
	import CheckIcon from '@lucide/svelte/icons/check';
	import MinusIcon from '@lucide/svelte/icons/minus';

	let {
		ref = $bindable(null),
		checked = $bindable(false),
		indeterminate = $bindable(false),
		class: className,
		...restProps
	}: WithoutChildrenOrChild<CheckboxPrimitive.RootProps> = $props();
</script>

<CheckboxPrimitive.Root
	bind:ref
	data-slot="checkbox"
	class={cn(
		"peer relative flex size-4 shrink-0 items-center justify-center rounded-[5px] border border-line-strong bg-surface text-on-accent outline-none transition-colors",
		"hover:border-accent/60",
		"data-[state=checked]:border-accent data-[state=checked]:bg-accent data-[state=indeterminate]:border-accent data-[state=indeterminate]:bg-accent",
		"focus-visible:border-accent focus-visible:ring-3 focus-visible:ring-accent/40",
		"disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-line-strong",
		"after:absolute after:-inset-x-2 after:-inset-y-2",
		className
	)}
	bind:checked
	bind:indeterminate
	{...restProps}
>
	{#snippet children({ checked, indeterminate })}
		<div
			data-slot="checkbox-indicator"
			class="grid place-content-center text-current transition-none [&>svg]:size-3.5"
		>
			{#if checked}
				<CheckIcon strokeWidth={3} />
			{:else if indeterminate}
				<MinusIcon strokeWidth={3} />
			{/if}
		</div>
	{/snippet}
</CheckboxPrimitive.Root>
