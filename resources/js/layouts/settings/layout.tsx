import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: edit(),
        icon: null,
    },
    {
        title: 'Sécurité',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Apparence',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="py-2">
            <Heading
                title="Paramètres"
                description="Gérez votre profil et les paramètres de votre compte"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-8 mt-6">
                <aside className="w-full lg:w-56 shrink-0 mb-6 lg:mb-0">
                    <nav
                        className="flex flex-row lg:flex-col gap-1.5 p-1.5 bg-slate-200/50 dark:bg-slate-800/50 rounded-2xl"
                        aria-label="Paramètres"
                    >
                        {sidebarNavItems.map((item, index) => {
                            const active = isCurrentOrParentUrl(item.href);
                            return (
                                <Link
                                    key={`${toUrl(item.href)}-${index}`}
                                    href={item.href}
                                    className={cn(
                                        'px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all text-left flex items-center',
                                        active
                                            ? 'bg-white dark:bg-slate-900 text-[#1c1e22] dark:text-white shadow-xs'
                                            : 'text-slate-500 hover:text-slate-900 dark:hover:text-white hover:bg-white/50'
                                    )}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="flex-1 min-w-0">
                    <div className="bg-white dark:bg-slate-800/80 rounded-[28px] border border-slate-200/70 dark:border-white/10 p-6 sm:p-8 shadow-xs">
                        <section className="max-w-2xl space-y-10">
                            {children}
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
}
