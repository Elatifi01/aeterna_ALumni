import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="min-h-screen flex items-center justify-center p-3 sm:p-6 lg:p-10 font-sans tracking-normal antialiased text-[#15171C] dark:text-slate-100 relative overflow-hidden">
            
            {/* General Background: Authentic Hassan II Mosque & Moroccan Heritage */}
            <div className="absolute inset-0 z-0 pointer-events-none select-none overflow-hidden">
                <img
                    src="/images/morocco-hassan-bg.jpg"
                    alt="Mosquée Hassan II à Casablanca — Cadre scolaire et universitaire au Maroc"
                    className="size-full object-cover object-[center_35%] scale-105 filter brightness-[0.78] contrast-[1.08] saturate-[1.12]"
                />
                {/* Atmospheric Frosted Tint & Ambient Light */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-slate-950/45 to-black/60 dark:from-[#090d16]/90 dark:via-[#090d16]/70 dark:to-[#090d16]/85" />
                <div className="absolute inset-0 backdrop-blur-[1.5px]" />
            </div>

            {/* Ambient Accent Glows */}
            <div className="absolute top-1/4 -left-32 size-96 rounded-full bg-amber-500/10 blur-3xl pointer-events-none z-1" />
            <div className="absolute bottom-1/4 -right-32 size-96 rounded-full bg-sky-500/15 blur-3xl pointer-events-none z-1" />

            {/* Main Central Card Container */}
            <div className="w-full max-w-5xl bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl rounded-[38px] sm:rounded-[44px] shadow-2xl shadow-black/50 border border-white/80 dark:border-white/15 p-3.5 sm:p-5 lg:p-7 grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-stretch relative z-10">
                
                {/* Left Panel: Moroccan Academic Campus Photo with Frosted Overlay */}
                <div className="lg:col-span-5 relative rounded-[28px] sm:rounded-[34px] overflow-hidden min-h-[460px] lg:min-h-[580px] shadow-lg flex flex-col justify-between p-4 sm:p-5">
                    {/* Background Moroccan University Campus Image */}
                    <img
                        src="/images/morocco-campus.jpg"
                        alt="Campus universitaire au Maroc — Aeterna"
                        className="absolute inset-0 size-full object-cover object-center"
                    />

                    {/* Subtle photo contrast gradient */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-black/40 pointer-events-none" />

                    {/* Central Frosted Glass Card matching reference mockup without blue opaque tint */}
                    <div className="relative z-10 my-auto size-full bg-black/40 dark:bg-black/60 backdrop-blur-md rounded-[24px] sm:rounded-[28px] p-6 sm:p-8 border border-white/25 text-white flex flex-col justify-between shadow-2xl">
                        {/* Top: — Aeterna — Brand */}
                        <div>
                            <div className="flex items-center justify-center gap-2 text-white/90 font-normal tracking-normal text-xs">
                                <span className="h-px w-6 bg-white/50" />
                                <span className="flex items-center gap-1.5 font-normal tracking-normal">
                                    <AppLogoIcon className="size-4.5 text-white" />
                                    Aeterna
                                </span>
                                <span className="h-px w-6 bg-white/50" />
                            </div>
                        </div>

                        {/* Middle Text: Montserrat Light & Regular */}
                        <div className="my-8 space-y-3">
                            <div className="text-xs font-normal text-amber-200/90 tracking-normal">
                                Réseau Alumni &amp; Établissements d'Enseignement Supérieur
                            </div>
                            <h2 className="text-2xl sm:text-3xl font-normal tracking-normal leading-snug text-white drop-shadow-sm">
                                Fédérez et valorisez votre communauté de diplômés.
                            </h2>
                            <p className="text-xs sm:text-sm font-light text-slate-200 leading-relaxed tracking-normal drop-shadow-xs">
                                Universités et grandes écoles partenaires réunies pour accompagner leurs promotions et booster l'insertion professionnelle au Maroc et à l'international.
                            </p>
                        </div>

                        {/* Bottom: Portal Link for Alumni */}
                        <div className="pt-4 border-t border-white/20 text-xs">
                            <div className="text-slate-300 font-light mb-1 tracking-normal">
                                Vous êtes diplômé(e) ?
                            </div>
                            <Link
                                href="/portail/universite-hassan-ii/connexion"
                                className="inline-flex items-center gap-1 text-white hover:text-amber-200 font-normal tracking-normal transition-colors"
                            >
                                <span>Accéder au Portail Alumni</span>
                                <span>&rarr;</span>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Right Panel: Clean White Authentication Form */}
                <div className="lg:col-span-7 flex flex-col justify-center p-4 sm:p-6 lg:p-8 xl:p-10">
                    <div className="mb-6 sm:mb-8 space-y-1.5">
                        <h1 className="text-2xl sm:text-3xl lg:text-4xl font-normal text-[#15171C] dark:text-white tracking-normal leading-tight">
                            {title || 'Bienvenue sur Aeterna'}
                        </h1>
                        {description && (
                            <p className="text-slate-500 dark:text-slate-400 text-sm font-light tracking-normal">
                                {description}
                            </p>
                        )}
                    </div>

                    {/* The Auth Form Children */}
                    {children}
                </div>
            </div>

            {/* Bottom Credits & Links */}
            <div className="absolute bottom-3 inset-x-0 text-center text-xs text-slate-500 dark:text-slate-400 pointer-events-none font-light tracking-normal">
                <span className="pointer-events-auto">
                    Aeterna &copy; 2026 &bull; Plateforme SaaS de Gestion Alumni
                </span>
            </div>
        </div>
    );
}
