import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, CalendarDays, UserCheck } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import {
    index as eventsIndex,
    show as eventShow,
} from '@/routes/portal/events';

type NextEvent = {
    id: number;
    title: string;
    location: string | null;
    starts_at: string | null;
};

type Props = {
    alumni: { first_name: string; full_name: string };
    stats: { upcoming: number; registered: number; attended: number };
    nextEvents: NextEvent[];
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function PortalDashboard({ alumni, stats, nextEvents }: Props) {
    return (
        <>
            <Head title="Tableau de bord" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={`Bonjour ${alumni.first_name}`}
                    description="Retrouvez ici les evenements de votre etablissement"
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard
                        label="Evenements a venir"
                        value={stats.upcoming}
                        icon={CalendarDays}
                        hint="Evenements publies dont la date n est pas passee."
                    />
                    <StatCard
                        label="Mes inscriptions"
                        value={stats.registered}
                        icon={CalendarCheck}
                        hint="Evenements a venir auxquels vous etes inscrit."
                    />
                    <StatCard
                        label="Ma participation"
                        value={stats.attended}
                        icon={UserCheck}
                        hint="Evenements auxquels votre presence a ete enregistree."
                    />
                </div>

                <section className="border-border bg-surface rounded-lg border">
                    <header className="border-border flex items-center justify-between border-b px-5 py-4">
                        <h2 className="text-text text-base font-semibold">
                            Prochains evenements
                        </h2>
                        <Link
                            href={eventsIndex()}
                            className="text-accent text-sm hover:underline"
                        >
                            Tout voir
                        </Link>
                    </header>

                    {nextEvents.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title="Aucun evenement a venir"
                            description="Votre etablissement n a pas encore publie de prochain evenement."
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {nextEvents.map((event) => (
                                <li key={event.id}>
                                    <Link
                                        href={eventShow(event.id)}
                                        className="hover:bg-surface-muted flex flex-col gap-1 px-5 py-4 transition"
                                    >
                                        <span className="text-text text-sm font-medium">
                                            {event.title}
                                        </span>
                                        <span className="text-text-muted text-xs">
                                            {formatDate(event.starts_at)}
                                            {event.location
                                                ? ` — ${event.location}`
                                                : ''}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
