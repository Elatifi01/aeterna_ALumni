import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    CreditCard,
    GraduationCap,
    Home,
    Layers,
    LifeBuoy,
    LogOut,
    Mail,
    Settings,
    ShieldCheck,
    Sparkles,
    Trash2,
    UsersRound,
    Wallet,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { SidebarFrame } from '@/components/sidebar-frame';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermissions } from '@/lib/permissions';
import { logout } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminOrganizations } from '@/routes/admin/organizations';
import { index as adminPlans } from '@/routes/admin/plans';
import { index as adminRoles } from '@/routes/admin/roles';
import { index as adminSubscriptions } from '@/routes/admin/subscriptions';
import { index as alumniIndex } from '@/routes/alumni';
import { index as assistantIndex } from '@/routes/assistant';
import { index as campaignsIndex } from '@/routes/campaigns';
import { dashboard } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { edit as organizationSettings } from '@/routes/organization';
import { edit as editProfile } from '@/routes/profile';
import { index as rolesIndex } from '@/routes/roles';
import { index as trashIndex } from '@/routes/trash';
import { index as usersIndex } from '@/routes/users';
import type { SharedData } from '@/types';

type Props = {
    onOpenPayment?: () => void;
};

export function AppSidebar({ onOpenPayment }: Props) {
    const { url, props } = usePage<SharedData>();
    const { auth } = props;
    const isSuperAdmin = auth?.isSuperAdmin ?? false;
    const user = auth?.user;
    const organization = auth?.organization;
    const { can } = usePermissions();

    const isItemActive = (href: unknown) => {
        try {
            const raw =
                typeof href === 'string'
                    ? href
                    : ((href as { url?: string })?.url ?? String(href));
            const itemPath = new URL(raw, 'http://localhost').pathname;
            if (
                itemPath === '/' ||
                itemPath === '/dashboard' ||
                itemPath === '/admin/dashboard'
            ) {
                return (
                    url === itemPath ||
                    (itemPath === '/dashboard' && url === '/')
                );
            }
            return url.startsWith(itemPath);
        } catch {
            return false;
        }
    };

    const homeHref = isSuperAdmin ? adminDashboard() : dashboard();

    // User initials
    const initials = user?.name
        ? user.name
              .split(' ')
              .map((n) => n[0])
              .slice(0, 2)
              .join('')
              .toUpperCase()
        : 'A';

    // Organization navigation items with permission guards
    const orgItems = [
        { title: 'Accueil', href: dashboard(), icon: Home },
        {
            title: 'Alumni',
            href: alumniIndex(),
            icon: GraduationCap,
            permission: 'alumni.view',
        },
        {
            title: 'Événements',
            href: eventsIndex(),
            icon: CalendarDays,
            permission: 'events.view',
        },
        {
            title: 'Campagnes',
            href: campaignsIndex(),
            icon: Mail,
            permission: 'campaigns.view',
        },
        {
            title: 'Assistant IA',
            href: assistantIndex(),
            icon: Sparkles,
            permission: 'assistant.use',
        },
        {
            title: 'Utilisateurs',
            href: usersIndex(),
            icon: UsersRound,
            permission: 'users.view',
        },
        {
            title: 'Rôles & Droits',
            href: rolesIndex(),
            icon: ShieldCheck,
            permission: 'roles.view',
        },
        {
            title: 'Organisation',
            href: organizationSettings(),
            icon: Building2,
            permission: 'organization.update',
        },
        { title: 'Corbeille', href: trashIndex(), icon: Trash2 },
    ].filter((item) => !item.permission || can(item.permission));

    // SuperAdmin navigation items
    // Platform navigation: the Super Administrator role decides which links appear.
    // The dashboard and the roles screen always stay, so the role can be repaired.
    const adminItems = [
        { title: 'Tableau de bord', href: adminDashboard(), icon: Home },
        {
            title: 'Organisations',
            href: adminOrganizations(),
            icon: Building2,
            permission: 'platform.organizations',
        },
        {
            title: 'Plans & Tarifs',
            href: adminPlans(),
            icon: Layers,
            permission: 'platform.plans',
        },
        {
            title: 'Abonnements',
            href: adminSubscriptions(),
            icon: CreditCard,
            permission: 'platform.subscriptions',
        },
        { title: 'Rôles & Droits', href: adminRoles(), icon: ShieldCheck },
    ].filter(
        (item) =>
            !item.permission ||
            (auth?.permissions ?? []).includes(item.permission),
    );

    const currentItems = isSuperAdmin ? adminItems : orgItems;

    return (
        <SidebarFrame>
            {/* =========================================================================
                BULLE 1: Capsule principale (Navigation & Logo) en NOIR OUVERT
               ========================================================================= */}
            <div className="relative flex w-full flex-col overflow-visible rounded-[38px] border border-white/12 bg-gradient-to-b from-[#0a0c10]/98 via-[#10131a]/96 to-[#07080b]/98 p-2.5 shadow-2xl ring-1 shadow-black/60 ring-white/5 backdrop-blur-2xl sm:p-3">
                {/* Dark Glass Lighting Highlights */}
                <div className="pointer-events-none absolute -top-12 -left-12 size-32 rounded-full bg-cyan-500/10 blur-2xl" />
                <div className="pointer-events-none absolute -right-12 -bottom-12 size-32 rounded-full bg-blue-500/10 blur-2xl" />

                {/* Top Header: Logo */}
                <div className="relative z-10 flex items-center gap-2.5 border-b border-white/10 px-1 pt-1 pb-2">
                    <Link
                        href={homeHref}
                        className="group flex min-w-0 cursor-pointer items-center gap-2.5"
                        title="Aeterna"
                    >
                        {/* Glass Bubble with Aeterna Logo */}
                        <div className="droplet-item droplet-circle flex size-11 shrink-0 items-center justify-center rounded-full border border-white/25 bg-gradient-to-tr from-white/15 via-white/10 to-white/20 text-white shadow-lg shadow-black/40 backdrop-blur-md">
                            <AppLogoIcon className="size-6 text-white drop-shadow-sm" />
                        </div>

                        <div className="flex min-w-0 flex-col pr-1">
                            <span className="truncate text-sm leading-tight font-normal tracking-normal text-white uppercase">
                                Aeterna
                            </span>
                            <span className="text-[10px] font-light tracking-normal text-slate-400 uppercase opacity-90">
                                Alumni SaaS
                            </span>
                        </div>
                    </Link>
                </div>

                {/* Navigation Items with Perfect Superposition & Alignment */}
                <nav className="no-scrollbar relative z-10 mt-2.5 flex max-h-[calc(100vh-17rem)] flex-col gap-1.5 overflow-y-auto">
                    {currentItems.map((item) => {
                        const active = isItemActive(item.href);
                        const Icon = item.icon;

                        if (active) {
                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    prefetch
                                    className="liquid-active-pill group relative flex w-full items-center gap-3 px-3.5 py-2.5 text-xs transition-all duration-200"
                                >
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-black/10 text-black">
                                        <Icon className="size-4.5" />
                                    </div>
                                    <span className="flex-1 truncate font-semibold tracking-normal text-black">
                                        {item.title}
                                    </span>
                                    <span className="mr-1 size-1.5 shrink-0 rounded-full bg-black/40" />
                                </Link>
                            );
                        }

                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                prefetch
                                className="droplet-bubble-stretch group relative flex w-full items-center gap-3 rounded-full border border-white/5 bg-white/5 px-3.5 py-2.5 text-xs font-normal text-white/80 transition-[transform,background-color,border-color,box-shadow,color] duration-150 ease-out will-change-transform hover:border-white/15 hover:bg-white/12 hover:text-white active:scale-[0.97]"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-white/90 transition-colors group-hover:bg-white/20 group-hover:text-white">
                                    <Icon className="size-4.5 transition-transform duration-150 group-hover:scale-110" />
                                </div>
                                <span className="flex-1 truncate font-normal tracking-normal text-white/85 group-hover:text-white">
                                    {item.title}
                                </span>
                            </Link>
                        );
                    })}

                    {/* Interactive Wallet / Payment Trigger */}
                    {onOpenPayment && (
                        <button
                            type="button"
                            onClick={onOpenPayment}
                            className="droplet-bubble-stretch group relative mt-1 flex w-full cursor-pointer items-center gap-3 rounded-full border border-emerald-400/25 bg-emerald-500/15 px-3.5 py-2.5 text-xs font-normal text-emerald-300 transition-[transform,background-color,border-color,box-shadow,color] duration-150 ease-out will-change-transform hover:bg-emerald-500/25 hover:text-white active:scale-[0.97]"
                        >
                            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/25 text-emerald-300 transition-colors group-hover:bg-emerald-500/40">
                                <Wallet className="size-4.5 transition-transform duration-150 group-hover:scale-110" />
                            </div>
                            <span className="flex-1 truncate text-left tracking-normal">
                                Paiements &amp; Cartes
                            </span>
                            <span className="mr-1 size-2 shrink-0 animate-pulse rounded-full bg-emerald-400" />
                        </button>
                    )}
                </nav>
            </div>

            {/* =========================================================================
                BULLE 2: Mini capsule inférieure (Profil, Paramètres, Logout) en NOIR
               ========================================================================= */}
            <div className="relative mt-auto flex w-full flex-col gap-2 overflow-hidden rounded-[32px] border border-white/12 bg-gradient-to-b from-[#0a0c10]/98 via-[#10131a]/96 to-[#07080b]/98 p-2 shadow-xl ring-1 shadow-black/50 ring-white/5 backdrop-blur-2xl sm:p-2.5">
                {/* Expanded User Profile Header */}
                <Link
                    href={editProfile()}
                    className="droplet-item droplet-bubble-stretch group flex items-center gap-2.5 rounded-2xl border border-white/5 bg-white/5 p-1.5 transition-[transform,background-color,border-color] duration-150 ease-out will-change-transform hover:bg-white/10 active:scale-[0.98]"
                    title="Voir mon profil"
                >
                    <div className="relative size-10 shrink-0 rounded-full">
                        <img
                            src={user?.avatar || '/images/avatars/admin.jpg'}
                            alt={user?.name || 'Mon Profil'}
                            className="size-full rounded-full border border-white/25 object-cover shadow-md"
                        />
                        <span className="absolute -right-0.5 -bottom-0.5 flex h-4 min-w-[18px] items-center justify-center rounded-full border border-white/40 bg-black/90 px-1 text-[9px] leading-none font-bold tracking-tight text-white shadow-sm">
                            {initials}
                        </span>
                    </div>
                    <div className="flex min-w-0 flex-col pr-1">
                        <span className="truncate text-xs font-normal tracking-normal text-white transition-colors group-hover:text-cyan-200">
                            {user?.name || 'Mon Profil'}
                        </span>
                        <span className="truncate text-[10px] font-light tracking-normal text-slate-400">
                            {isSuperAdmin
                                ? 'Super Administrateur'
                                : organization?.name || 'Administrateur'}
                        </span>
                    </div>
                </Link>

                {/* Expanded Actions Row */}
                <div className="grid grid-cols-3 gap-1 border-t border-white/10 pt-1">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Link
                                href={editProfile()}
                                className="droplet-item droplet-bubble-stretch flex items-center justify-center rounded-xl bg-white/5 py-2 text-xs text-white/70 transition-[transform,background-color,color] duration-150 ease-out will-change-transform hover:bg-white/15 hover:text-white active:scale-[0.95]"
                                title="Paramètres"
                            >
                                <Settings className="size-4" />
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent
                            side="top"
                            className="rounded-xl border-white/20 bg-[#0b0c10] px-2.5 py-1 text-xs text-white"
                        >
                            Paramètres
                        </TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                onClick={onOpenPayment}
                                className="droplet-item droplet-bubble-stretch flex cursor-pointer items-center justify-center rounded-xl bg-white/5 py-2 text-xs text-white/70 transition-[transform,background-color,color] duration-150 ease-out will-change-transform hover:bg-white/15 hover:text-white active:scale-[0.95]"
                                title="Aide & Support"
                            >
                                <LifeBuoy className="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent
                            side="top"
                            className="rounded-xl border-white/20 bg-[#0b0c10] px-2.5 py-1 text-xs text-white"
                        >
                            Aide &amp; Support
                        </TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Link
                                href={logout()}
                                method="post"
                                as="button"
                                className="droplet-item droplet-bubble-stretch flex cursor-pointer items-center justify-center rounded-xl bg-rose-500/10 py-2 text-xs text-rose-300 transition-[transform,background-color,color] duration-150 ease-out will-change-transform hover:bg-rose-500/25 hover:text-rose-100 active:scale-[0.95]"
                                title="Déconnexion"
                            >
                                <LogOut className="size-4" />
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent
                            side="top"
                            className="rounded-xl border-white/20 bg-[#0b0c10] px-2.5 py-1 text-xs text-rose-300"
                        >
                            Déconnexion
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </SidebarFrame>
    );
}
