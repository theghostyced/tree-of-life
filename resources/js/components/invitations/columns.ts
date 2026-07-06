import type { ColumnDef, RowData } from '@tanstack/table-core';
import { renderComponent } from '@/components/ui/data-table';
import { type Invitation, relative } from './types';
import SortableHeader from '@/components/data-table/sortable-header.svelte';
import RoleCell from '@/components/data-table/role-cell.svelte';
import TextCell from '@/components/data-table/text-cell.svelte';
import Invitee from './data-table-invitee.svelte';
import StatusCell from './data-table-status.svelte';
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
            meta: { width: '4%' },
            header: 'S/N',
            // Rendered directly in data-table.svelte, which has the visual row index.
            cell: () => '',
        },
        {
            id: 'invitee',
            accessorFn: (row) => `${row.name ?? ''} ${row.email}`,
            meta: { width: '23%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Invitee', column }),
            cell: ({ row }) =>
                renderComponent(Invitee, { invitation: row.original }),
        },
        {
            accessorKey: 'role',
            meta: { width: '10%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Role', column }),
            cell: ({ row }) =>
                renderComponent(RoleCell, { role: row.original.role }),
        },
        {
            accessorKey: 'status',
            meta: { width: '12%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Status', column }),
            cell: ({ row }) =>
                renderComponent(StatusCell, { status: row.original.status }),
        },
        {
            accessorKey: 'invitedBy',
            meta: { width: '13%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, {
                    label: 'Invited by',
                    column,
                }),
            cell: ({ row }) =>
                renderComponent(TextCell, {
                    value: row.original.invitedBy,
                    tone: 'muted',
                }),
        },
        {
            accessorKey: 'sentAt',
            meta: { width: '11%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Sent', column }),
            cell: ({ row }) =>
                renderComponent(TextCell, {
                    value: relative(row.original.sentAt),
                    tone: 'muted',
                }),
        },
        {
            accessorKey: 'expiresAt',
            meta: { width: '11%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Expires', column }),
            cell: ({ row }) =>
                renderComponent(TextCell, {
                    value:
                        row.original.status === 'pending'
                            ? relative(row.original.expiresAt)
                            : '—',
                    tone: row.original.status === 'pending' ? 'muted' : 'faint',
                }),
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { width: '16%', align: 'right' },
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
