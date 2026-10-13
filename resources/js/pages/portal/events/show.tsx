import { Head } from '@inertiajs/react';
import { CalendarDays, MapPin, Users } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { index as eventsIndex } from '@/routes/portal/events';
import {
    formatEventDate,
    RegistrationButton,
    seatsLabel,
    type PortalEvent,
} from './index';

type Props = {
    event: PortalEvent & {
        description: string | null;
        ends_at: string | null;
    };
};

export default function PortalEventShow({ event }: Props) {
    return (
        <>
            <Head title={event.title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={event.title}
                    description={formatEventDate(event.starts_at)}
                />

                <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
                    <section className="border-border bg-surface rounded-lg border p-6">
                        <h2 className="text-text mb-3 text-base font-semibold">
                            A propos
                        </h2>
                        {event.description ? (
                            <p className="text-text-secondary text-sm leading-relaxed whitespace-pre-line">
                                {event.description}
                            </p>
                        ) : (
                            <p className="text-text-muted text-sm">
                                Aucune description n a ete fournie pour cet
                                evenement.
                            </p>
                        )}
                    </section>

                    <aside className="border-border bg-surface flex flex-col gap-4 rounded-lg border p-6">
                        <div className="text-text-secondary flex flex-col gap-3 text-sm">
                            <span className="flex items-start gap-2">
                                <CalendarDays className="text-text-muted mt-0.5 size-4 shrink-0" />
                                <span>
                                    {formatEventDate(event.starts_at)}
                                    {event.ends_at && (
                                        <>
                                            <br />
                                            <span className="text-text-muted text-xs">
                                                Jusqu au{' '}
                                                {formatEventDate(event.ends_at)}
                                            </span>
                                        </>
                                    )}
                                </span>
                            </span>

                            {event.location && (
                                <span className="flex items-start gap-2">
                                    <MapPin className="text-text-muted mt-0.5 size-4 shrink-0" />
                                    {event.location}
                                </span>
                            )}

                            <span className="flex items-start gap-2">
                                <Users className="text-text-muted mt-0.5 size-4 shrink-0" />
                                {seatsLabel(event)}
                            </span>
                        </div>

                        <div className="border-border border-t pt-4">
                            <RegistrationButton event={event} />
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

PortalEventShow.layout = {
    breadcrumbs: [{ title: 'Evenements', href: eventsIndex() }],
};
