import type { ColumnDef, RowData } from '@tanstack/table-core';
import { renderComponent } from '@/components/ui/data-table';
import type { Invitation } from './types';
import SortableHeader from './data-table-sortable-header.svelte';
import Invitee from './data-table-invitee.svelte';
import RoleCell from './data-table-role.svelte';
import StatusCell from './data-table-status.svelte';
import Timeline from './data-table-timeline.svelte';
import Actions from './data-table-actions.svelte';

declare module '@tanstack/table-core' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        /** Column width for the table's <colgroup> (used with `table-fixed`). */
        width?: string;
        /** Horizontal alignment of the column's header + cells. */
        align?: 'left' | 'right';
    }
}

type Handlers = {
    onResend: (inv: Invitation) => void;
    onRevoke: (inv: Invitation) => void;
};

export function createColumns(handlers: Handlers): ColumnDef<Invitation>[] {
    return [
        {
            id: 'sn',
            enableSorting: false,
            meta: { width: '6%' },
            header: 'S/N',
            // Rendered directly in data-table.svelte, which has the visual row index.
            cell: () => '',
        },
        {
            id: 'invitee',
            accessorFn: (row) => `${row.name ?? ''} ${row.email}`,
            meta: { width: '24%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Invitee', column }),
            cell: ({ row }) =>
                renderComponent(Invitee, { invitation: row.original }),
        },
        {
            accessorKey: 'role',
            meta: { width: '12%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Role', column }),
            cell: ({ row }) =>
                renderComponent(RoleCell, { role: row.original.role }),
        },
        {
            accessorKey: 'status',
            meta: { width: '13%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Status', column }),
            cell: ({ row }) =>
                renderComponent(StatusCell, { status: row.original.status }),
        },
        {
            accessorKey: 'sentAt',
            meta: { width: '26%', align: 'right' },
            header: ({ column }) =>
                renderComponent(SortableHeader, {
                    label: 'Sent',
                    column,
                    align: 'right',
                }),
            cell: ({ row }) =>
                renderComponent(Timeline, { invitation: row.original }),
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { width: '19%', align: 'right' },
            header: 'Actions',
            cell: ({ row }) =>
                renderComponent(Actions, {
                    invitation: row.original,
                    onResend: handlers.onResend,
                    onRevoke: handlers.onRevoke,
                }),
        },
    ];
}
