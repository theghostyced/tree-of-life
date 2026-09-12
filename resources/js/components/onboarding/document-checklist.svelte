<script lang="ts">
    import { FileText } from '@lucide/svelte';
    import type { RequiredDocument } from './types';

    /**
     * Shown on the first onboarding step so people can gather the files before
     * they reach the upload step, rather than discovering the list there.
     */
    let {
        documents,
        fileHint,
    }: { documents: RequiredDocument[]; fileHint: string } = $props();
</script>

<aside
    class="border-b border-line pb-6"
    aria-labelledby="doc-checklist-heading"
>
    <div class="flex items-start gap-3">
        <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent"
        >
            <FileText class="size-4.5" strokeWidth={1.75} />
        </div>
        <div class="min-w-0">
            <p
                id="doc-checklist-heading"
                class="text-sm font-semibold text-ink"
            >
                Before you start, have these documents ready
            </p>
            <p class="mt-0.5 text-sm text-muted">
                You upload them in the Documents step. {fileHint}
            </p>
        </div>
    </div>
    <ul class="mt-4 space-y-3">
        {#each documents as doc (doc.type)}
            <li class="flex gap-3 text-sm">
                <span
                    class="mt-1.5 size-1.5 shrink-0 rounded-full {doc.uploaded
                        ? 'bg-accent'
                        : 'bg-faint'}"
                    aria-hidden="true"
                ></span>
                <div class="min-w-0">
                    <p class="font-medium text-ink">
                        {doc.label}
                        {#if doc.uploaded}
                            <span class="ml-1.5 text-xs font-normal text-accent"
                                >Uploaded</span
                            >
                        {/if}
                    </p>
                    <p class="text-muted">{doc.description}</p>
                </div>
            </li>
        {/each}
    </ul>
</aside>
