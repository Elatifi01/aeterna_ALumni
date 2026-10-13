import { Head, Link } from '@inertiajs/react';
import { Download, GraduationCap, Plus, Upload, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AlumniController from '@/actions/App/Http/Controllers/AlumniController';
import { AvatarInitials } from '@/components/avatar-initials';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { BulkBar } from '@/components/data-table/bulk-bar';
import { DataTable, type Column } from '@/components/data-table/data-table';
import { navigateWithParams } from '@/components/data-table/pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { Toolbar } from '@/components/toolbar';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/lib/permissions';
import { create, edit, show } from '@/routes/alumni';
import { exportMethod as exportAlumni, index } from '@/routes/alumni';
import { index as importIndex } from '@/routes/alumni/import';
import type { Paginated, PlanUsage, SortDirection } from '@/types';

type AlumniRow = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    degree: string | null;
    graduation_year: number | null;
    company: string | null;
    job_title: string | null;
    city: string | null;
};

type Filters = {
    search: string | null;
    graduation_year: number | null;
    degree: string | null;
    company: string | null;
    sort: string;
    direction: SortDirection;
    per_page: number;
};

type FilterOptions = {
    graduationYears: number[];
    degrees: string[];
    companies: string[];
};

type Props = {
    alumni: Paginated<AlumniRow>;
    filters: Filters;
    filterOptions?: FilterOptions;
    usage: PlanUsage;
};

