<script lang="ts">
    import { Check, Minus } from '@lucide/svelte';
    import type { RequiredDocument, SummaryItem } from './types';

    /**
     * What has been saved so far, so a returning user can see their earlier
     * answers without stepping back through every form.
     */
    let {
        items,
        documents,
        onedit,
    }: {
        items: SummaryItem[];
        documents: RequiredDocument[];
        onedit?: (step: number) => void;
    } = $props();
</script>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section aria-labelledby="summary-answers">
        <div class="flex items-baseline justify-between gap-3">
            <h3
                id="summary-answers"
                class="text-[11px] font-medium tracking-wide text-faint uppercase"
            >
                Your answers
            </h3>
            {#if onedit}
                <button
                    type="button"
                    onclick={() => onedit(0)}
                    class="-my-2 -mr-2 inline-flex min-h-11 items-center rounded-md px-2 text-xs font-medium text-accent outline-none transition-colors hover:text-accent-strong focus-visible:ring-2 focus-visible:ring-accent/60"
                >
                    Edit
                </button>
            {/if}
        </div>
        <dl class="mt-3 divide-y divide-line border-y border-line">
            {#each items as item (item.label)}
                <div
                    class="grid gap-1 py-3 sm:grid-cols-[minmax(0,10rem)_1fr] sm:gap-4"
                >
                    <dt class="text-sm text-muted">{item.label}</dt>
                    <dd
                        class="min-w-0 text-sm break-words {item.value
                            ? 'text-ink'
                            : 'text-faint italic'}"
                    >
                        {item.value ?? 'Not provided'}
                    </dd>
                </div>
            {/each}
        </dl>
    </section>

    <section aria-labelledby="summary-documents">
        <div class="flex items-baseline justify-between gap-3">
            <h3
                id="summary-documents"
                class="text-[11px] font-medium tracking-wide text-faint uppercase"
            >
                Documents
            </h3>
            {#if onedit}
                <button
                    type="button"
                    onclick={() => onedit(2)}
                    class="-my-2 -mr-2 inline-flex min-h-11 items-center rounded-md px-2 text-xs font-medium text-accent outline-none transition-colors hover:text-accent-strong focus-visible:ring-2 focus-visible:ring-accent/60"
                >
                    Edit
                </button>
            {/if}
        </div>
        <ul class="mt-3 divide-y divide-line border-y border-line">
            {#each documents as doc (doc.type)}
                <li class="flex items-center gap-3 py-3">
                    <span
                        class="flex size-6 shrink-0 items-center justify-center rounded-full {doc.uploaded
                            ? 'bg-accent-soft text-accent'
                            : 'bg-elevated text-faint'}"
                        aria-hidden="true"
                    >
                        {#if doc.uploaded}
                            <Check class="size-3.5" strokeWidth={2.5} />
                        {:else}
                            <Minus class="size-3.5" strokeWidth={2} />
                        {/if}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-ink">{doc.label}</p>
                        <p
                            class="truncate text-xs {doc.uploaded
                                ? 'text-muted'
                                : 'text-faint italic'}"
                        >
                            {doc.uploaded ?? 'Not uploaded yet'}
                        </p>
                    </div>
                </li>
            {/each}
        </ul>
    </section>
</div>
