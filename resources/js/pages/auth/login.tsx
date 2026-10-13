import { Form, Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const fillDemo = (email: string) => {
        const emailInput = document.getElementById('email') as HTMLInputElement | null;
        const passwordInput = document.getElementById('password') as HTMLInputElement | null;
        if (emailInput) {
            emailInput.value = email;
            emailInput.dispatchEvent(new Event('input', { bubbles: true }));
            emailInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (passwordInput) {
            passwordInput.value = 'password';
            passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
            passwordInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    return (
        <>
            <Head title="Connexion" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5 tracking-normal font-sans"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-4 sm:gap-5">
                        {/* Email Field */}
                        <div className="grid gap-1.5">
                            <Label htmlFor="email" className="text-xs font-normal text-slate-700 dark:text-slate-300 tracking-normal">
                                Adresse e-mail
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="nom@etablissement.fr"
                                className="h-11 sm:h-12 rounded-xl border-slate-200 dark:border-white/10 px-4 text-sm font-light tracking-normal placeholder:font-light focus:border-[#16375A] focus:ring-1 focus:ring-[#16375A]"
                            />
                            <InputError message={errors.email} />
                        </div>

                        {/* Password Field */}
                        <div className="grid gap-1.5">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password" className="text-xs font-normal text-slate-700 dark:text-slate-300 tracking-normal">
                                    Mot de passe
                                </Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-xs text-[#2E6F9E] dark:text-sky-400 hover:underline font-light tracking-normal"
                                        tabIndex={5}
                                    >
                                        Mot de passe oublié ?
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Votre mot de passe"
                                className="h-11 sm:h-12 rounded-xl border-slate-200 dark:border-white/10 px-4 text-sm font-light tracking-normal placeholder:font-light focus:border-[#16375A] focus:ring-1 focus:ring-[#16375A]"
                            />
                            <InputError message={errors.password} />
                        </div>

                        {/* Remember Me */}
                        <div className="flex items-center space-x-2.5">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={3}
                            />
                            <Label htmlFor="remember" className="text-xs font-light text-slate-600 dark:text-slate-400 cursor-pointer select-none tracking-normal">
                                Se souvenir de moi
                            </Label>
                        </div>

                        {/* Submit Button */}
                        <Button
                            type="submit"
                            className="w-full h-11 sm:h-12 bg-[#0c1e33] hover:bg-[#16375A] text-white font-normal text-sm rounded-xl shadow-md transition-all cursor-pointer mt-1 tracking-normal"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Se connecter
                        </Button>

                        {/* Fast Demo Accounts */}
                        <div className="pt-3 border-t border-slate-100 dark:border-white/10 space-y-2">
                            <div className="flex items-center justify-between text-[11px] font-normal text-slate-400 tracking-normal">
                                <span>Comptes de test (démo)</span>
                                <span className="font-light text-[10px] text-slate-400 lowercase tracking-normal">mdp: password</span>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => fillDemo('admin@uh2.test')}
                                    className="inline-flex items-center gap-1.5 text-xs font-normal px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer tracking-normal border border-slate-200/60 dark:border-white/5"
                                    title="admin@uh2.test"
                                >
                                    <span className="size-1.5 rounded-full bg-[#16375A] dark:bg-sky-400" />
                                    <span>Univ. Hassan II</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => fillDemo('admin@ecc.test')}
                                    className="inline-flex items-center gap-1.5 text-xs font-normal px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer tracking-normal border border-slate-200/60 dark:border-white/5"
                                    title="admin@ecc.test"
                                >
                                    <span className="size-1.5 rounded-full bg-[#16375A] dark:bg-sky-400" />
                                    <span>Centrale Casablanca</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => fillDemo('admin@aeterna.test')}
                                    className="inline-flex items-center gap-1.5 text-xs font-normal px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer tracking-normal border border-slate-200/60 dark:border-white/5"
                                    title="admin@aeterna.test"
                                >
                                    <span className="size-1.5 rounded-full bg-[#16375A] dark:bg-sky-400" />
                                    <span>Super Admin</span>
                                </button>
                            </div>
                        </div>

                        {/* Alumni Portal Quick Access */}
                        <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-white/10 text-xs text-slate-600 dark:text-slate-300 space-y-1 mt-1">
                            <div className="flex items-center gap-1.5 font-normal text-[#16375A] dark:text-sky-300 tracking-normal">
                                <Building2 className="size-4 text-[#16375A] dark:text-sky-400" />
                                <span>Espace Étudiant / Diplômé</span>
                            </div>
                            <p className="text-[11px] font-light text-slate-500 dark:text-slate-400 tracking-normal">
                                Accédez directement au portail de votre université ou école :
                            </p>
                            <div className="flex flex-wrap items-center gap-2 pt-1 font-normal tracking-normal">
                                <Link
                                    href="/portail/universite-hassan-ii/connexion"
                                    className="text-[11px] text-[#16375A] dark:text-sky-400 hover:underline tracking-normal"
                                >
                                    Portail Univ. Hassan II &rarr;
                                </Link>
                                <span className="text-slate-300 dark:text-slate-600">&bull;</span>
                                <Link
                                    href="/portail/ecole-centrale-casablanca/connexion"
                                    className="text-[11px] text-[#16375A] dark:text-sky-400 hover:underline tracking-normal"
                                >
                                    Portail Centrale Casablanca &rarr;
                                </Link>
                            </div>
                        </div>
                    </div>
                )}
            </Form>

            {status && (
                <div className="text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800/40 p-3 rounded-xl mt-3 text-center text-xs font-light tracking-normal">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Bienvenue sur Aeterna',
    description: 'Connectez-vous à votre espace établissement',
};
