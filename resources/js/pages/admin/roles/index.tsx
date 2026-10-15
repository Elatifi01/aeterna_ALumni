import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/admin/roles';

type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    guard: 'web' | 'alumni' | 'platform';
    is_platform: boolean;
    is_default: boolean;
    permissions_count: number;
    organizations_count: number;
};

type Props = { roles: RoleRow[] };

function badge(role: RoleRow) {
    if (role.is_platform) {
        return <StatusBadge tone="warning" label="Espace plateforme" />;
    }

    if (role.guard === 'alumni') {
        return <StatusBadge tone="success" label="Portail alumni" />;
    }

    return role.is_default ? (
        <StatusBadge tone="info" label="Par defaut" />
    ) : null;
}

export default function AdminRolesIndex({ roles }: Props) {
    return (
        <>
            <Head title="Roles de la plateforme" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Roles de la plateforme"
                    description="Chaque nouvelle organisation recoit une copie de ces roles, que son administrateur ajuste ensuite de son cote"
                    action={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Creer un role
                            </Link>
                        </Button>
                    }
                />
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {roles.map((role) => (
                        <article
                            key={role.id}
                            className="border-border bg-surface flex flex-col rounded-lg border p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                                <div className="min-w-0">
                                    <h2 className="text-text truncate text-base font-semibold">
                                        {role.name}
                                    </h2>
                                    {role.description && (
                                        <p className="text-text-secondary mt-1 text-sm">
                                            {role.description}
                                        </p>
                                    )}
                                </div>
                                {badge(role)}
                            </div>
                            <dl className="text-text-secondary mt-4 flex gap-6 text-sm">
                                <div>
                                    <dt className="text-text-muted text-xs">
                                        Permissions
                                    </dt>
                                    <dd className="tabular text-text font-medium">
                                        {role.permissions_count}
                                    </dd>
                                </div>
                                {!role.is_platform && (
                                    <div>
                                        <dt className="text-text-muted text-xs">
                                            Organisations
                                        </dt>
                                        <dd className="tabular text-text font-medium">
                                            {role.organizations_count}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                            <div className="mt-5 flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    className="h-9"
                                    asChild
                                >
                                    <Link href={edit(role.id)}>
                                        <Pencil className="size-3.5" />
                                        Modifier
                                    </Link>
                                </Button>
                                {!role.is_default && !role.is_platform && (
                                    <Form
                                        {...RoleController.destroy.form(
                                            role.id,
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
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            </div>
        </>
    );
}

AdminRolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
