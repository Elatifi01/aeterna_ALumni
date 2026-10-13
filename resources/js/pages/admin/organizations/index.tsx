import { Form, Head, Link } from '@inertiajs/react';
import { Building2, Plus, ToggleLeft, ToggleRight } from 'lucide-react';
import { useState } from 'react';
import OrganizationController from '@/actions/App/Http/Controllers/Admin/OrganizationController';
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
import { create, edit, index } from '@/routes/admin/organizations';
import type { Paginated, SortDirection } from '@/types';

type OrganizationRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    alumni_count: number;
    plan: string | null;
    ends_at: string | null;
};

type Props = {
    organizations: Paginated<OrganizationRow>;
    filters: {
        search: string | null;
        status: 'active' | 'inactive' | null;
        sort: string;
        direction: SortDirection;
        per_page: number;
    };
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function OrganizationsIndex({ organizations, filters }: Props) {
    const [selected, setSelected] = useState<(number | string)[]>([]);

    const columns: Column<OrganizationRow>[] = [
        {
            key: 'name',
            header: 'Organisation',
            sortable: true,
            cell: (row) => (
                <span className="min-w-0">
                    <Link
                        href={edit(row.id)}
                        className="text-text hover:text-accent block truncate font-medium"
                    >
                        {row.name}
                    </Link>
                    <span className="text-text-muted block truncate text-xs">
                        {row.email}
                    </span>
                </span>
            ),
        },
        {
            key: 'plan',
            header: 'Plan',
            cell: (row) =>
                row.plan ?? (
                    <span className="text-text-muted">Aucun abonnement</span>
                ),
        },
        {
            key: 'alumni_count',
            header: 'Alumni',
            className: 'tabular',
            cell: (row) => row.alumni_count,
        },
        {
            key: 'status',
            header: 'Statut',
            cell: (row) =>
                row.is_active ? (
                    <StatusBadge tone="success" label="Active" />
                ) : (
                    <StatusBadge tone="neutral" label="Desactivee" />
                ),
        },
        {
            key: 'ends_at',
            header: 'Fin d abonnement',
            secondary: true,
            className: 'tabular whitespace-nowrap',
            cell: (row) => formatDate(row.ends_at),
        },
    ];

    return (
        <>
            <Head title="Organisations" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Organisations"
                    description={`${organizations.total} organisations sur la plateforme`}
                    action={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Creer une organisation
                            </Link>
                        </Button>
                    }
                />
                <Toolbar
                    searchValue={filters.search}
                    searchLabel="Rechercher une organisation"
                    searchPlaceholder="Rechercher un nom ou un e-mail"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statut',
                            value: filters.status,
                            options: [
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Desactivee' },
                            ],
                        },
                    ]}
                />
                <DataTable
                    rows={organizations}
                    columns={columns}
                    rowKey={(row) => row.id}
                    sort={filters.sort}
                    direction={filters.direction}
                    onNavigate={navigateWithParams}
                    selected={selected}
                    onSelectedChange={setSelected}
                    actions={(row) => (
                        <RowActions label={row.name} edit={edit(row.id)}>
                            <Form
                                {...OrganizationController.toggleActive.form(
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
                        </RowActions>
                    )}
                    empty={
                        <EmptyState
                            icon={Building2}
                            title="Aucune organisation"
                            description="Creez une organisation pour lui donner acces a la plateforme."
                            action={
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus className="size-4" />
                                        Creer une organisation
                                    </Link>
                                </Button>
                            }
                        />
                    }
                />
            </div>
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [{ title: 'Organisations', href: index() }],
};
