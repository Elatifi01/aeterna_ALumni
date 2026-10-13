import { useState, useEffect } from 'react';
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

    // Dynamic scroll stretch physics
    const [scrollStretch, setScrollStretch] = useState<number>(1);
    const [stretchOrigin, setStretchOrigin] = useState<'top' | 'bottom'>('top');

    useEffect(() => {
        let lastScrollY = window.scrollY;
        let ticking = false;
        let resetTimer: NodeJS.Timeout | null = null;

        const handleScroll = () => {
            const currentScrollY = window.scrollY;
            const delta = currentScrollY - lastScrollY;
            lastScrollY = currentScrollY;

            if (!ticking) {
                window.requestAnimationFrame(() => {
                    const velocity = Math.min(Math.abs(delta), 120);
                    if (velocity > 3) {
                        const stretchFactor = 1 + (velocity / 120) * 0.045; // elastic stretch up to ~1.045
                        setStretchOrigin(delta > 0 ? 'top' : 'bottom');
                        setScrollStretch(stretchFactor);

                        if (resetTimer) clearTimeout(resetTimer);
                        resetTimer = setTimeout(() => {
                            setScrollStretch(1);
                        }, 180);
                    }
                    ticking = false;
                });
                ticking = true;
            }
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => {
            window.removeEventListener('scroll', handleScroll);
            if (resetTimer) clearTimeout(resetTimer);
        };
    }, []);

    const isItemActive = (href: unknown) => {
        try {
            const raw = typeof href === 'string' ? href : ((href as { url?: string })?.url ?? String(href));
            const itemPath = new URL(raw, 'http://localhost').pathname;
            if (itemPath === '/' || itemPath === '/dashboard' || itemPath === '/admin/dashboard') {
                return url === itemPath || (itemPath === '/dashboard' && url === '/');
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
        { title: 'Alumni', href: alumniIndex(), icon: GraduationCap, permission: 'alumni.view' },
        { title: 'Événements', href: eventsIndex(), icon: CalendarDays, permission: 'events.view' },
        { title: 'Campagnes', href: campaignsIndex(), icon: Mail, permission: 'campaigns.view' },
        { title: 'Assistant IA', href: assistantIndex(), icon: Sparkles, permission: 'assistant.use' },
        { title: 'Utilisateurs', href: usersIndex(), icon: UsersRound, permission: 'users.view' },
        { title: 'Rôles & Droits', href: rolesIndex(), icon: ShieldCheck, permission: 'roles.view' },
        { title: 'Organisation', href: organizationSettings(), icon: Building2, permission: 'organization.update' },
        { title: 'Corbeille', href: trashIndex(), icon: Trash2 },
    ].filter((item) => !item.permission || can(item.permission));

    // SuperAdmin navigation items
    const adminItems = [
        { title: 'Tableau de bord', href: adminDashboard(), icon: Home },
        { title: 'Organisations', href: adminOrganizations(), icon: Building2 },
        { title: 'Plans & Tarifs', href: adminPlans(), icon: Layers },
        { title: 'Abonnements', href: adminSubscriptions(), icon: CreditCard },
    ];

    const currentItems = isSuperAdmin ? adminItems : orgItems;

    return (
        <aside
            style={{
                transform: `scaleY(${scrollStretch})`,
                transformOrigin: stretchOrigin === 'top' ? 'top center' : 'bottom center',
                transition:
                    scrollStretch === 1
                        ? 'transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1)'
                        : 'transform 0.08s ease-out',
            }}
            className="w-full md:w-[230px] lg:w-[245px] shrink-0 sticky top-4 self-start h-[calc(100vh-2rem)] z-40 flex flex-col justify-between gap-3 select-none font-sans"
        >
            {/* =========================================================================
                BULLE 1: Capsule principale (Navigation & Logo) en NOIR OUVERT
               ========================================================================= */}
            <div className="w-full bg-gradient-to-b from-[#0a0c10]/98 via-[#10131a]/96 to-[#07080b]/98 backdrop-blur-2xl rounded-[38px] border border-white/12 shadow-2xl shadow-black/60 p-2.5 sm:p-3 flex flex-col relative overflow-visible ring-1 ring-white/5">
                {/* Dark Glass Lighting Highlights */}
                <div className="absolute -top-12 -left-12 size-32 rounded-full bg-cyan-500/10 blur-2xl pointer-events-none" />
                <div className="absolute -bottom-12 -right-12 size-32 rounded-full bg-blue-500/10 blur-2xl pointer-events-none" />

                {/* Top Header: Logo */}
                <div className="flex items-center gap-2.5 px-1 pt-1 pb-2 relative z-10 border-b border-white/10">
                    <Link
                        href={homeHref}
                        className="flex items-center gap-2.5 group cursor-pointer min-w-0"
                        title="Aeterna"
                    >
                        {/* Glass Bubble with Aeterna Logo */}
                        <div className="droplet-item droplet-circle size-11 rounded-full bg-gradient-to-tr from-white/15 via-white/10 to-white/20 backdrop-blur-md border border-white/25 flex items-center justify-center text-white shadow-lg shadow-black/40 shrink-0">
                            <AppLogoIcon className="size-6 text-white drop-shadow-sm" />
                        </div>

                        <div className="flex flex-col min-w-0 pr-1">
                            <span className="text-white font-normal text-sm uppercase leading-tight truncate tracking-normal">
                                Aeterna
                            </span>
                            <span className="text-slate-400 text-[10px] font-light uppercase opacity-90 tracking-normal">
                                Alumni SaaS
                            </span>
                        </div>
                    </Link>
                </div>

                {/* Navigation Items with Perfect Superposition & Alignment */}
                <nav className="flex flex-col gap-1.5 mt-2.5 no-scrollbar overflow-y-auto max-h-[calc(100vh-17rem)] relative z-10">
                    {currentItems.map((item) => {
                        const active = isItemActive(item.href);
                        const Icon = item.icon;

                        if (active) {
                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    prefetch
                                    className="liquid-active-pill group relative w-full flex items-center gap-3 px-3.5 py-2.5 text-xs transition-all duration-200"
                                >
                                    <div className="size-8 rounded-full bg-black/10 text-black flex items-center justify-center shrink-0">
                                        <Icon className="size-4.5" />
                                    </div>
                                    <span className="truncate tracking-normal font-semibold text-black flex-1">
                                        {item.title}
                                    </span>
                                    <span className="size-1.5 rounded-full bg-black/40 mr-1 shrink-0" />
                                </Link>
                            );
                        }

                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                prefetch
                                className="droplet-bubble-stretch group relative w-full flex items-center gap-3 px-3.5 py-2.5 rounded-full text-xs font-normal text-white/80 hover:text-white bg-white/5 hover:bg-white/12 border border-white/5 hover:border-white/15 transition-[transform,background-color,border-color,box-shadow,color] duration-150 ease-out active:scale-[0.97] will-change-transform"
                            >
                                <div className="size-8 rounded-full bg-white/10 group-hover:bg-white/20 text-white/90 group-hover:text-white flex items-center justify-center shrink-0 transition-colors">
                                    <Icon className="size-4.5 transition-transform duration-150 group-hover:scale-110" />
                                </div>
                                <span className="truncate tracking-normal font-normal text-white/85 group-hover:text-white flex-1">
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
                            className="droplet-bubble-stretch group relative w-full flex items-center gap-3 px-3.5 py-2.5 rounded-full text-xs font-normal text-emerald-300 hover:text-white bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-400/25 transition-[transform,background-color,border-color,box-shadow,color] duration-150 ease-out active:scale-[0.97] will-change-transform cursor-pointer mt-1"
                        >
                            <div className="size-8 rounded-full bg-emerald-500/25 group-hover:bg-emerald-500/40 flex items-center justify-center text-emerald-300 shrink-0 transition-colors">
                                <Wallet className="size-4.5 transition-transform duration-150 group-hover:scale-110" />
                            </div>
                            <span className="truncate tracking-normal text-left flex-1">
                                Paiements &amp; Cartes
                            </span>
                            <span className="size-2 rounded-full bg-emerald-400 animate-pulse mr-1 shrink-0" />
                        </button>
                    )}
                </nav>
            </div>

            {/* =========================================================================
                BULLE 2: Mini capsule inférieure (Profil, Paramètres, Logout) en NOIR
               ========================================================================= */}
            <div className="w-full mt-auto bg-gradient-to-b from-[#0a0c10]/98 via-[#10131a]/96 to-[#07080b]/98 backdrop-blur-2xl rounded-[32px] border border-white/12 shadow-xl shadow-black/50 p-2 sm:p-2.5 flex flex-col gap-2 relative overflow-hidden ring-1 ring-white/5">
                {/* Expanded User Profile Header */}
                <Link
                    href={editProfile()}
                    className="droplet-item droplet-bubble-stretch flex items-center gap-2.5 p-1.5 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/5 transition-[transform,background-color,border-color] duration-150 ease-out active:scale-[0.98] group will-change-transform"
                    title="Voir mon profil"
                >
                    <div className="relative size-10 rounded-full shrink-0">
                        <img
                            src={user?.avatar || '/images/avatars/admin.jpg'}
                            alt={user?.name || 'Mon Profil'}
                            className="size-full rounded-full object-cover border border-white/25 shadow-md"
                        />
                        <span className="absolute -bottom-0.5 -right-0.5 px-1 min-w-[18px] h-4 rounded-full bg-black/90 border border-white/40 text-[9px] font-bold text-white flex items-center justify-center leading-none shadow-sm tracking-tight">
                            {initials}
                        </span>
                    </div>
                    <div className="flex flex-col min-w-0 pr-1">
                        <span className="text-xs font-normal text-white truncate tracking-normal group-hover:text-cyan-200 transition-colors">
                            {user?.name || 'Mon Profil'}
                        </span>
                        <span className="text-[10px] font-light text-slate-400 truncate tracking-normal">
                            {isSuperAdmin
                                ? 'Super Administrateur'
                                : organization?.name || 'Administrateur'}
                        </span>
                    </div>
                </Link>

                {/* Expanded Actions Row */}
                <div className="grid grid-cols-3 gap-1 pt-1 border-t border-white/10">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Link
                                href={editProfile()}
                                className="droplet-item droplet-bubble-stretch flex items-center justify-center py-2 rounded-xl text-white/70 hover:text-white bg-white/5 hover:bg-white/15 transition-[transform,background-color,color] duration-150 ease-out active:scale-[0.95] text-xs will-change-transform"
                                title="Paramètres"
                            >
                                <Settings className="size-4" />
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent
                            side="top"
                            className="bg-[#0b0c10] text-white border-white/20 text-xs rounded-xl px-2.5 py-1"
                        >
                            Paramètres
                        </TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                onClick={onOpenPayment}
                                className="droplet-item droplet-bubble-stretch flex items-center justify-center py-2 rounded-xl text-white/70 hover:text-white bg-white/5 hover:bg-white/15 transition-[transform,background-color,color] duration-150 ease-out active:scale-[0.95] text-xs cursor-pointer will-change-transform"
                                title="Aide & Support"
                            >
                                <LifeBuoy className="size-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent
                            side="top"
                            className="bg-[#0b0c10] text-white border-white/20 text-xs rounded-xl px-2.5 py-1"
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
                                className="droplet-item droplet-bubble-stretch flex items-center justify-center py-2 rounded-xl text-rose-300 hover:text-rose-100 bg-rose-500/10 hover:bg-rose-500/25 transition-[transform,background-color,color] duration-150 ease-out active:scale-[0.95] text-xs cursor-pointer will-change-transform"
                                title="Déconnexion"
                            >
                                <LogOut className="size-4" />
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent
                            side="top"
                            className="bg-[#0b0c10] text-rose-300 border-white/20 text-xs rounded-xl px-2.5 py-1"
                        >
                            Déconnexion
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </aside>
    );
}
