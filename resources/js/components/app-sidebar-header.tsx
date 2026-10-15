import { Link, usePage } from '@inertiajs/react';
import { Menu, Wallet } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { PortalNotificationBell } from '@/components/portal-notification-bell';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { mobileNav } from '@/hooks/use-mobile-nav';
import type { BreadcrumbItem as BreadcrumbItemType, SharedData } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItemType[];
    onOpenPayment?: () => void;
};

export function AppSidebarHeader({
    breadcrumbs = [],
    onOpenPayment,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const user = auth?.user;
    const currentTitle =
        breadcrumbs.length > 0
            ? breadcrumbs[breadcrumbs.length - 1].title
            : 'Tableau de bord';
    const parentCategory =
        breadcrumbs.length > 1 ? breadcrumbs[0].title : 'Aeterna SaaS';

    return (
        <header className="flex items-center justify-between gap-3 pb-4 border-b border-slate-200/60 dark:border-white/10 shrink-0">
            <div className="flex min-w-0 items-center gap-3">
                {/* Below lg the sidebar is a drawer: this is the only way in */}
                <button
                    type="button"
                    onClick={mobileNav.open}
                    aria-label="Ouvrir le menu"
                    className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-[#0a0c10] text-white shadow-md shadow-black/20 transition-transform duration-150 ease-out active:scale-[0.97] lg:hidden"
                >
                    <Menu className="size-5" />
                </button>
                <div className="min-w-0">
                    <div className="truncate text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                        {parentCategory}
                    </div>
                    <div className="flex items-center gap-3 mt-0.5">
                        <h1 className="truncate text-xl sm:text-2xl font-black text-[#1c1e22] dark:text-white tracking-tight">
                            {currentTitle}
                        </h1>
                        {breadcrumbs.length > 1 && (
                            <div className="hidden lg:flex items-center text-xs text-slate-400 gap-1.5 ml-2 font-medium">
                                <Breadcrumbs breadcrumbs={breadcrumbs} />
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                {/* Quick Payment Button */}
                {onOpenPayment && (
                    <button
                        type="button"
                        onClick={onOpenPayment}
                        className="hidden sm:inline-flex items-center gap-2 bg-white dark:bg-slate-800 hover:bg-slate-50 text-slate-800 dark:text-slate-100 text-xs font-bold px-3.5 py-2 rounded-full border border-slate-200/80 dark:border-white/10 shadow-2xs transition-colors cursor-pointer group"
                    >
                        <Wallet className="size-3.5 text-emerald-500 group-hover:scale-110 transition-transform" />
                        <span>Payer / Recharger</span>
                    </button>
                )}

                {/* Notifications: the alumni portal only, for now */}
                {auth?.alumni && <PortalNotificationBell />}

                {/* User Avatar with Dropdown */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="relative size-10 rounded-full select-none cursor-pointer ring-2 ring-transparent hover:ring-blue-400 transition-all shadow-sm shrink-0"
                            title={user?.name ?? 'Compte'}
                        >
                            <img
                                src={user?.avatar || '/images/avatars/admin.jpg'}
                                alt={user?.name ?? 'Compte'}
                                className="size-full rounded-full object-cover border border-slate-200 dark:border-white/20"
                            />
                            <span className="absolute -bottom-0.5 -right-0.5 px-1 min-w-[17px] h-4 rounded-full bg-slate-900 border border-white/40 text-[8px] font-bold text-white flex items-center justify-center leading-none shadow-xs">
                                {user?.name ? user.name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase() : 'AU'}
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-56 rounded-2xl p-1.5 shadow-xl border border-slate-200/80 dark:border-white/10 bg-white dark:bg-slate-900"
                        align="end"
                    >
                        {user ? (
                            <UserMenuContent user={user} />
                        ) : (
                            <DropdownMenuItem asChild>
                                <Link href="/login" className="w-full">
                                    Se connecter
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
