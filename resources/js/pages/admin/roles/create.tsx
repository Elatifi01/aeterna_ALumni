import { Head } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { PageHeader } from '@/components/page-header';
import { RoleForm, type CatalogueEntry } from '@/pages/roles/role-form';
import { create, index } from '@/routes/admin/roles';

type Props = { catalogue: CatalogueEntry[] };

export default function AdminRoleCreate({ catalogue }: Props) {
    return (
        <>
            <Head title="Creer un role de la plateforme" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Creer un role de la plateforme"
                    description="Nommez le role puis cochez les permissions qu il accorde"
                />

                <RoleForm
                    action={RoleController.store.form()}
                    catalogue={catalogue}
                    cancelHref={index()}
                    notice="Les organisations creees a partir de maintenant recevront une copie de ce role. Les organisations existantes ne sont pas modifiees."
                    submitLabel="Creer le role"
                />
            </div>
        </>
    );
}

AdminRoleCreate.layout = {
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'Creer', href: create() },
    ],
};
