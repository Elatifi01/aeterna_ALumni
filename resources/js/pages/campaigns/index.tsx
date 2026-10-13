import { Head, Link } from '@inertiajs/react';
import { Mail, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { BulkBar } from '@/components/data-table/bulk-bar';
import { DataTable, type Column } from '@/components/data-table/data-table';
import { navigateWithParams } from '@/components/data-table/pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import {
    campaignStatuses,
    StatusBadge,
    type CampaignStatus,
} from '@/components/status-badge';
import { Toolbar } from '@/components/toolbar';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/lib/permissions';
import { create, edit, index, show } from '@/routes/campaigns';
import type { Paginated, PlanUsage, SortDirection } from '@/types';

export type CampaignRow = {
    id: number;
    subject: string;
    status: CampaignStatus;
    recipients_count: number;
    sent_count: number;
    failed_count: number;
    sent_at: string | null;
    created_at: string | null;
};

type Props = {
    campaigns: Paginated<CampaignRow>;
    filters: {
        search: string | null;
        status: CampaignStatus | null;
        sort: string;
        direction: SortDirection;
        per_page: number;
    };
    usage: PlanUsage;
};

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function CampaignsIndex({ campaigns, filters, usage }: Props) {
    const { can } = usePermissions();
    const [selected, setSelected] = useState<(number | string)[]>([]);
    const [pendingDelete, setPendingDelete] = useState<CampaignRow | null>(
        null,
    );
    const [pendingBulkDelete, setPendingBulkDelete] = useState<
        (number | string)[] | null
    >(null);

    const atLimit = usage.limit !== null && usage.remaining === 0;

    const columns: Column<CampaignRow>[] = [
        {
            key: 'subject',
            header: 'Objet',
            sortable: true,
            cell: (row) => (
                <Link
                    href={show(row.id)}
                    className="text-text hover:text-accent font-medium"
                >
                    {row.subject}
                </Link>
            ),
        },
        {
            key: 'recipients_count',
            header: 'Destinataires',
            sortable: true,
            className: 'tabular',
            cell: (row) => row.recipients_count,
        },
        {
            key: 'sent',
            header: 'Envoyees',
            className: 'tabular',
            cell: (row) => (
                <span>
                    {row.sent_count}
                    {row.failed_count > 0 && (
                        <span className="text-danger ml-2 text-xs">
                            {row.failed_count} echec
                            {row.failed_count > 1 ? 's' : ''}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            sortable: true,
            cell: (row) => (
                <StatusBadge
                    tone={campaignStatuses[row.status].tone}
                    label={campaignStatuses[row.status].label}
                />
            ),
        },
        {
            key: 'sent_at',
            header: 'Date d envoi',
            sortable: true,
            secondary: true,
            className: 'tabular whitespace-nowrap',
            cell: (row) => formatDate(row.sent_at),
        },
    ];

    return (
        <>
            <Head title="Campagnes" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Campagnes"
                    description={
                        usage.limit === null
                            ? `${campaigns.total} campagnes creees`
                            : `${usage.used} / ${usage.limit} campagnes utilisees sur votre plan`
                    }
                    action={
                        can('campaigns.create') && (
                            <Button asChild disabled={atLimit}>
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Creer une campagne
                                </Link>
                            </Button>
                        )
                    }
                />
                <Toolbar
                    searchValue={filters.search}
                    searchLabel="Rechercher une campagne"
                    searchPlaceholder="Rechercher un objet"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statut',
                            value: filters.status,
                            options: (
                                Object.keys(
                                    campaignStatuses,
                                ) as CampaignStatus[]
                            ).map((status) => ({
                                value: status,
                                label: campaignStatuses[status].label,
                            })),
                        },
                    ]}
                />
                <DataTable
                    rows={campaigns}
                    columns={columns}
                    rowKey={(row) => row.id}
                    sort={filters.sort}
                    direction={filters.direction}
                    onNavigate={navigateWithParams}
                    selected={selected}
                    onSelectedChange={setSelected}
                    actions={(row) => (
                        <RowActions
                            label={row.subject}
                            show={show(row.id)}
                            edit={
                                row.status === 'draft' &&
                                can('campaigns.create')
                                    ? edit(row.id)
                                    : undefined
                            }
                            onDelete={
                                can('campaigns.create')
                                    ? () => setPendingDelete(row)
                                    : undefined
                            }
                        />
                    )}
                    bulkBar={(keys, clear) => (
                        <BulkBar count={keys.length} onClear={clear}>
                            {can('campaigns.create') && (
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
                            icon={Mail}
                            title={
                                filters.search || filters.status
                                    ? 'Aucune campagne ne correspond a votre recherche'
                                    : 'Aucune campagne pour le moment'
                            }
                            description={
                                filters.search || filters.status
                                    ? 'Modifiez ou reinitialisez vos filtres pour elargir la recherche.'
                                    : 'Preparez une campagne pour communiquer avec vos alumni par e-mail.'
                            }
                            action={
                                can('campaigns.create') && (
                                    <Button asChild>
                                        <Link href={create()}>
                                            <Plus className="size-4" />
                                            Creer une campagne
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
                        ? CampaignController.destroySelection.form()
                        : null
                }
                fields={{ ids: pendingBulkDelete ?? [] }}
                onSuccess={() => setSelected([])}
                title={`Supprimer ${pendingBulkDelete?.length ?? 0} ${
                    (pendingBulkDelete?.length ?? 0) > 1
                        ? 'campagnes'
                        : 'campagne'
                } ?`}
                description="La selection part a la corbeille et disparait de vos listes. Vous pourrez restaurer chaque ligne."
                confirmLabel="Supprimer la selection"
            />
            <ConfirmDeleteDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
                action={
                    pendingDelete
                        ? CampaignController.destroy.form(pendingDelete.id)
                        : null
                }
                title={`Supprimer ${pendingDelete?.subject} ?`}
                description="Ce brouillon part a la corbeille et pourra etre restaure."
            />
        </>
    );
}

CampaignsIndex.layout = {
    breadcrumbs: [{ title: 'Campagnes', href: index() }],
};
