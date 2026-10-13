import { Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/page-header';
import {
    UserForm,
    type RoleOption,
    type UserFormValues,
} from '@/pages/users/user-form';
import { edit, index } from '@/routes/users';

type Props = {
    user: UserFormValues & { id: number };
    roles: RoleOption[];
};

export default function UserEdit({ user, roles }: Props) {
    return (
        <>
            <Head title={`Modifier ${user.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Modifier l utilisateur"
                    description={user.email}
                />

                <UserForm
                    action={UserController.update.form(user.id)}
                    user={user}
                    roles={roles}
                    submitLabel="Enregistrer les modifications"
                />
            </div>
        </>
    );
}

UserEdit.layout = ({ user }: Props) => ({
    breadcrumbs: [
        { title: 'Utilisateurs', href: index() },
        { title: 'Modifier', href: edit(user.id) },
    ],
});
