import { Form, Head, Link } from '@inertiajs/react';
import { CreditCard, Plus, Receipt } from 'lucide-react';
import { useState } from 'react';
import SubscriptionController from '@/actions/App/Http/Controllers/Admin/SubscriptionController';
import { DataTable, type Column } from '@/components/data-table/data-table';
import { navigateWithParams } from '@/components/data-table/pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import {
    StatusBadge,
    subscriptionStatuses,
    type SubscriptionStatus,
} from '@/components/status-badge';
import { Toolbar } from '@/components/toolbar';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/admin/subscriptions';
import type { Paginated } from '@/types';

type SubscriptionRow = {
    id: number;
    organization: string | null;
    organization_id: number;
    plan: string | null;
    billing_cycle: 'monthly' | 'yearly';
    status: SubscriptionStatus;
    price: string;
    starts_at: string | null;
    ends_at: string | null;
    paid_count: number;
};

type PaymentRow = {
    id: number;
    organization: string | null;
    amount: string;
    currency: string;
    status: string;
    provider: string;
    provider_reference: string | null;
    paid_at: string | null;
};

type Props = {
    subscriptions: Paginated<SubscriptionRow>;
    filters: { status: SubscriptionStatus | null; per_page: number };
    payments?: PaymentRow[];
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

export default function SubscriptionsIndex({
    subscriptions,
    filters,
    payments,
}: Props) {
    const [selected, setSelected] = useState<(number | string)[]>([]);

    const columns: Column<SubscriptionRow>[] = [
        {
            key: 'organization',
            header: 'Organisation',
            cell: (row) => (
                <span className="text-text font-medium">
                    {row.organization ?? '—'}
                </span>
            ),
        },
        { key: 'plan', header: 'Plan', cell: (row) => row.plan ?? '—' },
        {
            key: 'billing_cycle',
            header: 'Cycle',
            cell: (row) =>
                row.billing_cycle === 'yearly' ? 'Annuel' : 'Mensuel',
        },
        {
            key: 'price',
            header: 'Montant',
            className: 'tabular',
            cell: (row) => `${row.price} USD`,
        },
        {
            key: 'period',
            header: 'Periode',
            secondary: true,
            className: 'tabular whitespace-nowrap',
            cell: (row) =>
                `${formatDate(row.starts_at)} → ${formatDate(row.ends_at)}`,
        },
        {
            key: 'status',
            header: 'Statut',
            cell: (row) => (
                <StatusBadge
                    tone={subscriptionStatuses[row.status].tone}
                    label={subscriptionStatuses[row.status].label}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Abonnements" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Abonnements"
                    description={`${subscriptions.total} abonnements enregistres`}
                    action={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Attribuer un abonnement
                            </Link>
                        </Button>
                    }
                />
                <Toolbar
                    searchValue={null}
                    searchLabel="Rechercher"
                    searchPlaceholder="Rechercher"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statut',
                            value: filters.status,
                            options: (
                                Object.keys(
                                    subscriptionStatuses,
                                ) as SubscriptionStatus[]
                            ).map((status) => ({
                                value: status,
                                label: subscriptionStatuses[status].label,
                            })),
                        },
                    ]}
                />
                <DataTable
                    rows={subscriptions}
                    columns={columns}
                    rowKey={(row) => row.id}
                    sort="starts_at"
                    direction="desc"
                    onNavigate={navigateWithParams}
                    selected={selected}
                    onSelectedChange={setSelected}
                    actions={(row) =>
                        row.status === 'canceled' ? null : (
                            <Form
                                {...SubscriptionController.cancel.form(row.id)}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        className="text-danger h-8"
                                        disabled={processing}
                                    >
                                        Annuler
                                    </Button>
                                )}
                            </Form>
                        )
                    }
                    empty={
                        <EmptyState
                            icon={CreditCard}
                            title="Aucun abonnement"
                            description="Attribuez un plan a une organisation pour demarrer un abonnement."
                            action={
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus className="size-4" />
                                        Attribuer un abonnement
                                    </Link>
                                </Button>
                            }
                        />
                    }
                />
                <section className="border-border bg-surface overflow-hidden rounded-lg border">
                    <header className="border-border border-b px-5 py-4">
                        <h2 className="text-text text-base font-semibold">
                            Historique des paiements
                        </h2>
                        <p className="text-text-secondary text-sm">
                            Les 25 derniers paiements enregistres
                        </p>
                    </header>
                    {!payments || payments.length === 0 ? (
                        <EmptyState
                            icon={Receipt}
                            title="Aucun paiement enregistre"
                            description="Les paiements apparaitront ici des qu un abonnement aura ete regle en ligne."
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {payments.map((payment) => (
                                <li
                                    key={payment.id}
                                    className="flex items-center gap-3 px-5 py-3"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="text-text block truncate text-sm font-medium">
                                            {payment.organization ?? '—'}
                                        </span>
                                        <span className="text-text-muted block truncate text-xs">
                                            {payment.provider}
                                            {payment.provider_reference
                                                ? ` · ${payment.provider_reference}`
                                                : ''}
                                        </span>
                                    </span>
                                    <span className="text-text tabular text-sm">
                                        {payment.amount} {payment.currency}
                                    </span>
                                    <StatusBadge
                                        tone={
                                            payment.status === 'paid'
                                                ? 'success'
                                                : payment.status === 'failed'
                                                  ? 'danger'
                                                  : 'warning'
                                        }
                                        label={payment.status}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

SubscriptionsIndex.layout = {
    breadcrumbs: [{ title: 'Abonnements', href: index() }],
};
