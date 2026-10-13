import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update as submitReset } from '@/routes/portal/password';

type Props = {
    organization: {
        name: string;
        slug: string;
        logo_url: string | null;
    };
    token: string;
    email: string;
};

export default function PortalResetPassword({
    organization,
    token,
    email,
}: Props) {
    return (
        <>
            <Head title="Nouveau mot de passe" />

            <Form
                {...submitReset.form({
                    organization: organization.slug,
                    token,
                })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Adresse e-mail</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                readOnly
                                defaultValue={email}
                                autoComplete="username"
                                className="bg-surface-muted"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                Nouveau mot de passe
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder="Nouveau mot de passe"
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
                                placeholder="Confirmer le mot de passe"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Enregistrer
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

PortalResetPassword.layout = {
    title: 'Nouveau mot de passe',
    description: 'Choisissez le mot de passe de votre espace alumni',
};
