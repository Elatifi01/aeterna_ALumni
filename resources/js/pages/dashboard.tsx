import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    GraduationCap,
    Mail,
    Plus,
    Send,
    Sparkles,
    TrendingUp,
    Users,
    Wallet,
    ArrowUpRight,
    Building2,
    MapPin,
    Briefcase,
} from 'lucide-react';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AvatarInitials } from '@/components/avatar-initials';
import { PaymentModal } from '@/components/payment-modal';
import type { SharedData } from '@/types';

type RecentAlumniItem = {
    id: number;
    first_name: string;
    last_name: string;
    degree?: string | null;
    graduation_year?: number | null;
    company?: string | null;
    city?: string | null;
    created_at?: string | null;
};

type UpcomingEventItem = {
    id: number;
    title: string;
    start_date?: string | null;
    location?: string | null;
    capacity?: number | null;
    status?: string | null;
    registrations_count?: number;
};

type ActivityItem = {
    type: 'alumni' | 'event' | 'campaign';
    label: string;
    subtitle?: string;
    at: string;
    link?: string;
};

type Props = {
    organization?: {
        name: string;
        slug: string;
        logo_url?: string | null;
    };
    stats?: {
        alumni: number;
        events: number;
        campaigns: number;
        users: number;
        participationRate: number;
    };
    subscription?: {
        isActive: boolean;
        planName: string;
        price: number;
        billingCycle: string;
        alumniLimit?: number | null;
        eventsLimit?: number | null;
        campaignsLimit?: number | null;
        usersLimit?: number | null;
    };
    recentAlumni?: RecentAlumniItem[];
    upcomingEvents?: UpcomingEventItem[];
    recentActivity?: ActivityItem[];
};

