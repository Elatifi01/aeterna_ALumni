import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { PageHeader } from '@/components/page-header';
import {
    RoleForm,
    type CatalogueEntry,
    type RoleFormValues,
} from '@/pages/roles/role-form';
import { edit, index } from '@/routes/admin/roles';

type Props = {
    role: RoleFormValues & { id: number; guard: 'web' | 'alumni' | 'platform' };
    catalogue: CatalogueEntry[];
};

export default function AdminRoleEdit({ role, catalogue }: Props) {
    return (
        <>
            <Head title={`Modifier ${role.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Modifier le role de la plateforme"
                    description={role.name}
                />

                <RoleForm
                    action={RoleController.update.form(role.id)}
                    role={role}
                    catalogue={catalogue}
                    cancelHref={index()}
                    notice={
                        role.guard === 'platform'
                            ? 'Ces acces decident des liens de la barre laterale de l espace plateforme, et des pages qu ils ouvrent. Le tableau de bord et les roles restent toujours accessibles.'
                            : 'Ces permissions s appliquent aux organisations creees ensuite. Chaque organisation existante garde sa propre copie, que son administrateur gere.'
                    }
                    submitLabel="Enregistrer les modifications"
                />
            </div>
        </>
    );
}

AdminRoleEdit.layout = ({ role }: Props) => ({
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'Modifier', href: edit(role.id) },
    ],
});
