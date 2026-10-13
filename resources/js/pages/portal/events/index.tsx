import { Form, Head, Link } from '@inertiajs/react';
import { CalendarDays, MapPin, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    cancel as cancelRegistration,
    index as eventsIndex,
    register as registerForEvent,
    show as eventShow,
} from '@/routes/portal/events';
import type { Paginated } from '@/types';

export type PortalEvent = {
    id: number;
    title: string;
    location: string | null;
    starts_at: string;
    capacity: number | null;
    registrations_count: number;
    is_registered: boolean;
    attended: boolean;
    is_past: boolean;
    is_full: boolean;
};

type Props = {
    events: Paginated<PortalEvent>;
};

export function formatEventDate(value: string): string {
    return new Date(value).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function seatsLabel(event: PortalEvent): string {
    if (event.capacity === null) {
        return `${event.registrations_count} inscrit${event.registrations_count > 1 ? 's' : ''}`;
    }

    return `${event.registrations_count} / ${event.capacity} places`;
}

export function RegistrationButton({ event }: { event: PortalEvent }) {
    if (event.is_past) {
        return (
            <StatusBadge
                tone={event.attended ? 'success' : 'neutral'}
                label={event.attended ? 'Vous y etiez' : 'Termine'}
            />
        );
    }

    if (event.is_registered) {
        return (
            <Form
                {...cancelRegistration.form(event.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Annuler mon inscription
                    </Button>
                )}
            </Form>
        );
    }

    if (event.is_full) {
        return <StatusBadge tone="warning" label="Complet" />;
    }

    return (
        <Form
            {...registerForEvent.form(event.id)}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <Button type="submit" size="sm" disabled={processing}>
                    {processing && <Spinner />}
                    S inscrire
                </Button>
            )}
        </Form>
    );
}

export default function PortalEventsIndex({ events: page }: Props) {
    return (
        <>
            <Head title="Evenements" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Evenements"
                    description="Les evenements publies par votre etablissement"
                />

                {page.data.length === 0 ? (
                    <div className="border-border bg-surface rounded-lg border">
                        <EmptyState
                            icon={CalendarDays}
                            title="Aucun evenement pour le moment"
                            description="Les evenements publies par votre etablissement apparaitront ici."
                        />
                    </div>
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {page.data.map((event) => (
                            <li
                                key={event.id}
                                className="border-border bg-surface flex flex-col gap-3 rounded-lg border p-5"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <Link
                                        href={eventShow(event.id)}
                                        className="text-text text-sm font-semibold hover:underline"
                                    >
                                        {event.title}
                                    </Link>
                                    {event.is_registered && !event.is_past && (
                                        <StatusBadge
                                            tone="success"
                                            label="Inscrit"
                                        />
                                    )}
                                </div>

                                <div className="text-text-muted flex flex-col gap-1.5 text-xs">
                                    <span className="flex items-center gap-1.5">
                                        <CalendarDays className="size-3.5" />
                                        {formatEventDate(event.starts_at)}
                                    </span>
                                    {event.location && (
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="size-3.5" />
                                            {event.location}
                                        </span>
                                    )}
                                    <span className="flex items-center gap-1.5">
                                        <Users className="size-3.5" />
                                        {seatsLabel(event)}
                                    </span>
                                </div>

                                <div className="mt-auto pt-2">
                                    <RegistrationButton event={event} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

PortalEventsIndex.layout = {
    breadcrumbs: [{ title: 'Evenements', href: eventsIndex() }],
};
