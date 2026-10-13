import { Form, Head, usePage } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes/portal';
import { store as submitPassword } from '@/routes/portal/password/change';
import type { SharedData } from '@/types';

export default function PortalChangePassword() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Choisir votre mot de passe" />
            <div className="mb-6 flex justify-center">
                <span className="bg-brand-light/30 text-primary dark:text-text flex size-12 items-center justify-center rounded-full">
                    <KeyRound className="size-6" />
                </span>
            </div>
            <p className="text-text-secondary mb-6 text-center text-sm">
                Le mot de passe que vous avez recu par e-mail est provisoire.
                Choisissez-en un nouveau pour acceder a votre espace.
            </p>
            <Form
                {...submitPassword.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <input
                            type="email"
                            name="username"
                            autoComplete="username"
                            value={auth?.alumni?.email ?? ''}
                            readOnly
                            hidden
                        />
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
                            Enregistrer et continuer
                        </Button>
                    </div>
                )}
            </Form>
            <Form {...logout.form()} className="mt-6 text-center">
                <button
                    type="submit"
                    className="text-text-muted hover:text-text text-sm underline underline-offset-4"
                >
                    Se deconnecter
                </button>
            </Form>
        </>
    );
}

PortalChangePassword.layout = {
    title: 'Choisir votre mot de passe',
    description: 'Une derniere etape avant d acceder a votre espace',
};
