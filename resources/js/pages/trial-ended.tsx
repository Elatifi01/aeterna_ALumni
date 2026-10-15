import { Head, Link } from '@inertiajs/react';
import { LogOut, Mail } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { TicketMascot } from '@/components/landing/mascots';
import { logout } from '@/routes';

type Props = {
    organization: string;
    endedOn: string;
    contact: string | null;
};

export default function TrialEnded({ organization, endedOn, contact }: Props) {
    const ended = new Date(`${endedOn}T00:00:00`).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });

    return (
        <>
            <Head title="Période d’essai terminée" />

            <div className="flex min-h-screen flex-col bg-[linear-gradient(100deg,#9dbcf8_0%,#d6e3fc_48%,#bfeaf2_100%)] px-4 font-sans font-normal text-[#0c1e33] antialiased">
                <header className="mx-auto flex w-full max-w-5xl items-center gap-2 py-6">
                    <span className="flex size-9 items-center justify-center rounded-full bg-[#0c1e33] text-white">
                        <AppLogoIcon className="size-5" />
                    </span>
                    <span className="text-lg font-bold tracking-tight">
                        Aeterna
                    </span>
                </header>

                <main className="flex flex-1 items-center justify-center pb-16">
                    <div className="w-full max-w-xl rounded-[40px] bg-white p-8 text-center shadow-[0_40px_80px_-30px_rgba(12,30,51,0.45)] sm:p-12">
                        <TicketMascot className="mx-auto w-24" />
                        <h1 className="mt-6 text-3xl font-extrabold tracking-[-0.02em]">
                            Votre période d’essai est terminée
                        </h1>
                        <p className="mt-4 leading-relaxed text-[#16375A]/85">
                            L’essai gratuit de <strong>{organization}</strong> a
                            pris fin le {ended}. Toutes vos données sont
                            conservées : choisissez une formule avec notre
                            équipe pour retrouver votre espace là où vous l’avez
                            laissé.
                        </p>

                        <div className="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                            {contact && (
                                <a
                                    href={`mailto:${contact}?subject=${encodeURIComponent(`Fin de l’essai Aeterna — ${organization}`)}`}
                                    className="inline-flex h-12 items-center gap-2 rounded-xl bg-[#0c1e33] px-6 text-sm font-semibold text-white transition-[background-color,transform] duration-150 ease-out hover:bg-[#16375A] active:scale-[0.97]"
                                >
                                    <Mail className="size-4" />
                                    Contacter l’équipe Aeterna
                                </a>
                            )}
                            <Link
                                href={logout()}
                                method="post"
                                as="button"
                                className="inline-flex h-12 cursor-pointer items-center gap-2 rounded-xl px-5 text-sm font-semibold text-[#0c1e33] transition-colors duration-150 hover:bg-slate-100"
                            >
                                <LogOut className="size-4" />
                                Se déconnecter
                            </Link>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
