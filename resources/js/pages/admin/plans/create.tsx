import { Head } from '@inertiajs/react';
import PlanController from '@/actions/App/Http/Controllers/Admin/PlanController';
import { PageHeader } from '@/components/page-header';
import { PlanForm } from '@/pages/admin/plans/plan-form';
import { create, index } from '@/routes/admin/plans';

export default function PlanCreate() {
    return (
        <>
            <Head title="Creer un plan" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Creer un plan"
                    description="Tarifs, limites par ressource et identifiants Paddle"
                />

                <PlanForm
                    action={PlanController.store.form()}
                    submitLabel="Creer le plan"
                />
            </div>
        </>
    );
}

PlanCreate.layout = {
    breadcrumbs: [
        { title: 'Plans', href: index() },
        { title: 'Creer', href: create() },
    ],
};
