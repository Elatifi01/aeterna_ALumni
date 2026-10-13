import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as submitLogin } from '@/routes/portal/login';
import { request as forgotPassword } from '@/routes/portal/password';

type Props = {
    organization: {
        name: string;
        slug: string;
        logo_url: string | null;
    };
    status?: string;
};

export default function PortalLogin({ organization, status }: Props) {
    return (
        <>
            <Head title={`Portail alumni — ${organization.name}`} />
            {organization.logo_url && (
                <div className="mb-6 flex justify-center">
                    <img
                        src={organization.logo_url}
                        alt={`Logo ${organization.name}`}
                        className="max-h-16 max-w-[200px] object-contain"
                    />
                </div>
            )}

            {status && (
                <div className="text-success mb-4 text-center text-sm font-medium">
                    {status}
                </div>
            )}

            <Form
                {...submitLogin.form({ organization: organization.slug })}
                resetOnSuccess={['password']}
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
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="vous@exemple.com"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Mot de passe</Label>
                                <TextLink
                                    href={forgotPassword({
                                        organization: organization.slug,
                                    })}
                                    className="ml-auto text-sm"
                                    tabIndex={5}
                                >
                                    Mot de passe oublie ?
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Mot de passe"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="flex items-center space-x-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <Label htmlFor="remember">Se souvenir de moi</Label>
                        </div>
                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            tabIndex={4}
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Se connecter
                        </Button>
                        <p className="text-text-muted text-center text-xs">
                            Vos identifiants vous ont ete envoyes par{' '}
                            {organization.name} par e-mail.
                        </p>
                    </div>
                )}
            </Form>
        </>
    );
}

PortalLogin.layout = {
    title: 'Portail alumni',
    description: 'Saisissez votre e-mail et votre mot de passe pour continuer',
};