export default function Dashboard({
    organization,
    stats,
    subscription,
    recentAlumni = [],
    upcomingEvents = [],
    recentActivity = [],
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user;
    const userName = user?.name ?? 'Administrateur';
    const orgName = organization?.name ?? auth?.organization?.name ?? 'Établissement';

    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);

    const alumniCount = stats?.alumni ?? 0;
    const eventsCount = stats?.events ?? 0;
    const campaignsCount = stats?.campaigns ?? 0;
    const participationRate = stats?.participationRate ?? 82.5;

    // Date formatting helper
    const formatDate = (dateString?: string | null) => {
        if (!dateString) return 'Récemment';
        try {
            const d = new Date(dateString);
            return d.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'short',
            });
        } catch {
            return dateString;
        }
    };

    return (
        <>
            <Head title={`Tableau de bord — ${orgName}`} />

            <div className="min-h-screen bg-[#eaecf2] dark:bg-[#0b0f19] p-3 sm:p-5 lg:p-6 flex flex-col md:flex-row gap-5 lg:gap-6 font-sans antialiased text-[#15171C] dark:text-slate-100">
                {/* Navigation Dock */}
                <AppSidebar onOpenPayment={() => setIsPaymentModalOpen(true)} />

                {/* Main Window Container */}
                <div className="flex-1 min-w-0 bg-[#f4f5f9] dark:bg-slate-900/90 rounded-[32px] sm:rounded-[36px] shadow-2xl p-4 sm:p-6 lg:p-7 flex flex-col border border-white/80 dark:border-white/10 min-h-[calc(100vh-3rem)]">
                    {/* Top Header */}
                    <AppSidebarHeader
                        breadcrumbs={[{ title: 'Tableau de bord', href: '/dashboard' }]}
                        onOpenPayment={() => setIsPaymentModalOpen(true)}
                    />

                    <main className="flex-1 min-w-0 mt-5 space-y-6">
                        {/* Welcome Hero Banner */}
                        <div className="bg-white dark:bg-slate-800/90 rounded-[28px] p-6 sm:p-8 border border-slate-200/70 dark:border-white/10 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                            <div className="space-y-2">
                                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-50 dark:bg-sky-950/60 text-[#2E6F9E] dark:text-sky-300 text-xs font-semibold border border-sky-200/70 dark:border-sky-800/40">
                                    <Building2 className="size-3.5" />
                                    <span>{orgName}</span>
                                    <span className="text-slate-300 dark:text-slate-600">&bull;</span>
                                    <span className="text-emerald-600 dark:text-emerald-400 font-medium">Espace Actif</span>
                                </div>
                                <h2 className="text-2xl sm:text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                    Bonjour, {userName}
                                </h2>
                                <p className="text-slate-500 dark:text-slate-400 text-sm max-w-2xl leading-relaxed">
                                    Bienvenue sur le tableau de bord de votre réseau d'Alumni. Suivez vos indicateurs clés, animez votre communauté et pilotez vos événements en direct.
                                </p>
                            </div>

                            {/* Quick Action Buttons */}
                            <div className="flex flex-wrap items-center gap-2.5 self-start lg:self-auto">
                                <Link
                                    href="/alumni/create"
                                    className="inline-flex items-center gap-2 bg-[#16375A] hover:bg-[#0F2740] text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-xs hover:shadow-md hover:-translate-y-0.5 transition-[transform,background-color,box-shadow] duration-150 ease-out active:scale-[0.96] will-change-transform"
                                >
                                    <Plus className="size-4" />
                                    <span>Nouveau Diplômé</span>
                                </Link>
                                <Link
                                    href="/events/create"
                                    className="inline-flex items-center gap-2 bg-white dark:bg-slate-700 hover:bg-slate-50 text-[#15171C] dark:text-white text-xs font-semibold px-3.5 py-2.5 rounded-xl border border-slate-200/90 dark:border-white/10 shadow-2xs hover:shadow-xs hover:-translate-y-0.5 transition-[transform,background-color,border-color,box-shadow] duration-150 ease-out active:scale-[0.96] will-change-transform"
                                >
                                    <CalendarDays className="size-4 text-[#2E6F9E]" />
                                    <span>Événement</span>
                                </Link>
                                <Link
                                    href="/campaigns/create"
                                    className="inline-flex items-center gap-2 bg-white dark:bg-slate-700 hover:bg-slate-50 text-[#15171C] dark:text-white text-xs font-semibold px-3.5 py-2.5 rounded-xl border border-slate-200/90 dark:border-white/10 shadow-2xs hover:shadow-xs hover:-translate-y-0.5 transition-[transform,background-color,border-color,box-shadow] duration-150 ease-out active:scale-[0.96] will-change-transform"
                                >
                                    <Mail className="size-4 text-[#2E6F9E]" />
                                    <span>Campagne</span>
                                </Link>
                                <Link
                                    href="/assistant"
                                    className="inline-flex items-center gap-1.5 bg-gradient-to-r from-sky-500 to-indigo-600 hover:from-sky-600 hover:to-indigo-700 text-white text-xs font-semibold px-3.5 py-2.5 rounded-xl shadow-sm shadow-sky-500/20 hover:shadow-md hover:shadow-sky-500/30 hover:-translate-y-0.5 transition-[transform,box-shadow] duration-150 ease-out active:scale-[0.96] will-change-transform"
                                >
                                    <Sparkles className="size-3.5" />
                                    <span>Assistant IA</span>
                                </Link>
                            </div>
                        </div>

                        {/* 4 KPI Cards Grid */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
                            {/* Card 1: Alumni */}
                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-white/20 transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-1 active:scale-[0.985] will-change-transform flex flex-col justify-between group cursor-default">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Diplômés Référencés
                                    </span>
                                    <div className="size-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#2E6F9E] dark:text-sky-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 ease-out">
                                        <GraduationCap className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                        {alumniCount.toLocaleString('fr-FR')}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400 dark:text-slate-400">
                                            {subscription?.alumniLimit ? `Limite : ${subscription.alumniLimit}` : 'Annuaire complet'}
                                        </span>
                                        <Link href="/alumni" className="text-[#2E6F9E] dark:text-sky-400 font-semibold hover:underline inline-flex items-center gap-0.5">
                                            Gérer &rarr;
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            {/* Card 2: Participation Rate */}
                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-white/20 transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-1 active:scale-[0.985] will-change-transform flex flex-col justify-between group cursor-default">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Taux d'Engagement
                                    </span>
                                    <div className="size-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 ease-out">
                                        <TrendingUp className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight flex items-baseline gap-1.5">
                                        <span>{participationRate}%</span>
                                        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            Présence moyenne
                                        </span>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400 dark:text-slate-400">
                                            Sur l'ensemble des événements
                                        </span>
                                        <span className="text-emerald-600 dark:text-emerald-400 font-semibold inline-flex items-center gap-1">
                                            <CheckCircle2 className="size-3" /> Excellent
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Card 3: Events */}
                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-white/20 transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-1 active:scale-[0.985] will-change-transform flex flex-col justify-between group cursor-default">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Événements
                                    </span>
                                    <div className="size-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 ease-out">
                                        <CalendarDays className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                        {eventsCount}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400 dark:text-slate-400">
                                            {upcomingEvents.length} en préparation
                                        </span>
                                        <Link href="/events" className="text-[#2E6F9E] dark:text-sky-400 font-semibold hover:underline inline-flex items-center gap-0.5">
                                            Calendrier &rarr;
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            {/* Card 4: Campaigns */}
                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-white/20 transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-1 active:scale-[0.985] will-change-transform flex flex-col justify-between group cursor-default">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Campagnes d'Emails
                                    </span>
                                    <div className="size-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center group-hover:scale-110 transition-transform duration-200 ease-out">
                                        <Mail className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                        {campaignsCount}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400 dark:text-slate-400">
                                            Newsletters &amp; invitations
                                        </span>
                                        <Link href="/campaigns" className="text-[#2E6F9E] dark:text-sky-400 font-semibold hover:underline inline-flex items-center gap-0.5">
                                            Diffusions &rarr;
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Main Grid: Left 7 cols, Right 5 cols */}
                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">
                            {/* Left Column: Recent Alumni & Activity */}
                            <div className="lg:col-span-7 xl:col-span-8 space-y-5 sm:space-y-6">
                                {/* Table: Latest Alumni */}
                                <div className="bg-white dark:bg-slate-800/90 rounded-[28px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-base font-bold text-[#15171C] dark:text-white">
                                                Derniers Diplômés Référencés
                                            </h3>
                                            <p className="text-xs text-slate-400 dark:text-slate-400 mt-0.5">
                                                Profils récemment enregistrés dans l'annuaire de l'établissement
                                            </p>
                                        </div>
                                        <Link
                                            href="/alumni"
                                            className="text-xs font-semibold text-[#2E6F9E] dark:text-sky-400 hover:underline inline-flex items-center gap-1"
                                        >
                                            <span>Voir tout</span>
                                            <ArrowUpRight className="size-3.5" />
                                        </Link>
                                    </div>

                                    {recentAlumni.length > 0 ? (
                                        <div className="overflow-x-auto -mx-2 sm:mx-0">
                                            <table className="w-full text-left text-xs">
                                                <thead>
                                                    <tr className="border-b border-slate-100 dark:border-white/10 text-slate-400 font-medium">
                                                        <th className="pb-3 px-3">Diplômé</th>
                                                        <th className="pb-3 px-3">Diplôme / Promo</th>
                                                        <th className="pb-3 px-3">Entreprise</th>
                                                        <th className="pb-3 px-3 text-right">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100 dark:divide-white/5">
                                                    {recentAlumni.map((item) => (
                                                        <tr key={item.id} className="hover:bg-slate-50/70 dark:hover:bg-white/5 transition-colors">
                                                            <td className="py-3 px-3">
                                                                <div className="flex items-center gap-2.5">
                                                                    <AvatarInitials
                                                                        firstName={item.first_name}
                                                                        lastName={item.last_name}
                                                                    />
                                                                    <div>
                                                                        <div className="font-semibold text-slate-800 dark:text-slate-200">
                                                                            {item.first_name} {item.last_name}
                                                                        </div>
                                                                        {item.city && (
                                                                            <div className="text-[11px] text-slate-400 flex items-center gap-1">
                                                                                <MapPin className="size-3" />
                                                                                <span>{item.city}</span>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td className="py-3 px-3">
                                                                <div className="font-medium text-slate-700 dark:text-slate-300">
                                                                    {item.degree ?? 'Cursus standard'}
                                                                </div>
                                                                <div className="text-[11px] text-slate-400">
                                                                    {item.graduation_year ? `Promo ${item.graduation_year}` : 'Promo non spécifiée'}
                                                                </div>
                                                            </td>
                                                            <td className="py-3 px-3">
                                                                {item.company ? (
                                                                    <div className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-medium">
                                                                        <Briefcase className="size-3 text-slate-400" />
                                                                        <span>{item.company}</span>
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-slate-400 italic">En recherche / Non renseigné</span>
                                                                )}
                                                            </td>
                                                            <td className="py-3 px-3 text-right">
                                                                <Link
                                                                    href={`/alumni/${item.id}`}
                                                                    className="text-xs font-semibold text-[#2E6F9E] dark:text-sky-400 hover:underline"
                                                                >
                                                                    Fiche &rarr;
                                                                </Link>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    ) : (
                                        <div className="text-center py-8 text-slate-400 text-xs">
                                            Aucun diplômé enregistré pour l'instant.
                                        </div>
                                    )}
                                </div>

                                {/* Feed: Recent Activity Stream */}
                                <div className="bg-white dark:bg-slate-800/90 rounded-[28px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h3 className="text-base font-bold text-[#15171C] dark:text-white">
                                                Activité Récente du Réseau
                                            </h3>
                                            <p className="text-xs text-slate-400 dark:text-slate-400 mt-0.5">
                                                Historique en direct des ajouts, publications et diffusions
                                            </p>
                                        </div>
                                        <div className="text-xs text-slate-400 font-medium">
                                            Temps réel
                                        </div>
                                    </div>

                                    {recentActivity.length > 0 ? (
                                        <div className="space-y-3">
                                            {recentActivity.map((act, index) => {
                                                const iconMap = {
                                                    alumni: <GraduationCap className="size-4 text-blue-600 dark:text-blue-400" />,
                                                    event: <CalendarDays className="size-4 text-purple-600 dark:text-purple-400" />,
                                                    campaign: <Send className="size-4 text-amber-600 dark:text-amber-400" />,
                                                };
                                                const badgeBgMap = {
                                                    alumni: 'bg-blue-50 dark:bg-blue-950/60',
                                                    event: 'bg-purple-50 dark:bg-purple-950/60',
                                                    campaign: 'bg-amber-50 dark:bg-amber-950/60',
                                                };

                                                return (
                                                    <div
                                                        key={index}
                                                        className="flex items-center justify-between p-3 rounded-2xl bg-slate-50/70 dark:bg-white/5 border border-slate-100 dark:border-white/5 hover:bg-slate-100/70 dark:hover:bg-white/10 transition-colors"
                                                    >
                                                        <div className="flex items-center gap-3 min-w-0">
                                                            <div className={`size-9 rounded-xl ${badgeBgMap[act.type]} flex items-center justify-center shrink-0`}>
                                                                {iconMap[act.type]}
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
                                                                    {act.label}
                                                                </div>
                                                                <div className="text-[11px] text-slate-400">
                                                                    {act.subtitle ?? act.type}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 shrink-0 ml-3">
                                                            {formatDate(act.at)}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        <div className="text-center py-6 text-slate-400 text-xs">
                                            Aucune activité récente à afficher.
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Right Column: Subscription & Upcoming Events */}
                            <div className="lg:col-span-5 xl:col-span-4 space-y-5 sm:space-y-6">
                                {/* Subscription & Quotas Card */}
                                <div className="bg-white dark:bg-slate-800/90 rounded-[28px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 text-xs font-bold border border-emerald-200 dark:border-emerald-800/40">
                                            <span className="size-2 rounded-full bg-emerald-500 animate-pulse" />
                                            <span>Plan {subscription?.planName ?? 'Pro'} Actif</span>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setIsPaymentModalOpen(true)}
                                            className="text-xs text-[#2E6F9E] dark:text-sky-400 font-semibold hover:underline cursor-pointer"
                                        >
                                            Gérer &rarr;
                                        </button>
                                    </div>

                                    <div className="space-y-1">
                                        <div className="text-2xl font-black text-[#15171C] dark:text-white">
                                            {subscription?.price ? `${subscription.price} €` : '49.00 €'}
                                            <span className="text-xs text-slate-400 font-normal"> / mois</span>
                                        </div>
                                        <p className="text-xs text-slate-500 dark:text-slate-400">
                                            Souscription active pour l'établissement {orgName}.
                                        </p>
                                    </div>

                                    {/* Quota Progress Bars */}
                                    <div className="space-y-3 pt-2 border-t border-slate-100 dark:border-white/10">
                                        <div>
                                            <div className="flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                                                <span>Alumni enregistrés</span>
                                                <span className="text-slate-400">{alumniCount} / {subscription?.alumniLimit ?? 'Illimité'}</span>
                                            </div>
                                            <div className="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                                <div
                                                    className="h-full bg-[#16375A] dark:bg-sky-500 rounded-full transition-all"
                                                    style={{ width: `${Math.min(100, Math.max(15, (alumniCount / (subscription?.alumniLimit || 1000)) * 100))}%` }}
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <div className="flex items-center justify-between text-xs font-medium text-slate-600 dark:text-slate-300 mb-1">
                                                <span>Événements organisés</span>
                                                <span className="text-slate-400">{eventsCount} / {subscription?.eventsLimit ?? 'Illimité'}</span>
                                            </div>
                                            <div className="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                                <div
                                                    className="h-full bg-purple-600 rounded-full transition-all"
                                                    style={{ width: `${Math.min(100, Math.max(20, (eventsCount / (subscription?.eventsLimit || 20)) * 100))}%` }}
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() => setIsPaymentModalOpen(true)}
                                        className="w-full mt-2 inline-flex items-center justify-center gap-2 bg-[#16375A] hover:bg-[#0F2740] text-white text-xs font-semibold py-2.5 rounded-xl shadow-sm transition-all cursor-pointer"
                                    >
                                        <Wallet className="size-4" />
                                        <span>Recharger / Facturation</span>
                                    </button>
                                </div>

                                {/* Upcoming Events Card */}
                                <div className="bg-white dark:bg-slate-800/90 rounded-[28px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-base font-bold text-[#15171C] dark:text-white">
                                            Prochains Événements
                                        </h3>
                                        <Link
                                            href="/events"
                                            className="text-xs font-semibold text-[#2E6F9E] dark:text-sky-400 hover:underline"
                                        >
                                            Tous &rarr;
                                        </Link>
                                    </div>

                                    {upcomingEvents.length > 0 ? (
                                        <div className="space-y-3">
                                            {upcomingEvents.map((ev) => (
                                                <Link
                                                    key={ev.id}
                                                    href={`/events/${ev.id}`}
                                                    className="flex items-center gap-3 p-3 rounded-2xl bg-slate-50/70 dark:bg-white/5 border border-slate-100 dark:border-white/5 hover:border-slate-300 dark:hover:border-white/20 transition-all block group"
                                                >
                                                    <div className="size-10 rounded-xl bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 font-bold flex flex-col items-center justify-center text-xs shrink-0 overflow-hidden">
                                                        {ev.start_date ? (
                                                            <>
                                                                <span className="text-[9px] leading-none uppercase font-normal text-purple-600 dark:text-purple-400">
                                                                    {new Date(ev.start_date).toLocaleDateString('fr-FR', { month: 'short' })}
                                                                </span>
                                                                <span className="text-xs font-bold leading-none mt-0.5">
                                                                    {new Date(ev.start_date).getDate()}
                                                                </span>
                                                            </>
                                                        ) : (
                                                            <CalendarDays className="size-5" />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="font-semibold text-xs text-slate-800 dark:text-slate-200 truncate group-hover:text-[#2E6F9E] transition-colors">
                                                            {ev.title}
                                                        </div>
                                                        <div className="text-[11px] text-slate-400 truncate">
                                                            {ev.location ?? 'En ligne'} &bull; {ev.registrations_count ?? 0} inscrit(s)
                                                        </div>
                                                    </div>
                                                </Link>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-6 text-slate-400 text-xs">
                                            Aucun événement planifié pour le moment.
                                        </div>
                                    )}
                                </div>

                                {/* Useful Shortcuts Card */}
                                <div className="bg-gradient-to-br from-[#16375A] to-[#0f2740] rounded-[28px] p-6 text-white shadow-xl space-y-4 relative overflow-hidden">
                                    <div className="relative z-10 space-y-2">
                                        <div className="inline-flex items-center gap-1.5 text-[11px] font-bold text-sky-300 uppercase tracking-wider bg-white/10 px-2.5 py-1 rounded-full border border-white/10">
                                            <Sparkles className="size-3" />
                                            <span>Outils d'Insertion</span>
                                        </div>
                                        <h4 className="text-base font-bold text-white">
                                            Assistant IA &amp; Import Alumni
                                        </h4>
                                        <p className="text-xs text-slate-300 leading-relaxed">
                                            Importez facilement vos listes de diplômés par fichier Excel ou utilisez l'IA pour analyser vos promotions.
                                        </p>
                                    </div>

                                    <div className="relative z-10 grid grid-cols-2 gap-2 pt-1">
                                        <Link
                                            href="/alumni/import"
                                            className="inline-flex items-center justify-center gap-1.5 bg-white/15 hover:bg-white/20 text-white text-xs font-semibold p-2.5 rounded-xl border border-white/15 transition-colors"
                                        >
                                            <FileSpreadsheet className="size-3.5 text-emerald-300" />
                                            <span>Import Excel</span>
                                        </Link>
                                        <Link
                                            href="/assistant"
                                            className="inline-flex items-center justify-center gap-1.5 bg-white text-[#16375A] hover:bg-slate-100 text-xs font-bold p-2.5 rounded-xl transition-colors shadow-sm"
                                        >
                                            <Sparkles className="size-3.5 text-indigo-600" />
                                            <span>Assistant IA</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>

                {/* Global Interactive Payment Modal */}
                <PaymentModal
                    isOpen={isPaymentModalOpen}
                    onClose={() => setIsPaymentModalOpen(false)}
                />
            </div>
        </>
    );
}
