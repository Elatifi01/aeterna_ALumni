import { Form, Head, Link } from '@inertiajs/react';
import { Layers, Pencil, Plus, Trash2 } from 'lucide-react';
import PlanController from '@/actions/App/Http/Controllers/Admin/PlanController';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/admin/plans';

export type PlanRow = {
    id: number;
    name: string;
    description: string | null;
    price_monthly: string;
    price_yearly: string;
    max_alumni: number | null;
    max_events: number | null;
    max_campaigns: number | null;
    max_users: number | null;
    paddle_price_monthly_id: string | null;
    paddle_price_yearly_id: string | null;
    is_active: boolean;
    is_custom: boolean;
    active_subscriptions_count: number;
};

type Props = { plans: PlanRow[]; customPlansCount: number };

function limit(value: number | null): string {
    return value === null ? 'Illimite' : value.toLocaleString('fr-FR');
}

export default function PlansIndex({ plans, customPlansCount }: Props) {
    return (
        <>
            <Head title="Plans" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Plans"
                    description={
                        customPlansCount > 0
                            ? `Tarifs, limites et identifiants Paddle — ${customPlansCount} abonnement(s) sur mesure geres depuis leur organisation`
                            : 'Tarifs, limites et identifiants de prix Paddle'
                    }
                    action={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Creer un plan
                            </Link>
                        </Button>
                    }
                />
                {plans.length === 0 ? (
                    <div className="border-border bg-surface rounded-lg border">
                        <EmptyState
                            icon={Layers}
                            title="Aucun plan defini"
                            description="Creez un plan pour pouvoir attribuer des abonnements."
                        />
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        {plans.map((plan) => (
                            <article
                                key={plan.id}
                                className="border-border bg-surface flex flex-col rounded-lg border p-5"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h2 className="text-text truncate text-base font-semibold">
                                            {plan.name}
                                        </h2>
                                        {plan.description && (
                                            <p className="text-text-secondary mt-1 text-sm">
                                                {plan.description}
                                            </p>
                                        )}
                                    </div>
                                    {plan.is_active ? (
                                        <StatusBadge
                                            tone="success"
                                            label="Actif"
                                        />
                                    ) : (
                                        <StatusBadge
                                            tone="neutral"
                                            label="Inactif"
                                        />
                                    )}
                                </div>
                                <p className="text-text tabular mt-4 text-2xl font-semibold">
                                    {plan.price_monthly}
                                    <span className="text-text-muted ml-1 text-sm font-normal">
                                        USD / mois
                                    </span>
                                </p>
                                <p className="text-text-muted tabular text-xs">
                                    {plan.price_yearly} USD / an
                                </p>
                                <dl className="border-border mt-4 grid grid-cols-2 gap-x-4 gap-y-2 border-t pt-4 text-sm">
                                    {[
                                        ['Alumni', plan.max_alumni],
                                        ['Evenements', plan.max_events],
                                        ['Campagnes', plan.max_campaigns],
                                        ['Utilisateurs', plan.max_users],
                                    ].map(([label, value]) => (
                                        <div key={label as string}>
                                            <dt className="text-text-muted text-xs">
                                                {label}
                                            </dt>
                                            <dd className="text-text tabular font-medium">
                                                {limit(value as number | null)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <p className="text-text-muted mt-4 text-xs">
                                    {plan.paddle_price_monthly_id ||
                                    plan.paddle_price_yearly_id
                                        ? 'Prix Paddle configures'
                                        : 'Aucun prix Paddle — achat en ligne indisponible'}
                                </p>
                                <p className="text-text-muted tabular mt-1 text-xs">
                                    {plan.active_subscriptions_count} abonnement
                                    {plan.active_subscriptions_count > 1
                                        ? 's'
                                        : ''}{' '}
                                    actif
                                    {plan.active_subscriptions_count > 1
                                        ? 's'
                                        : ''}
                                </p>
                                <div className="mt-5 flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        className="h-9"
                                        asChild
                                    >
                                        <Link href={edit(plan.id)}>
                                            <Pencil className="size-3.5" />
                                            Modifier
                                        </Link>
                                    </Button>
                                    <Form
                                        {...PlanController.destroy.form(
                                            plan.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                className="text-danger h-9"
                                                disabled={processing}
                                            >
                                                <Trash2 className="size-3.5" />
                                                Supprimer
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

PlansIndex.layout = {
    breadcrumbs: [{ title: 'Plans', href: index() }],
};
