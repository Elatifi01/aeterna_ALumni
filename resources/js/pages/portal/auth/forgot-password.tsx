import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes/portal';
import { email as sendResetLink } from '@/routes/portal/password';

type Props = {
    organization: {
        name: string;
        slug: string;
        logo_url: string | null;
    };
    status?: string;
};

export default function PortalForgotPassword({ organization, status }: Props) {
    return (
        <>
            <Head title="Mot de passe oublie" />

            {status && (
                <div className="text-success mb-4 text-center text-sm font-medium">
                    {status}
                </div>
            )}

            <Form
                {...sendResetLink.form({ organization: organization.slug })}
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
                                autoFocus
                                autoComplete="email"
                                placeholder="vous@exemple.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Envoyer le lien
                        </Button>
                    </div>
                )}
            </Form>

            <div className="text-text-muted mt-6 text-center text-sm">
                <TextLink href={login({ organization: organization.slug })}>
                    Retour a la connexion
                </TextLink>
            </div>
        </>
    );
}

PortalForgotPassword.layout = {
    title: 'Mot de passe oublie',
    description: 'Nous vous enverrons un lien pour en choisir un nouveau',
};
