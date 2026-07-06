import type { ColumnDef } from '@tanstack/table-core';
import { renderComponent } from '@/components/ui/data-table';
import { type UserRow, relative } from './types';
import SortableHeader from '@/components/data-table/sortable-header.svelte';
import RoleCell from '@/components/data-table/role-cell.svelte';
import TextCell from '@/components/data-table/text-cell.svelte';
import UserIdentity from './user-identity.svelte';
import AccountStatus from './account-status.svelte';
import UserActions from './user-actions.svelte';

type Handlers = {
    currentUserId: number;
    onDeactivate: (u: UserRow) => void;
    onReactivate: (u: UserRow) => void;
    onDelete: (u: UserRow) => void;
};

export function createColumns(handlers: Handlers): ColumnDef<UserRow>[] {
    return [
        {
            id: 'sn',
            enableSorting: false,
            meta: { width: '4%' },
            header: 'S/N',
            cell: () => '',
        },
        {
            id: 'user',
            accessorFn: (row) => `${row.name} ${row.email}`,
            meta: { width: '30%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'User', column }),
            cell: ({ row }) =>
                renderComponent(UserIdentity, { user: row.original }),
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
                renderComponent(AccountStatus, { status: row.original.status }),
        },
        {
            accessorKey: 'joinedAt',
            meta: { width: '13%' },
            header: ({ column }) =>
                renderComponent(SortableHeader, { label: 'Joined', column }),
            cell: ({ row }) =>
                renderComponent(TextCell, {
                    value: relative(row.original.joinedAt),
                    tone: 'muted',
                }),
        },
        {
            id: 'actions',
            enableSorting: false,
            meta: { width: '18%', align: 'right' },
            header: 'Actions',
            cell: ({ row }) =>
                renderComponent(UserActions, {
                    user: row.original,
                    currentUserId: handlers.currentUserId,
                    onDeactivate: handlers.onDeactivate,
                    onReactivate: handlers.onReactivate,
                    onDelete: handlers.onDelete,
                }),
        },
    ];
}
