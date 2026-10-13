import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { PageHeader } from '@/components/page-header';
import {
    RoleForm,
    type CatalogueEntry,
    type RoleFormValues,
} from '@/pages/roles/role-form';
import { edit, index } from '@/routes/roles';

type Props = {
    role: RoleFormValues & { id: number; guard: 'web' | 'alumni' };
    catalogue: CatalogueEntry[];
};

export default function RoleEdit({ role, catalogue }: Props) {
    return (
        <>
            <Head title={`Modifier ${role.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader title="Modifier le role" description={role.name} />

                <RoleForm
                    forAlumni={role.guard === 'alumni'}
                    action={RoleController.update.form(role.id)}
                    role={role}
                    catalogue={catalogue}
                    submitLabel="Enregistrer les modifications"
                />
            </div>
        </>
    );
}

RoleEdit.layout = ({ role }: Props) => ({
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'Modifier', href: edit(role.id) },
    ],
});
