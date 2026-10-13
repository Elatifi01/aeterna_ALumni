import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { BulkBar } from '@/components/data-table/bulk-bar';
import { DataTable, type Column } from '@/components/data-table/data-table';
import { navigateWithParams } from '@/components/data-table/pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import {
    eventStatuses,
    StatusBadge,
    type EventStatus,
} from '@/components/status-badge';
import { Toolbar } from '@/components/toolbar';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/lib/permissions';
import { create, edit, index, show } from '@/routes/events';
import type { Paginated, PlanUsage, SortDirection } from '@/types';

export type EventRow = {
    id: number;
    title: string;
    location: string | null;
    starts_at: string | null;
    ends_at: string | null;
    capacity: number | null;
    status: EventStatus;
    registrations_count: number;
    attendees_count: number;
    attendance_rate: number | null;
};

type Props = {
    events: Paginated<EventRow>;
    filters: {
        search: string | null;
        status: EventStatus | null;
        sort: string;
        direction: SortDirection;
        per_page: number;
    };
    usage: PlanUsage;
};

export function formatEventDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function EventsIndex({ events, filters, usage }: Props) {
    const { can } = usePermissions();
    const [selected, setSelected] = useState<(number | string)[]>([]);
    const [pendingDelete, setPendingDelete] = useState<EventRow | null>(null);
    const [pendingBulkDelete, setPendingBulkDelete] = useState<
        (number | string)[] | null
    >(null);

    const atLimit = usage.limit !== null && usage.remaining === 0;

    const columns: Column<EventRow>[] = [
        {
            key: 'title',
            header: 'Titre',
            sortable: true,
            cell: (row) => (
                <Link
                    href={show(row.id)}
                    className="text-text hover:text-accent font-medium"
                >
                    {row.title}
                </Link>
            ),
        },
        {
            key: 'starts_at',
            header: 'Date',
            sortable: true,
            className: 'tabular whitespace-nowrap',
            cell: (row) => formatEventDate(row.starts_at),
        },
        {
            key: 'location',
            header: 'Lieu',
            sortable: true,
            secondary: true,
            cell: (row) => row.location ?? '—',
        },
        {
            key: 'registrations',
            header: 'Inscrits',
            className: 'tabular',
            cell: (row) =>
                row.capacity === null
                    ? row.registrations_count
                    : `${row.registrations_count}/${row.capacity}`,
        },
        {
            key: 'attendance',
            header: 'Participation',
            className: 'tabular',
            cell: (row) =>
                row.attendance_rate === null
                    ? '—'
                    : `${row.attendance_rate.toLocaleString('fr-FR')} %`,
        },
        {
            key: 'status',
            header: 'Statut',
            sortable: true,
            cell: (row) => (
                <StatusBadge
                    tone={eventStatuses[row.status].tone}
                    label={eventStatuses[row.status].label}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Evenements" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Evenements"
                    description={
                        usage.limit === null
                            ? `${events.total} evenements organises`
                            : `${usage.used} / ${usage.limit} evenements utilises sur votre plan`
                    }
                    action={
                        can('events.create') && (
                            <Button asChild disabled={atLimit}>
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Creer un evenement
                                </Link>
                            </Button>
                        )
                    }
                />
                <Toolbar
                    searchValue={filters.search}
                    searchLabel="Rechercher un evenement"
                    searchPlaceholder="Rechercher un titre ou un lieu"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statut',
                            value: filters.status,
                            options: (
                                Object.keys(eventStatuses) as EventStatus[]
                            ).map((status) => ({
                                value: status,
                                label: eventStatuses[status].label,
                            })),
                        },
                    ]}
                />
                <DataTable
                    rows={events}
                    columns={columns}
                    rowKey={(row) => row.id}
                    sort={filters.sort}
                    direction={filters.direction}
                    onNavigate={navigateWithParams}
                    selected={selected}
                    onSelectedChange={setSelected}
                    actions={(row) => (
                        <RowActions
                            label={row.title}
                            show={show(row.id)}
                            edit={
                                can('events.update') ? edit(row.id) : undefined
                            }
                            onDelete={
                                can('events.delete')
                                    ? () => setPendingDelete(row)
                                    : undefined
                            }
                        />
                    )}
                    bulkBar={(keys, clear) => (
                        <BulkBar count={keys.length} onClear={clear}>
                            {can('events.delete') && (
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
                            icon={CalendarDays}
                            title={
                                filters.search || filters.status
                                    ? 'Aucun evenement ne correspond a votre recherche'
                                    : 'Aucun evenement pour le moment'
                            }
                            description={
                                filters.search || filters.status
                                    ? 'Modifiez ou reinitialisez vos filtres pour elargir la recherche.'
                                    : 'Creez un evenement pour commencer a suivre les inscriptions et la presence.'
                            }
                            action={
                                can('events.create') && (
                                    <Button asChild>
                                        <Link href={create()}>
                                            <Plus className="size-4" />
                                            Creer un evenement
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
                        ? EventController.destroySelection.form()
                        : null
                }
                fields={{ ids: pendingBulkDelete ?? [] }}
                onSuccess={() => setSelected([])}
                title={`Supprimer ${pendingBulkDelete?.length ?? 0} ${
                    (pendingBulkDelete?.length ?? 0) > 1
                        ? 'evenements'
                        : 'evenement'
                } ?`}
                description="La selection part a la corbeille et disparait de vos listes. Vous pourrez restaurer chaque ligne."
                confirmLabel="Supprimer la selection"
            />
            <ConfirmDeleteDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
                action={
                    pendingDelete
                        ? EventController.destroy.form(pendingDelete.id)
                        : null
                }
                title={`Supprimer ${pendingDelete?.title} ?`}
                description={
                    pendingDelete && pendingDelete.registrations_count > 0
                        ? `Cet evenement compte ${pendingDelete.registrations_count} inscription(s). Il part a la corbeille et pourra etre restaure.`
                        : 'Cet evenement part a la corbeille et pourra etre restaure.'
                }
            />
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Evenements', href: index() }],
};
