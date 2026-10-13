import { Form, Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import OrganizationSettingsController from '@/actions/App/Http/Controllers/OrganizationSettingsController';
import InputError from '@/components/input-error';
import { LogoUploadField } from '@/components/logo-upload-field';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/organization';
import type { PlanUsage } from '@/types';

type Props = {
    organization: {
        name: string;
        email: string;
        phone: string | null;
        address: string | null;
        logo_url: string | null;
    };
    subscription: {
        plan: string | null;
        billing_cycle: 'monthly' | 'yearly';
        ends_at: string;
    } | null;
    usage: Record<'alumni' | 'events' | 'campaigns' | 'users', PlanUsage>;
};

const usageLabels: Record<string, string> = {
    alumni: 'Alumni',
    events: 'Evenements',
    campaigns: 'Campagnes',
    users: 'Utilisateurs',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

export default function OrganizationEdit({
    organization,
    subscription,
    usage,
}: Props) {
    return (
        <>
            <Head title="Organisation" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Organisation"
                    description="Les informations et le logo affiches a vos utilisateurs"
                />

                <Form
                    {...OrganizationSettingsController.update.form()}
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
                                    Identite
                                </h2>

                                <LogoUploadField
                                    currentUrl={organization.logo_url}
                                    error={errors.logo}
                                    allowRemoval
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Nom
                                        <span className="text-danger ml-0.5">
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={organization.name}
                                        className="h-12"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </section>

                            <section className="space-y-4">
                                <h2 className="text-text text-base font-semibold">
                                    Contact
                                </h2>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        E-mail de contact
                                        <span className="text-danger ml-0.5">
                                            *
                                        </span>
                                    </Label>
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
                                            defaultValue={
                                                organization.phone ?? ''
                                            }
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
                            </section>

                            <div className="border-border flex items-center justify-end border-t pt-6">
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

                <section className="border-border bg-surface rounded-lg border">
                    <header className="border-border border-b px-5 py-4">
                        <h2 className="text-text text-base font-semibold">
                            Abonnement
                        </h2>
                        <p className="text-text-secondary text-sm">
                            {subscription
                                ? `Plan ${subscription.plan} — ${subscription.billing_cycle === 'yearly' ? 'annuel' : 'mensuel'}, jusqu au ${formatDate(subscription.ends_at)}`
                                : 'Aucun abonnement actif. Contactez la plateforme pour en obtenir un.'}
                        </p>
                    </header>

                    <div className="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                        {(
                            Object.keys(usageLabels) as (keyof typeof usage)[]
                        ).map((key) => (
                            <StatCard
                                key={key}
                                label={usageLabels[key]}
                                value={
                                    usage[key].limit === null
                                        ? `${usage[key].used} / illimite`
                                        : `${usage[key].used} / ${usage[key].limit}`
                                }
                                icon={Building2}
                                hint="Consommation de votre plan pour cette ressource."
                            />
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}

OrganizationEdit.layout = {
    breadcrumbs: [{ title: 'Organisation', href: edit() }],
};
