import { Form, Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PortalSettingsNav } from '@/components/portal-settings-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { profile as profileSettings } from '@/routes/portal/settings';
import { update } from '@/routes/portal/settings/profile';

type Props = {
    profile: {
        phone: string | null;
        company: string | null;
        job_title: string | null;
        city: string | null;
    };
    locked: {
        first_name: string;
        last_name: string;
        email: string;
        degree: string | null;
        graduation_year: number | null;
    };
};

export default function PortalProfile({ profile, locked }: Props) {
    return (
        <>
            <Head title="Mon profil" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Mon profil"
                    description="Tenez vos coordonnees a jour pour votre etablissement"
                />
                <PortalSettingsNav />
                <Form
                    {...update.form()}
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
                                    Coordonnees
                                </h2>
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Telephone</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        defaultValue={profile.phone ?? ''}
                                        autoComplete="tel"
                                        className="h-12"
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="city">Ville</Label>
                                    <Input
                                        id="city"
                                        name="city"
                                        defaultValue={profile.city ?? ''}
                                        autoComplete="address-level2"
                                        className="h-12"
                                    />
                                    <InputError message={errors.city} />
                                </div>
                            </section>
                            <section className="space-y-4">
                                <h2 className="text-text text-base font-semibold">
                                    Situation professionnelle
                                </h2>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="company">
                                            Entreprise
                                        </Label>
                                        <Input
                                            id="company"
                                            name="company"
                                            defaultValue={profile.company ?? ''}
                                            autoComplete="organization"
                                            className="h-12"
                                        />
                                        <InputError message={errors.company} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="job_title">Poste</Label>
                                        <Input
                                            id="job_title"
                                            name="job_title"
                                            defaultValue={
                                                profile.job_title ?? ''
                                            }
                                            autoComplete="organization-title"
                                            className="h-12"
                                        />
                                        <InputError
                                            message={errors.job_title}
                                        />
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
                <section className="border-border bg-surface max-w-[640px] rounded-lg border">
                    <header className="border-border flex items-start gap-3 border-b px-5 py-4">
                        <Lock className="text-text-muted mt-0.5 size-4 shrink-0" />
                        <div>
                            <h2 className="text-text text-base font-semibold">
                                Informations de votre etablissement
                            </h2>
                            <p className="text-text-secondary text-sm">
                                Votre identite et votre parcours sont tenus par
                                votre etablissement. Contactez-le pour toute
                                correction.
                            </p>
                        </div>
                    </header>
                    <dl className="divide-border divide-y">
                        {[
                            { label: 'Prenom', value: locked.first_name },
                            { label: 'Nom', value: locked.last_name },
                            {
                                label: 'Adresse e-mail',
                                value: locked.email,
                                hint: 'Sert aussi d identifiant de connexion',
                            },
                            { label: 'Diplome', value: locked.degree ?? '—' },
                            {
                                label: 'Promotion',
                                value:
                                    locked.graduation_year?.toString() ?? '—',
                            },
                        ].map((row) => (
                            <div
                                key={row.label}
                                className="grid gap-1 px-5 py-3 sm:grid-cols-3 sm:gap-4"
                            >
                                <dt className="text-text-secondary text-sm">
                                    {row.label}
                                </dt>
                                <dd className="text-text text-sm sm:col-span-2">
                                    {row.value}
                                    {row.hint && (
                                        <span className="text-text-muted ml-2 text-xs">
                                            {row.hint}
                                        </span>
                                    )}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            </div>
        </>
    );
}

PortalProfile.layout = {
    breadcrumbs: [{ title: 'Mon profil', href: profileSettings() }],
};
