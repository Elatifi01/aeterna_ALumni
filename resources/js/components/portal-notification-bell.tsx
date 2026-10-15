import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CalendarCheck, CalendarDays, Mail } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { show as eventShow } from '@/routes/portal/events';
import { show as messageShow } from '@/routes/portal/messages';
import {
    index as notificationsIndex,
    read,
    readAll,
} from '@/routes/portal/notifications';
import type { PortalNotification, SharedData } from '@/types';

const icons: Record<PortalNotification['kind'], LucideIcon> = {
    campaign: Mail,
    event: CalendarDays,
    registration: CalendarCheck,
    other: Bell,
};

function target(notification: PortalNotification): string | null {
    if (notification.campaign_id !== null) {
        return messageShow(notification.campaign_id).url;
    }

    if (notification.event_id !== null) {
        return eventShow(notification.event_id).url;
    }

    return null;
}

function ago(value: string | null): string {
    if (value === null) {
        return '';
    }

    const minutes = Math.round(
        (Date.now() - new Date(value).getTime()) / 60000,
    );

    if (minutes < 1) {
        return 'a l instant';
    }

    if (minutes < 60) {
        return `il y a ${minutes} min`;
    }

    if (minutes < 60 * 24) {
        return `il y a ${Math.round(minutes / 60)} h`;
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
    });
}

/**
 * The portal's notifications, opened from the bell in the header.
 */
export function PortalNotificationBell() {
    const { auth } = usePage<SharedData>().props;
    const alumni = auth?.alumni;

    if (!alumni?.permissions.includes('portal.notifications.view')) {
        return null;
    }

    const unread = alumni.unread_notifications;
    const recent = alumni.recent_notifications;

    const open = (notification: PortalNotification) => {
        const href = target(notification);

        if (notification.read) {
            if (href !== null) {
                router.visit(href);
            }

            return;
        }

        router.post(
            read(notification.id).url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (href !== null) {
                        router.visit(href);
                    }
                },
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={
                        unread > 0
                            ? `Notifications, ${unread} non lue${unread > 1 ? 's' : ''}`
                            : 'Notifications'
                    }
                    className="relative flex size-10 cursor-pointer items-center justify-center rounded-full border border-slate-200/70 bg-white text-slate-600 shadow-2xs transition-[color,transform] duration-150 ease-out hover:text-slate-900 active:scale-[0.97] dark:border-white/10 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-white"
                >
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <span className="tabular absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] leading-none font-semibold text-white ring-2 ring-white dark:ring-slate-900">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-[min(22rem,calc(100vw-2rem))] origin-(--radix-dropdown-menu-content-transform-origin) p-0"
            >
                <div className="flex items-center justify-between gap-3 border-b border-slate-200/70 px-4 py-3 dark:border-white/10">
                    <p className="text-sm font-semibold text-slate-900 dark:text-white">
                        Notifications
                    </p>
                    {unread > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                )
                            }
                            className="cursor-pointer text-xs font-medium text-blue-600 transition-colors duration-150 ease-out hover:text-blue-700 dark:text-blue-400"
                        >
                            Tout marquer comme lu
                        </button>
                    )}
                </div>

                {recent.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
                        <Bell className="size-5 text-slate-300" />
                        <p className="text-xs text-slate-500">
                            Aucune notification pour le moment.
                        </p>
                    </div>
                ) : (
                    <div className="max-h-[22rem] overflow-y-auto p-1.5">
                        {recent.map((notification) => {
                            const Icon = icons[notification.kind] ?? Bell;

                            return (
                                <DropdownMenuItem
                                    key={notification.id}
                                    onSelect={() => open(notification)}
                                    className="flex cursor-pointer items-start gap-3 rounded-xl px-2.5 py-2.5"
                                >
                                    <span
                                        className={
                                            notification.read
                                                ? 'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-white/5'
                                                : 'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400'
                                        }
                                    >
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="grid min-w-0 flex-1 gap-0.5">
                                        <span
                                            className={
                                                notification.read
                                                    ? 'truncate text-sm text-slate-600 dark:text-slate-300'
                                                    : 'truncate text-sm font-semibold text-slate-900 dark:text-white'
                                            }
                                        >
                                            {notification.title}
                                        </span>
                                        <span className="line-clamp-2 text-xs text-slate-500">
                                            {notification.body}
                                        </span>
                                        <span className="text-[11px] text-slate-400">
                                            {ago(notification.created_at)}
                                        </span>
                                    </span>
                                    {!notification.read && (
                                        <span
                                            aria-hidden
                                            className="mt-2 size-2 shrink-0 rounded-full bg-blue-600"
                                        />
                                    )}
                                </DropdownMenuItem>
                            );
                        })}
                    </div>
                )}

                <div className="border-t border-slate-200/70 p-1.5 dark:border-white/10">
                    <DropdownMenuItem
                        asChild
                        className="cursor-pointer justify-center rounded-xl py-2 text-xs font-medium"
                    >
                        <Link href={notificationsIndex()}>
                            Voir toutes les notifications
                        </Link>
                    </DropdownMenuItem>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
