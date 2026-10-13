import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Building2,
    CheckCircle2,
    CreditCard,
    Layers,
    Plus,
    Shield,
    Sparkles,
    Users,
    ArrowUpRight,
} from 'lucide-react';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { SharedData } from '@/types';

type PlanDistItem = {
    name: string;
    price: number;
    organizations: number;
};

type OrgItem = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    created_at: string;
    active_subscription?: {
        plan?: {
            name: string;
        };
    } | null;
};

type Props = {
    stats?: {
        organizations: number;
        activeOrganizations: number;
        activeSubscriptions: number;
    };
    recentOrganizations?: OrgItem[];
    planDistribution?: PlanDistItem[];
    jobBoard?: {
        used?: number;
        budget?: number;
        remaining?: number;
        reset_at?: string;
    };
};

export default function AdminDashboard({
    stats,
    recentOrganizations = [],
    planDistribution = [],
    jobBoard,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user;

    const totalOrgs = stats?.organizations ?? 0;
    const activeOrgs = stats?.activeOrganizations ?? 0;
    const activeSubs = stats?.activeSubscriptions ?? 0;

    const formatDate = (dateString?: string) => {
        if (!dateString) return '—';
        try {
            return new Date(dateString).toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        } catch {
            return dateString;
        }
    };

    return (
        <>
            <Head title="Administration Plateforme — Aeterna" />

            <div className="min-h-screen bg-[#eaecf2] dark:bg-[#0b0f19] p-3 sm:p-5 lg:p-6 flex flex-col md:flex-row gap-5 lg:gap-6 font-sans antialiased text-[#15171C] dark:text-slate-100">
                {/* Admin Sidebar */}
                <AppSidebar />

                {/* Main Window */}
                <div className="flex-1 min-w-0 bg-[#f4f5f9] dark:bg-slate-900/90 rounded-[32px] sm:rounded-[36px] shadow-2xl p-4 sm:p-6 lg:p-7 flex flex-col border border-white/80 dark:border-white/10 min-h-[calc(100vh-3rem)]">
                    <AppSidebarHeader
                        breadcrumbs={[{ title: 'Super Admin', href: '/admin/dashboard' }]}
                    />

                    <main className="flex-1 min-w-0 mt-5 space-y-6">
                        {/* Hero Banner */}
                        <div className="bg-white dark:bg-slate-800/90 rounded-[28px] p-6 sm:p-8 border border-slate-200/70 dark:border-white/10 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                            <div className="space-y-2">
                                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold border border-indigo-200/70 dark:border-indigo-800/40">
                                    <Shield className="size-3.5" />
                                    <span>Super Administration</span>
                                    <span className="text-slate-300 dark:text-slate-600">&bull;</span>
                                    <span className="text-emerald-600 dark:text-emerald-400 font-medium">Plateforme Globale</span>
                                </div>
                                <h2 className="text-2xl sm:text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                    Bonjour, {user?.name ?? 'Super Administrateur'}
                                </h2>
                                <p className="text-slate-500 dark:text-slate-400 text-sm max-w-2xl leading-relaxed">
                                    Pilotez l'ensemble des établissements partenaires, la distribution des forfaits SaaS et la consommation des services externes.
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2.5">
                                <Link
                                    href="/admin/organizations/create"
                                    className="inline-flex items-center gap-2 bg-[#16375A] hover:bg-[#0F2740] text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-sm transition-all"
                                >
                                    <Plus className="size-4" />
                                    <span>Nouvelle Organisation</span>
                                </Link>
                                <Link
                                    href="/admin/plans"
                                    className="inline-flex items-center gap-2 bg-white dark:bg-slate-700 hover:bg-slate-50 text-[#15171C] dark:text-white text-xs font-semibold px-3.5 py-2.5 rounded-xl border border-slate-200/90 dark:border-white/10 shadow-2xs transition-all"
                                >
                                    <Layers className="size-4 text-[#2E6F9E]" />
                                    <span>Catalogue des Plans</span>
                                </Link>
                                <Link
                                    href="/admin/subscriptions"
                                    className="inline-flex items-center gap-2 bg-white dark:bg-slate-700 hover:bg-slate-50 text-[#15171C] dark:text-white text-xs font-semibold px-3.5 py-2.5 rounded-xl border border-slate-200/90 dark:border-white/10 shadow-2xs transition-all"
                                >
                                    <CreditCard className="size-4 text-[#2E6F9E]" />
                                    <span>Abonnements</span>
                                </Link>
                            </div>
                        </div>

                        {/* 4 Stat Cards */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs flex flex-col justify-between">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Organisations Inscrites
                                    </span>
                                    <div className="size-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#2E6F9E] dark:text-sky-400 flex items-center justify-center">
                                        <Building2 className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                        {totalOrgs}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400">Établissements partenaires</span>
                                        <Link href="/admin/organizations" className="text-[#2E6F9E] font-semibold hover:underline">
                                            Voir tout &rarr;
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs flex flex-col justify-between">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Organisations Actives
                                    </span>
                                    <div className="size-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                        <CheckCircle2 className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                        {activeOrgs}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400">Accès et portails opérationnels</span>
                                        <span className="text-emerald-600 font-semibold">{totalOrgs > 0 ? Math.round((activeOrgs / totalOrgs) * 100) : 100}% actif</span>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs flex flex-col justify-between">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Abonnements Actifs
                                    </span>
                                    <div className="size-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                                        <CreditCard className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                        {activeSubs}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400">Souscriptions payantes</span>
                                        <Link href="/admin/subscriptions" className="text-[#2E6F9E] font-semibold hover:underline">
                                            Détails &rarr;
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-white dark:bg-slate-800/90 rounded-[24px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs flex flex-col justify-between">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Crédits Scraper Emploi
                                    </span>
                                    <div className="size-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                        <Activity className="size-5" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-3xl font-extrabold text-[#15171C] dark:text-white tracking-tight">
                                        {jobBoard?.remaining ?? 180}
                                        <span className="text-xs text-slate-400 font-normal"> / {jobBoard?.budget ?? 200}</span>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-xs">
                                        <span className="text-slate-400">Consommation Parse.bot</span>
                                        <span className="text-emerald-600 font-semibold">Solde OK</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Content Grid */}
                        <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">
                            {/* Left: Recent Organizations Table */}
                            <div className="lg:col-span-7 xl:col-span-8 bg-white dark:bg-slate-800/90 rounded-[28px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-base font-bold text-[#15171C] dark:text-white">
                                            Organisations Récentes
                                        </h3>
                                        <p className="text-xs text-slate-400 mt-0.5">
                                            Universités et Grandes Écoles enregistrées sur la plateforme
                                        </p>
                                    </div>
                                    <Link
                                        href="/admin/organizations"
                                        className="text-xs font-semibold text-[#2E6F9E] dark:text-sky-400 hover:underline inline-flex items-center gap-1"
                                    >
                                        <span>Gérer tout</span>
                                        <ArrowUpRight className="size-3.5" />
                                    </Link>
                                </div>

                                <div className="overflow-x-auto -mx-2 sm:mx-0">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b border-slate-100 dark:border-white/10 text-slate-400 font-medium">
                                                <th className="pb-3 px-3">Établissement</th>
                                                <th className="pb-3 px-3">Plan Actuel</th>
                                                <th className="pb-3 px-3">Date d'inscription</th>
                                                <th className="pb-3 px-3 text-right">Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-white/5">
                                            {recentOrganizations.map((org) => (
                                                <tr key={org.id} className="hover:bg-slate-50/70 dark:hover:bg-white/5 transition-colors">
                                                    <td className="py-3 px-3">
                                                        <Link
                                                            href={`/admin/organizations/${org.id}/edit`}
                                                            className="font-semibold text-slate-800 dark:text-slate-200 hover:text-[#2E6F9E]"
                                                        >
                                                            {org.name}
                                                        </Link>
                                                        <div className="text-[11px] text-slate-400">
                                                            slug: {org.slug}
                                                        </div>
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <span className="inline-flex items-center px-2 py-0.5 rounded-md bg-sky-50 dark:bg-sky-950/60 text-[#2E6F9E] dark:text-sky-300 font-medium text-[11px]">
                                                            {org.active_subscription?.plan?.name ?? 'Plan Standard'}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-3 text-slate-500 dark:text-slate-400">
                                                        {formatDate(org.created_at)}
                                                    </td>
                                                    <td className="py-3 px-3 text-right">
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ${
                                                            org.is_active
                                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400'
                                                                : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400'
                                                        }`}>
                                                            {org.is_active ? 'Actif' : 'Suspendu'}
                                                        </span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Right: Plan Distribution */}
                            <div className="lg:col-span-5 xl:col-span-4 space-y-5">
                                <div className="bg-white dark:bg-slate-800/90 rounded-[28px] p-5 sm:p-6 border border-slate-200/70 dark:border-white/10 shadow-xs space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-base font-bold text-[#15171C] dark:text-white">
                                            Distribution par Plan
                                        </h3>
                                        <Link href="/admin/plans" className="text-xs font-semibold text-[#2E6F9E] hover:underline">
                                            Plans &rarr;
                                        </Link>
                                    </div>

                                    <div className="space-y-3.5">
                                        {planDistribution.map((item) => (
                                            <div key={item.name} className="space-y-1.5">
                                                <div className="flex items-center justify-between text-xs font-medium">
                                                    <span className="font-semibold text-slate-800 dark:text-slate-200">{item.name}</span>
                                                    <span className="text-slate-400">
                                                        {item.organizations} souscription(s) &bull; {item.price} €/m
                                                    </span>
                                                </div>
                                                <div className="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                                    <div
                                                        className="h-full bg-[#16375A] dark:bg-sky-500 rounded-full transition-all"
                                                        style={{
                                                            width: `${Math.min(100, Math.max(10, (item.organizations / Math.max(1, totalOrgs)) * 100))}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
