<script lang="ts" generics="TData, TValue">
    import {
        type ColumnDef,
        type SortingState,
        type PaginationState,
        getCoreRowModel,
        getSortedRowModel,
        getPaginationRowModel,
        getFilteredRowModel,
    } from '@tanstack/table-core';
    import { createSvelteTable, FlexRender } from '@/components/ui/data-table';
    import * as Table from '@/components/ui/table';
    import { Search, ChevronLeft, ChevronRight } from '@lucide/svelte';
    import { cn } from '@/lib/utils';

    let {
        data,
        columns,
    }: { data: TData[]; columns: ColumnDef<TData, TValue>[] } = $props();

    let sorting = $state<SortingState>([{ id: 'sentAt', desc: true }]);
    let pagination = $state<PaginationState>({ pageIndex: 0, pageSize: 20 });
    let globalFilter = $state('');

    const table = createSvelteTable({
        get data() {
            return data;
        },
        get columns() {
            return columns;
        },
        state: {
            get sorting() {
                return sorting;
            },
            get pagination() {
                return pagination;
            },
            get globalFilter() {
                return globalFilter;
            },
        },
        globalFilterFn: 'includesString',
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onSortingChange: (u) => {
            sorting = typeof u === 'function' ? u(sorting) : u;
        },
        onPaginationChange: (u) => {
            pagination = typeof u === 'function' ? u(pagination) : u;
        },
        onGlobalFilterChange: (u) => {
            globalFilter = typeof u === 'function' ? u(globalFilter) : u;
        },
    });

    const pageBtn =
        'inline-flex items-center gap-1 rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:border-line-strong hover:text-ink disabled:pointer-events-none disabled:opacity-40 focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:outline-none';
</script>

<div class="w-full">
    <!-- Toolbar -->
    <div class="flex items-center justify-between gap-3 pb-4">
        <div class="relative w-full max-w-xs">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-faint"
                strokeWidth={1.75}
            />
            <input
                type="search"
                value={globalFilter}
                oninput={(e) => table.setGlobalFilter(e.currentTarget.value)}
                placeholder="Search name or email"
                class="h-9 w-full rounded-lg border border-line bg-surface pr-3 pl-9 text-sm text-ink transition-colors placeholder:text-faint hover:border-line-strong focus:border-accent focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:outline-none"
            />
        </div>
        <p class="hidden shrink-0 text-xs text-faint sm:block">
            {table.getFilteredRowModel().rows.length}
            {table.getFilteredRowModel().rows.length === 1
                ? 'invitation'
                : 'invitations'}
        </p>
    </div>

    <div class="overflow-hidden rounded-xl border border-line bg-surface">
        <Table.Root class="min-w-[820px] table-fixed">
            <colgroup>
                {#each table.getVisibleLeafColumns() as col (col.id)}
                    <col
                        style={col.columnDef.meta?.width
                            ? `width:${col.columnDef.meta.width}`
                            : undefined}
                    />
                {/each}
            </colgroup>
            <Table.Header>
                {#each table.getHeaderGroups() as headerGroup (headerGroup.id)}
                    <Table.Row class="hover:bg-transparent">
                        {#each headerGroup.headers as header (header.id)}
                            <Table.Head
                                class={cn(
                                    'px-5',
                                    header.column.columnDef.meta?.align ===
                                        'right' && 'text-right',
                                )}
                            >
                                {#if !header.isPlaceholder}
                                    <FlexRender
                                        content={header.column.columnDef.header}
                                        context={header.getContext()}
                                    />
                                {/if}
                            </Table.Head>
                        {/each}
                    </Table.Row>
                {/each}
            </Table.Header>
            <Table.Body>
                {#each table.getRowModel().rows as row, rowIndex (row.id)}
                    <Table.Row>
                        {#each row.getVisibleCells() as cell (cell.id)}
                            <Table.Cell
                                class={cn(
                                    'px-5 py-3',
                                    cell.column.columnDef.meta?.align ===
                                        'right' && 'text-right',
                                )}
                            >
                                {#if cell.column.id === 'sn'}
                                    <span
                                        class="text-xs font-medium text-faint tabular-nums"
                                    >
                                        {table.getState().pagination.pageIndex *
                                            table.getState().pagination
                                                .pageSize +
                                            rowIndex +
                                            1}
                                    </span>
                                {:else}
                                    <FlexRender
                                        content={cell.column.columnDef.cell}
                                        context={cell.getContext()}
                                    />
                                {/if}
                            </Table.Cell>
                        {/each}
                    </Table.Row>
                {:else}
                    <Table.Row class="hover:bg-transparent">
                        <Table.Cell
                            colspan={columns.length}
                            class="h-28 text-center text-sm text-muted"
                        >
                            No invitations match your search.
                        </Table.Cell>
                    </Table.Row>
                {/each}
            </Table.Body>
        </Table.Root>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-between gap-3 pt-4">
        <p class="text-xs text-faint">
            Page {table.getState().pagination.pageIndex + 1} of {Math.max(
                1,
                table.getPageCount(),
            )}
        </p>
        <div class="flex items-center gap-2">
            <button
                type="button"
                onclick={() => table.previousPage()}
                disabled={!table.getCanPreviousPage()}
                class={pageBtn}
            >
                <ChevronLeft class="size-3.5" strokeWidth={2} />
                Previous
            </button>
            <button
                type="button"
                onclick={() => table.nextPage()}
                disabled={!table.getCanNextPage()}
                class={pageBtn}
            >
                Next
                <ChevronRight class="size-3.5" strokeWidth={2} />
            </button>
        </div>
    </div>
</div>
