import { Head } from '@inertiajs/react';
import PlanController from '@/actions/App/Http/Controllers/Admin/PlanController';
import { PageHeader } from '@/components/page-header';
import { PlanForm } from '@/pages/admin/plans/plan-form';
import type { PlanRow } from '@/pages/admin/plans/index';
import { edit, index } from '@/routes/admin/plans';

type Props = { plan: PlanRow & { slug: string } };

export default function PlanEdit({ plan }: Props) {
    return (
        <>
            <Head title={`Modifier ${plan.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Modifier le plan"
                    description={`${plan.name} — ${plan.active_subscriptions_count} abonnement(s) actif(s)`}
                />

                <PlanForm
                    action={PlanController.update.form(plan.id)}
                    plan={plan}
                    submitLabel="Enregistrer les modifications"
                />
            </div>
        </>
    );
}

PlanEdit.layout = ({ plan }: Props) => ({
    breadcrumbs: [
        { title: 'Plans', href: index() },
        { title: plan.name, href: edit(plan.id) },
    ],
});
