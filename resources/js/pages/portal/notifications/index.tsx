import { Form, Head, Link } from '@inertiajs/react';
import { Bell, CalendarCheck, CalendarDays, Mail } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { show as eventShow } from '@/routes/portal/events';
import { show as messageShow } from '@/routes/portal/messages';
import {
    index as notificationsIndex,
    readAll,
} from '@/routes/portal/notifications';
import type { Paginated } from '@/types';

type NotificationKind = 'campaign' | 'event' | 'registration' | 'other';

type PortalNotification = {
    id: string;
    kind: NotificationKind;
    title: string;
    body: string;
    read: boolean;
    created_at: string | null;
    event_id: number | null;
    campaign_id: number | null;
};

type Props = {
    notifications: Paginated<PortalNotification>;
};

const icons: Record<NotificationKind, LucideIcon> = {
    campaign: Mail,
    event: CalendarDays,
    registration: CalendarCheck,
    other: Bell,
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function target(notification: PortalNotification): string | null {
    if (notification.campaign_id !== null) {
        return messageShow(notification.campaign_id).url;
    }

    if (notification.event_id !== null) {
        return eventShow(notification.event_id).url;
    }

    return null;
}

export default function PortalNotificationsIndex({ notifications }: Props) {
    const unread = notifications.data.filter((one) => !one.read).length;

    return (
        <>
            <Head title="Notifications" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Notifications"
                    description="Ce qui s est passe dans votre etablissement"
                    action={
                        unread > 0 && (
                            <Form
                                {...readAll.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Tout marquer comme lu
                                    </Button>
                                )}
                            </Form>
                        )
                    }
                />
                <section className="border-border bg-surface rounded-lg border">
                    {notifications.data.length === 0 ? (
                        <EmptyState
                            icon={Bell}
                            title="Aucune notification"
                            description="Vous serez prevenu ici des nouveaux evenements et messages."
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {notifications.data.map((notification) => {
                                const Icon = icons[notification.kind];
                                const href = target(notification);

                                const body = (
                                    <>
                                        <span
                                            className={
                                                notification.read
                                                    ? 'text-text-muted mt-0.5'
                                                    : 'text-accent mt-0.5'
                                            }
                                        >
                                            <Icon className="size-4" />
                                        </span>
                                        <span className="grid min-w-0 flex-1 gap-0.5">
                                            <span
                                                className={
                                                    notification.read
                                                        ? 'text-text truncate text-sm'
                                                        : 'text-text truncate text-sm font-semibold'
                                                }
                                            >
                                                {notification.title}
                                            </span>
                                            <span className="text-text-muted truncate text-xs">
                                                {notification.body}
                                            </span>
                                        </span>
                                        <span className="text-text-muted shrink-0 text-xs whitespace-nowrap">
                                            {formatDate(
                                                notification.created_at,
                                            )}
                                        </span>
                                    </>
                                );

                                return (
                                    <li key={notification.id}>
                                        {href === null ? (
                                            <div className="flex items-start gap-3 px-5 py-4">
                                                {body}
                                            </div>
                                        ) : (
                                            <Link
                                                href={href}
                                                className="hover:bg-surface-muted flex items-start gap-3 px-5 py-4 transition"
                                            >
                                                {body}
                                            </Link>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

PortalNotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notifications', href: notificationsIndex() }],
};
