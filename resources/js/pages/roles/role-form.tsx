import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/roles';

export type CatalogueEntry = {
    module: string;
    permissions: { slug: string; label: string }[];
};

export type RoleFormValues = {
    id?: number;
    name: string;
    description: string | null;
    permissions: string[];
};

type Props = {
    action: Record<string, unknown>;
    role?: RoleFormValues;
    catalogue: CatalogueEntry[];
    submitLabel: string;
    forAlumni?: boolean;
};

const moduleLabels: Record<string, string> = {
    alumni: 'Alumni',
    events: 'Evenements',
    campaigns: 'Campagnes',
    users: 'Utilisateurs',
    roles: 'Roles',
    assistant: 'Aeterna Assistant',
    dashboard: 'Tableau de bord',
    organization: 'Organisation',
    subscription: 'Abonnement',
    portal: 'Portail alumni',
};

export function RoleForm({
    action,
    role,
    catalogue,
    submitLabel,
    forAlumni = false,
}: Props) {
    const [selected, setSelected] = useState<string[]>(role?.permissions ?? []);

    const toggle = (slug: string) => {
        setSelected((current) =>
            current.includes(slug)
                ? current.filter((value) => value !== slug)
                : [...current, slug],
        );
    };

    const toggleModule = (entry: CatalogueEntry) => {
        const slugs = entry.permissions.map((permission) => permission.slug);
        const allOn = slugs.every((slug) => selected.includes(slug));

        setSelected((current) =>
            allOn
                ? current.filter((slug) => !slugs.includes(slug))
                : [...new Set([...current, ...slugs])],
        );
    };

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="max-w-[720px] space-y-8"
        >
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors: Record<string, string>;
            }) => (
                <>
                    {selected.map((slug) => (
                        <input
                            key={slug}
                            type="hidden"
                            name="permissions[]"
                            value={slug}
                        />
                    ))}

                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Identification
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Nom du role
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                defaultValue={role?.name}
                                placeholder="Charge de communication"
                                className="h-12"
                                aria-invalid={errors.name ? true : undefined}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                name="description"
                                defaultValue={role?.description ?? ''}
                                placeholder="Ce que ce role permet de faire"
                                className="h-12"
                            />
                            <InputError message={errors.description} />
                        </div>
                    </section>
                    <section className="space-y-4">
                        {forAlumni && (
                            <p className="bg-accent-soft text-text-secondary rounded-lg px-4 py-3 text-sm">
                                Ce role est celui de tous les alumni de votre
                                etablissement. Ce que vous cochez ici est ce que
                                leur portail leur propose ; ce que vous decochez
                                en disparait pour tous.
                            </p>
                        )}

                        <div className="flex items-baseline justify-between">
                            <h2 className="text-text text-base font-semibold">
                                Permissions
                            </h2>
                            <span className="text-text-muted tabular text-sm">
                                {selected.length} selectionnee
                                {selected.length > 1 ? 's' : ''}
                            </span>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {catalogue.map((entry) => {
                                const slugs = entry.permissions.map(
                                    (permission) => permission.slug,
                                );
                                const allOn = slugs.every((slug) =>
                                    selected.includes(slug),
                                );

                                return (
                                    <fieldset
                                        key={entry.module}
                                        className="border-border rounded-lg border p-4"
                                    >
                                        <legend className="flex items-center gap-2 px-1">
                                            <span className="text-text text-sm font-medium">
                                                {moduleLabels[entry.module] ??
                                                    entry.module}
                                            </span>
                                        </legend>
                                        <button
                                            type="button"
                                            onClick={() => toggleModule(entry)}
                                            className="text-accent hover:text-accent-hover mb-3 text-xs"
                                        >
                                            {allOn
                                                ? 'Tout decocher'
                                                : 'Tout cocher'}
                                        </button>
                                        <div className="space-y-2">
                                            {entry.permissions.map(
                                                (permission) => (
                                                    <label
                                                        key={permission.slug}
                                                        className="flex items-center gap-2.5 text-sm"
                                                    >
                                                        <Checkbox
                                                            checked={selected.includes(
                                                                permission.slug,
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggle(
                                                                    permission.slug,
                                                                )
                                                            }
                                                        />
                                                        <span className="text-text-secondary">
                                                            {permission.label}
                                                        </span>
                                                    </label>
                                                ),
                                            )}
                                        </div>
                                    </fieldset>
                                );
                            })}
                        </div>
                        <InputError message={errors.permissions} />
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
