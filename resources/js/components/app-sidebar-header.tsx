import { Link, usePage } from '@inertiajs/react';
import { Bell, Search, Wallet } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
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
        <header className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200/60 dark:border-white/10 shrink-0">
            <div>
                <div className="text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
                    {parentCategory}
                </div>
                <div className="flex items-center gap-3 mt-0.5">
                    <h1 className="text-2xl font-black text-[#1c1e22] dark:text-white tracking-tight">
                        {currentTitle}
                    </h1>
                    {breadcrumbs.length > 1 && (
                        <div className="hidden lg:flex items-center text-xs text-slate-400 gap-1.5 ml-2 font-medium">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    )}
                </div>
            </div>

            <div className="flex items-center gap-3">
                {/* Search Bar */}
                <div className="flex items-center gap-2 bg-white dark:bg-slate-800 rounded-full pl-3.5 pr-4 py-2 border border-slate-200/70 dark:border-white/10 shadow-2xs text-sm">
                    <Search className="size-4 text-slate-400" />
                    <input
                        type="text"
                        placeholder="Rechercher..."
                        className="bg-transparent border-none outline-none text-xs text-slate-700 dark:text-slate-200 placeholder:text-slate-400 w-24 sm:w-32 focus:w-44 transition-all"
                    />
                </div>

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

                {/* Notification Bell */}
                <button
                    type="button"
                    className="relative size-10 rounded-full bg-white dark:bg-slate-800 shadow-2xs border border-slate-200/70 dark:border-white/10 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer"
                >
                    <Bell className="size-4" />
                    <span className="absolute top-2.5 right-2.5 size-2 rounded-full bg-blue-600 ring-2 ring-white"></span>
                </button>

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
