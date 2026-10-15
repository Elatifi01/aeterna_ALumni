import { Form, Head, Link } from '@inertiajs/react';
import { Plus, ToggleLeft, ToggleRight, UsersRound } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { AvatarInitials } from '@/components/avatar-initials';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { DataTable, type Column } from '@/components/data-table/data-table';
import { navigateWithParams } from '@/components/data-table/pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { StatusBadge } from '@/components/status-badge';
import { Toolbar } from '@/components/toolbar';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermissions } from '@/lib/permissions';
import { create, edit, index } from '@/routes/users';
import type { Paginated, PlanUsage, SortDirection } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    role: string | null;
    is_self: boolean;
};

type RoleOption = { id: number; name: string };

type Props = {
    users: Paginated<UserRow>;
    filters: {
        search: string | null;
        role_id: number | null;
        sort: string;
        direction: SortDirection;
        per_page: number;
    };
    roles?: RoleOption[];
    usage: PlanUsage;
};

export default function UsersIndex({ users, filters, roles, usage }: Props) {
    const { can } = usePermissions();
    const [selected, setSelected] = useState<(number | string)[]>([]);
    const [pendingDelete, setPendingDelete] = useState<UserRow | null>(null);

    const atLimit = usage.limit !== null && usage.remaining === 0;

    const nameParts = (value: string) => {
        const [first, ...rest] = value.split(' ');

        return { first: first ?? '', last: rest.join(' ') || (first ?? '') };
    };

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: 'Nom',
            sortable: true,
            cell: (row) => {
                const { first, last } = nameParts(row.name);

                return (
                    <div className="flex items-center gap-3">
                        <AvatarInitials firstName={first} lastName={last} />
                        <span className="min-w-0">
                            <span className="text-text block truncate font-medium">
                                {row.name}
                                {row.is_self && (
                                    <span className="text-text-muted ml-2 text-xs font-normal">
                                        (vous)
                                    </span>
                                )}
                            </span>
                            <span className="text-text-muted block truncate text-xs">
                                {row.email}
                            </span>
                        </span>
                    </div>
                );
            },
        },
        {
            key: 'role',
            header: 'Role',
            cell: (row) => row.role ?? '—',
        },
        {
            key: 'status',
            header: 'Statut',
            cell: (row) =>
                row.is_active ? (
                    <StatusBadge tone="success" label="Actif" />
                ) : (
                    <StatusBadge tone="neutral" label="Desactive" />
                ),
        },
    ];

    return (
        <>
            <Head title="Utilisateurs" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Utilisateurs"
                    description={
                        usage.limit === null
                            ? `${users.total} comptes dans votre organisation`
                            : `${usage.used} / ${usage.limit} comptes utilises sur votre plan`
                    }
                    action={
                        can('users.create') && (
                            <Button asChild disabled={atLimit}>
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Ajouter un utilisateur
                                </Link>
                            </Button>
                        )
                    }
                />
                <Toolbar
                    searchValue={filters.search}
                    searchLabel="Rechercher un utilisateur"
                    searchPlaceholder="Rechercher un nom ou un e-mail"
                    filters={[
                        {
                            name: 'role_id',
                            label: 'Role',
                            value: filters.role_id,
                            options: (roles ?? []).map((role) => ({
                                value: role.id,
                                label: role.name,
                            })),
                        },
                    ]}
                />
                <DataTable
                    rows={users}
                    columns={columns}
                    rowKey={(row) => row.id}
                    sort={filters.sort}
                    direction={filters.direction}
                    onNavigate={navigateWithParams}
                    selected={selected}
                    onSelectedChange={setSelected}
                    actions={(row) => (
                        <RowActions
                            label={row.name}
                            edit={
                                can('users.update') ? edit(row.id) : undefined
                            }
                            onDelete={
                                can('users.delete') && !row.is_self
                                    ? () => setPendingDelete(row)
                                    : undefined
                            }
                        >
                            {can('users.update') && !row.is_self && (
                                <Form
                                    {...UserController.toggleActive.form(
                                        row.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                >
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="icon"
                                                className="text-text-secondary hover:text-text size-8"
                                                aria-label={`${row.is_active ? 'Desactiver' : 'Activer'} ${row.name}`}
                                            >
                                                {row.is_active ? (
                                                    <ToggleRight className="size-4" />
                                                ) : (
                                                    <ToggleLeft className="size-4" />
                                                )}
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {row.is_active
                                                ? 'Desactiver'
                                                : 'Activer'}
                                        </TooltipContent>
                                    </Tooltip>
                                </Form>
                            )}
                        </RowActions>
                    )}
                    empty={
                        <EmptyState
                            icon={UsersRound}
                            title="Aucun utilisateur ne correspond a votre recherche"
                            description="Modifiez ou reinitialisez vos filtres pour elargir la recherche."
                        />
                    }
                />
            </div>
            <ConfirmDeleteDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
                action={
                    pendingDelete
                        ? UserController.destroy.form(pendingDelete.id)
                        : null
                }
                title={`Supprimer le compte de ${pendingDelete?.name} ?`}
                description="Le compte part a la corbeille et ne pourra plus se connecter. Vous pourrez le restaurer."
            />
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Utilisateurs', href: index() }],
};
