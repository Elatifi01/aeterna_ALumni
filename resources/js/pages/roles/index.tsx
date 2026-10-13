import { Form, Head, Link } from '@inertiajs/react';
import { Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/lib/permissions';
import { create, edit, index } from '@/routes/roles';

type RoleRow = {
    id: number;
    name: string;
    description: string | null;
    is_system: boolean;
    guard: 'web' | 'alumni';
    users_count: number;
    permissions_count: number;
};

type Props = { roles: RoleRow[] };

export default function RolesIndex({ roles }: Props) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Roles et permissions" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Roles et permissions"
                    description="Un role regroupe les permissions accordees a un utilisateur"
                    action={
                        can('roles.manage') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus className="size-4" />
                                    Creer un role
                                </Link>
                            </Button>
                        )
                    }
                />
                {roles.length === 0 ? (
                    <div className="border-border bg-surface rounded-lg border">
                        <EmptyState
                            icon={ShieldCheck}
                            title="Aucun role defini"
                            description="Creez un role pour accorder des permissions precises a vos utilisateurs."
                        />
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {roles.map((role) => (
                            <article
                                key={role.id}
                                className="border-border bg-surface flex flex-col rounded-lg border p-5"
                            >
                                <div className="flex items-start justify-between gap-3">
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
                                    {role.is_system ? (
                                        <StatusBadge
                                            tone="info"
                                            label="Systeme"
                                        />
                                    ) : (
                                        role.guard === 'alumni' && (
                                            <StatusBadge
                                                tone="success"
                                                label="Portail alumni"
                                            />
                                        )
                                    )}
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
                                    <div>
                                        <dt className="text-text-muted text-xs">
                                            {role.guard === 'alumni'
                                                ? 'Comptes alumni'
                                                : 'Utilisateurs'}
                                        </dt>
                                        <dd className="tabular text-text font-medium">
                                            {role.users_count}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="mt-5 flex items-center gap-2">
                                    {role.is_system ? (
                                        <p className="text-text-muted flex items-center gap-1.5 text-xs">
                                            <Lock className="size-3.5" />
                                            Role de la plateforme, non
                                            modifiable
                                        </p>
                                    ) : (
                                        can('roles.manage') && (
                                            <>
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
                                                {role.guard !== 'alumni' && (
                                                    <Form
                                                        {...RoleController.destroy.form(
                                                            role.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                variant="ghost"
                                                                className="text-danger h-9"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                                Supprimer
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                            </>
                                        )
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
