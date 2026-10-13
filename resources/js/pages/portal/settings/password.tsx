import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { PageHeader } from '@/components/page-header';
import { PortalSettingsNav } from '@/components/portal-settings-nav';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { password as passwordSettings } from '@/routes/portal/settings';
import { update as updatePassword } from '@/routes/portal/settings/password';
import type { SharedData } from '@/types';

export default function PortalPasswordSettings() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Securite" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Securite"
                    description="Modifiez le mot de passe de votre espace alumni"
                />
                <PortalSettingsNav />
                <Form
                    {...updatePassword.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={[
                        'current_password',
                        'password',
                        'password_confirmation',
                    ]}
                    className="max-w-[480px] space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="email"
                                name="username"
                                autoComplete="username"
                                value={auth?.alumni?.email ?? ''}
                                readOnly
                                hidden
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="current_password">
                                    Mot de passe actuel
                                </Label>
                                <PasswordInput
                                    id="current_password"
                                    name="current_password"
                                    required
                                    autoComplete="current-password"
                                    className="h-12"
                                />
                                <InputError message={errors.current_password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    Nouveau mot de passe
                                </Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    className="h-12"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmer le mot de passe
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    className="h-12"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                            <div className="border-border flex justify-end border-t pt-6">
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

PortalPasswordSettings.layout = {
    breadcrumbs: [{ title: 'Securite', href: passwordSettings() }],
};
