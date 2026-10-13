import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/page-header';
import { UserForm, type RoleOption } from '@/pages/users/user-form';
import { create, index } from '@/routes/users';

type Props = { roles: RoleOption[] };

export default function UserCreate({ roles }: Props) {
    return (
        <>
            <Head title="Ajouter un utilisateur" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Ajouter un utilisateur"
                    description="Le compte est actif immediatement et son role determine ses droits"
                />

                <UserForm
                    action={UserController.store.form()}
                    roles={roles}
                    submitLabel="Creer le compte"
                />
            </div>
        </>
    );
}

UserCreate.layout = {
    breadcrumbs: [
        { title: 'Utilisateurs', href: index() },
        { title: 'Ajouter', href: create() },
    ],
};
