import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/users';

export type RoleOption = { id: number; name: string; is_system: boolean };

export type UserFormValues = {
    id?: number;
    name: string;
    email: string;
    role_id: number;
    is_self?: boolean;
};

type Props = {
    action: Record<string, unknown>;
    user?: UserFormValues;
    roles: RoleOption[];
    submitLabel: string;
};

export function UserForm({ action, user, roles, submitLabel }: Props) {
    const isEditing = user !== undefined;
    const isSelf = user?.is_self === true;

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="max-w-[640px] space-y-8"
        >
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors: Record<string, string>;
            }) => (
                <>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Compte
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Nom complet
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                defaultValue={user?.name}
                                className="h-12"
                                aria-invalid={errors.name ? true : undefined}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">
                                Adresse e-mail
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                defaultValue={user?.email}
                                className="h-12"
                                aria-invalid={errors.email ? true : undefined}
                            />
                            <p className="text-text-muted text-xs">
                                Cette adresse sert d identifiant de connexion.
                            </p>
                            <InputError message={errors.email} />
                        </div>
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            {isEditing
                                ? 'Changer le mot de passe'
                                : 'Mot de passe'}
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                Mot de passe
                                {!isEditing && (
                                    <span className="text-danger ml-0.5">
                                        *
                                    </span>
                                )}
                            </Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="new-password"
                                required={!isEditing}
                                className="h-12"
                            />
                            {isEditing && (
                                <p className="text-text-muted text-xs">
                                    Laissez vide pour conserver le mot de passe
                                    actuel.
                                </p>
                            )}
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmer le mot de passe
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                required={!isEditing}
                                className="h-12"
                            />
                        </div>
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Role
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="role_id">
                                Role attribue
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            {isSelf && (
                                <input
                                    type="hidden"
                                    name="role_id"
                                    value={user?.role_id}
                                />
                            )}
                            <select
                                id="role_id"
                                name={isSelf ? undefined : 'role_id'}
                                required={!isSelf}
                                disabled={isSelf}
                                defaultValue={user?.role_id ?? ''}
                                className="border-border-strong bg-surface text-text h-12 rounded-md border px-3 text-sm disabled:opacity-45"
                            >
                                <option value="" disabled>
                                    Choisir un role
                                </option>
                                {roles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {role.name}
                                        {role.is_system ? ' (systeme)' : ''}
                                    </option>
                                ))}
                            </select>
                            {isSelf && (
                                <p className="text-text-muted text-xs">
                                    Vous ne pouvez pas modifier votre propre
                                    role.
                                </p>
                            )}
                            <InputError message={errors.role_id} />
                        </div>
                    </section>
                    <div className="border-border flex items-center justify-end gap-3 border-t pt-6">
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Annuler</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="h-12 w-full sm:w-auto"
                        >
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
