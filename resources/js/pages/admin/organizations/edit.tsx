import { Form, Head, Link } from '@inertiajs/react';
import OrganizationController from '@/actions/App/Http/Controllers/Admin/OrganizationController';
import InputError from '@/components/input-error';
import { LogoUploadField } from '@/components/logo-upload-field';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, index } from '@/routes/admin/organizations';

type Props = {
    organization: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        address: string | null;
        is_active: boolean;
        logo_url: string | null;
    };
};

export default function OrganizationEdit({ organization }: Props) {
    return (
        <>
            <Head title={`Modifier ${organization.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Modifier l organisation"
                    description={organization.name}
                />

                <div className="flex items-center gap-3">
                    {organization.is_active ? (
                        <StatusBadge tone="success" label="Active" />
                    ) : (
                        <StatusBadge tone="neutral" label="Desactivee" />
                    )}

                    <Form
                        {...OrganizationController.toggleActive.form(
                            organization.id,
                        )}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-8"
                                disabled={processing}
                            >
                                {organization.is_active
                                    ? 'Desactiver'
                                    : 'Activer'}
                            </Button>
                        )}
                    </Form>
                </div>

                <Form
                    {...OrganizationController.update.form(organization.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-[640px] space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <LogoUploadField
                                currentUrl={organization.logo_url}
                                error={errors.logo}
                                allowRemoval
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="name">Nom</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={organization.name}
                                    className="h-12"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail de contact</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    defaultValue={organization.email}
                                    className="h-12"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Telephone</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        defaultValue={organization.phone ?? ''}
                                        className="h-12"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Adresse</Label>
                                    <Input
                                        id="address"
                                        name="address"
                                        defaultValue={
                                            organization.address ?? ''
                                        }
                                        className="h-12"
                                    />
                                    <InputError message={errors.address} />
                                </div>
                            </div>

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
                                    Enregistrer
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

OrganizationEdit.layout = ({ organization }: Props) => ({
    breadcrumbs: [
        { title: 'Organisations', href: index() },
        { title: organization.name, href: edit(organization.id) },
    ],
});
