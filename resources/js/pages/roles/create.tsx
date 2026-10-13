import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { PageHeader } from '@/components/page-header';
import { RoleForm, type CatalogueEntry } from '@/pages/roles/role-form';
import { create, index } from '@/routes/roles';

type Props = { catalogue: CatalogueEntry[] };

export default function RoleCreate({ catalogue }: Props) {
    return (
        <>
            <Head title="Creer un role" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Creer un role"
                    description="Nommez le role puis cochez les permissions qu il accorde"
                />

                <RoleForm
                    action={RoleController.store.form()}
                    catalogue={catalogue}
                    submitLabel="Creer le role"
                />
            </div>
        </>
    );
}

RoleCreate.layout = {
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'Creer', href: create() },
    ],
};