export default function AlumniIndex({
    alumni,
    filters,
    filterOptions,
    usage,
}: Props) {
    const { can } = usePermissions();
    const [selected, setSelected] = useState<(number | string)[]>([]);
    const [pendingDelete, setPendingDelete] = useState<AlumniRow | null>(null);
    const [pendingBulkDelete, setPendingBulkDelete] = useState<
        (number | string)[] | null
    >(null);

    const atLimit = usage.limit !== null && usage.remaining === 0;

    const columns: Column<AlumniRow>[] = [
        {
            key: 'last_name',
            header: 'Nom',
            sortable: true,
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <AvatarInitials
                        firstName={row.first_name}
                        lastName={row.last_name}
                    />
                    <span className="min-w-0">
                        <Link
                            href={show(row.id)}
                            className="text-text hover:text-accent block truncate font-medium"
                        >
                            {row.first_name} {row.last_name}
                        </Link>
                        <span className="text-text-muted block truncate text-xs">
                            {row.email}
                        </span>
                    </span>
                </div>
            ),
        },
        {
            key: 'degree',
            header: 'Diplome',
            sortable: true,
            cell: (row) => row.degree ?? '—',
        },
        {
            key: 'graduation_year',
            header: 'Promotion',
            sortable: true,
            className: 'tabular',
            cell: (row) => row.graduation_year ?? '—',
        },
        {
            key: 'company',
            header: 'Entreprise',
            sortable: true,
            cell: (row) => row.company ?? '—',
        },
        {
            key: 'job_title',
            header: 'Poste',
            secondary: true,
            cell: (row) => row.job_title ?? '—',
        },
        {
            key: 'city',
            header: 'Ville',
            secondary: true,
            cell: (row) => row.city ?? '—',
        },
    ];

    const hasFilters = Boolean(
        filters.search ||
        filters.graduation_year ||
        filters.degree ||
        filters.company,
    );

    return (
        <>
            <Head title="Alumni" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Alumni"
                    description={
                        usage.limit === null
                            ? `${alumni.total} alumni enregistres`
                            : `${usage.used} / ${usage.limit} alumni utilises sur votre plan`
                    }
                    action={
                        <div className="flex items-center gap-2">
                            {can('alumni.export') && (
                                <Button asChild variant="outline">
                                    <a
                                        href={
                                            exportAlumni({
                                                query: {
                                                    search:
                                                        filters.search ??
                                                        undefined,
                                                    graduation_year:
                                                        filters.graduation_year ??
                                                        undefined,
                                                    degree:
                                                        filters.degree ??
                                                        undefined,
                                                    company:
                                                        filters.company ??
                                                        undefined,
                                                },
                                            }).url
                                        }
                                    >
                                        <Download className="size-4" />
                                        Exporter
                                    </a>
                                </Button>
                            )}
                            {can('alumni.import') && (
                                <Button asChild variant="outline">
                                    <Link href={importIndex()}>
                                        <Upload className="size-4" />
                                        Importer
                                    </Link>
                                </Button>
                            )}
                            {can('alumni.create') && (
                                <Button asChild disabled={atLimit}>
                                    <Link href={create()}>
                                        <Plus className="size-4" />
                                        Ajouter un alumni
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />
                <Toolbar
                    searchValue={filters.search}
                    searchLabel="Rechercher un alumni"
                    searchPlaceholder="Rechercher un nom, un e-mail, une entreprise"
                    filters={[
                        {
                            name: 'graduation_year',
                            label: 'Promotion',
                            value: filters.graduation_year,
                            options: (filterOptions?.graduationYears ?? []).map(
                                (year) => ({
                                    value: year,
                                    label: String(year),
                                }),
                            ),
                        },
                        {
                            name: 'degree',
                            label: 'Diplome',
                            value: filters.degree,
                            options: (filterOptions?.degrees ?? []).map(
                                (degree) => ({ value: degree, label: degree }),
                            ),
                        },
                        {
                            name: 'company',
                            label: 'Entreprise',
                            value: filters.company,
                            options: (filterOptions?.companies ?? []).map(
                                (company) => ({
                                    value: company,
                                    label: company,
                                }),
                            ),
                        },
                    ]}
                />
                <DataTable
                    rows={alumni}
                    columns={columns}
                    rowKey={(row) => row.id}
                    sort={filters.sort}
                    direction={filters.direction}
                    onNavigate={navigateWithParams}
                    selected={selected}
                    onSelectedChange={setSelected}
                    actions={(row) => (
                        <RowActions
                            label={`${row.first_name} ${row.last_name}`}
                            show={show(row.id)}
                            edit={
                                can('alumni.update') ? edit(row.id) : undefined
                            }
                            onDelete={
                                can('alumni.delete')
                                    ? () => setPendingDelete(row)
                                    : undefined
                            }
                        />
                    )}
                    bulkBar={(keys, clear) => (
                        <BulkBar count={keys.length} onClear={clear}>
                            {can('alumni.delete') && (
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setPendingBulkDelete(keys)}
                                    data-test="delete-selection"
                                >
                                    <Trash2 className="size-4" />
                                    Supprimer la selection
                                </Button>
                            )}
                        </BulkBar>
                    )}
                    empty={
                        <EmptyState
                            icon={GraduationCap}
                            title={
                                hasFilters
                                    ? 'Aucun alumni ne correspond a votre recherche'
                                    : 'Aucun alumni pour le moment'
                            }
                            description={
                                hasFilters
                                    ? 'Modifiez ou reinitialisez vos filtres pour elargir la recherche.'
                                    : 'Les anciens etudiants de votre etablissement apparaitront ici une fois ajoutes.'
                            }
                            action={
                                !hasFilters &&
                                can('alumni.create') && (
                                    <Button asChild>
                                        <Link href={create()}>
                                            <Plus className="size-4" />
                                            Ajouter un alumni
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    }
                />
            </div>
            <ConfirmDeleteDialog
                open={pendingBulkDelete !== null}
                onOpenChange={(open) => !open && setPendingBulkDelete(null)}
                action={
                    pendingBulkDelete
                        ? AlumniController.destroySelection.form()
                        : null
                }
                fields={{ ids: pendingBulkDelete ?? [] }}
                onSuccess={() => setSelected([])}
                title={`Supprimer ${pendingBulkDelete?.length ?? 0} ${
                    (pendingBulkDelete?.length ?? 0) > 1 ? 'alumni' : 'alumni'
                } ?`}
                description="La selection part a la corbeille et disparait de vos listes. Vous pourrez restaurer chaque ligne."
                confirmLabel="Supprimer la selection"
            />
            <ConfirmDeleteDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
                action={
                    pendingDelete
                        ? AlumniController.destroy.form(pendingDelete.id)
                        : null
                }
                title={`Supprimer ${pendingDelete?.first_name} ${pendingDelete?.last_name} ?`}
                description="Cette fiche part a la corbeille et disparait de vos listes. Vous pourrez la restaurer."
            />
        </>
    );
}

AlumniIndex.layout = {
    breadcrumbs: [{ title: 'Alumni', href: index() }],
};
