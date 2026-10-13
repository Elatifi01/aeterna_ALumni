import { Form, Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    MapPin,
    Pencil,
    Percent,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import EventRegistrationController from '@/actions/App/Http/Controllers/EventRegistrationController';
import { AvatarInitials } from '@/components/avatar-initials';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { eventStatuses, StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/lib/permissions';
import { formatEventDate, type EventRow } from '@/pages/events/index';
import { index, edit, show } from '@/routes/events';

type Registration = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    status: 'registered' | 'canceled';
    attended: boolean;
};

type Candidate = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
};

type Props = {
    event: EventRow & { description: string | null };
    registrations: Registration[];
    seatsLeft: number | null;
    availableAlumni?: Candidate[];
    alumniSearch: string | null;
};

export default function EventShow({
    event,
    registrations,
    seatsLeft,
    availableAlumni,
    alumniSearch,
}: Props) {
    const { can } = usePermissions();
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [search, setSearch] = useState(alumniSearch ?? '');

    const canManage = can('events.registrations');
    const active = registrations.filter((row) => row.status === 'registered');
    const isFull = seatsLeft !== null && seatsLeft === 0;

    const searchCandidates = (event_: React.FormEvent) => {
        event_.preventDefault();

        router.get(
            show(event.id).url,
            { alumni_search: search },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['availableAlumni', 'alumniSearch'],
            },
        );
    };

    return (
        <>
            <Head title={event.title} />

            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title={event.title}
                    description={[
                        formatEventDate(event.starts_at),
                        event.location,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    action={
                        can('events.update') && (
                            <Button asChild>
                                <Link href={edit(event.id)}>
                                    <Pencil className="size-4" />
                                    Modifier
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex items-center gap-2">
                    <StatusBadge
                        tone={eventStatuses[event.status].tone}
                        label={eventStatuses[event.status].label}
                    />
                    {isFull && <StatusBadge tone="danger" label="Complet" />}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard
                        label="Inscrits"
                        value={
                            event.capacity === null
                                ? active.length
                                : `${active.length} / ${event.capacity}`
                        }
                        icon={Users}
                        hint="Inscriptions actives, hors inscriptions annulees."
                    />
                    <StatCard
                        label="Taux de participation"
                        value={
                            event.attendance_rate === null
                                ? '—'
                                : `${event.attendance_rate.toLocaleString('fr-FR')} %`
                        }
                        icon={Percent}
                        hint="Presences rapportees au nombre d inscriptions actives."
                    />
                    <StatCard
                        label="Places restantes"
                        value={seatsLeft === null ? 'Illimite' : seatsLeft}
                        icon={CalendarDays}
                        hint="Un evenement sans capacite definie accepte un nombre illimite d inscriptions."
                    />
                </div>

                {(event.description || event.location) && (
                    <section className="border-border bg-surface rounded-lg border p-5">
                        <h2 className="text-text text-base font-semibold">
                            Details
                        </h2>
                        {event.description && (
                            <p className="text-text-secondary mt-2 text-sm whitespace-pre-line">
                                {event.description}
                            </p>
                        )}
                        {event.location && (
                            <p className="text-text-secondary mt-3 flex items-center gap-2 text-sm">
                                <MapPin className="size-4" />
                                {event.location}
                            </p>
                        )}
                    </section>
                )}

                {canManage && (
                    <section className="border-border bg-surface rounded-lg border p-5">
                        <h2 className="text-text text-base font-semibold">
                            Inscrire un alumni
                        </h2>
                        <p className="text-text-secondary mt-1 text-sm">
                            {isFull
                                ? 'Cet evenement affiche complet. Annulez une inscription pour liberer une place.'
                                : 'Recherchez un alumni de votre organisation par nom, e-mail ou entreprise.'}
                        </p>

                        <form
                            onSubmit={searchCandidates}
                            className="mt-3 flex gap-2"
                        >
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Rechercher un alumni"
                                aria-label="Rechercher un alumni a inscrire"
                                className="h-10 max-w-xs"
                                disabled={isFull}
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-10"
                                disabled={isFull}
                            >
                                Rechercher
                            </Button>
                        </form>

                        {availableAlumni && availableAlumni.length > 0 && (
                            <ul className="border-border divide-border mt-3 divide-y rounded-md border">
                                {availableAlumni.map((candidate) => (
                                    <li
                                        key={candidate.id}
                                        className="flex items-center gap-3 px-3 py-2"
                                    >
                                        <AvatarInitials
                                            firstName={candidate.first_name}
                                            lastName={candidate.last_name}
                                            className="size-8"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="text-text block truncate text-sm font-medium">
                                                {candidate.first_name}{' '}
                                                {candidate.last_name}
                                            </span>
                                            <span className="text-text-muted block truncate text-xs">
                                                {candidate.email}
                                            </span>
                                        </span>

                                        <Form
                                            {...EventRegistrationController.store.form(
                                                event.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="alumni_id"
                                                        value={candidate.id}
                                                    />
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        className="h-9"
                                                        disabled={
                                                            processing || isFull
                                                        }
                                                    >
                                                        <UserPlus className="size-4" />
                                                        Inscrire
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {availableAlumni && availableAlumni.length === 0 && (
                            <p className="text-text-muted mt-3 text-sm">
                                Aucun alumni disponible ne correspond a cette
                                recherche.
                            </p>
                        )}
                    </section>
                )}

                <section className="border-border bg-surface overflow-hidden rounded-lg border">
                    <header className="border-border border-b px-5 py-4">
                        <h2 className="text-text text-base font-semibold">
                            Inscriptions
                        </h2>
                        <p className="text-text-secondary text-sm">
                            Cochez la case pour marquer un alumni present
                        </p>
                    </header>

                    {registrations.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Aucune inscription"
                            description="Inscrivez des alumni pour suivre la participation a cet evenement."
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {registrations.map((registration) => (
                                <li
                                    key={registration.id}
                                    className="flex items-center gap-3 px-5 py-3"
                                >
                                    {canManage &&
                                    registration.status === 'registered' ? (
                                        <Form
                                            {...EventRegistrationController.attendance.form(
                                                [event.id, registration.id],
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ submit }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="attended"
                                                        value={
                                                            registration.attended
                                                                ? '0'
                                                                : '1'
                                                        }
                                                    />
                                                    <Checkbox
                                                        checked={
                                                            registration.attended
                                                        }
                                                        onCheckedChange={() =>
                                                            submit()
                                                        }
                                                        aria-label={`Marquer ${registration.first_name} ${registration.last_name} ${registration.attended ? 'absent' : 'present'}`}
                                                    />
                                                </>
                                            )}
                                        </Form>
                                    ) : (
                                        <span className="size-4" />
                                    )}

                                    <AvatarInitials
                                        firstName={registration.first_name}
                                        lastName={registration.last_name}
                                        className="size-8"
                                    />

                                    <span className="min-w-0 flex-1">
                                        <span className="text-text block truncate text-sm font-medium">
                                            {registration.first_name}{' '}
                                            {registration.last_name}
                                        </span>
                                        <span className="text-text-muted block truncate text-xs">
                                            {registration.email}
                                        </span>
                                    </span>

                                    {registration.status === 'canceled' ? (
                                        <StatusBadge
                                            tone="neutral"
                                            label="Annulee"
                                        />
                                    ) : registration.attended ? (
                                        <StatusBadge
                                            tone="success"
                                            label="Present"
                                        />
                                    ) : (
                                        <StatusBadge
                                            tone="warning"
                                            label="Inscrit"
                                        />
                                    )}

                                    {canManage &&
                                        registration.status ===
                                            'registered' && (
                                            <Form
                                                {...EventRegistrationController.destroy.form(
                                                    [event.id, registration.id],
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        disabled={processing}
                                                        aria-label={`Annuler l inscription de ${registration.first_name} ${registration.last_name}`}
                                                    >
                                                        <X className="size-4" />
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {can('events.delete') && (
                    <section className="border-border bg-surface rounded-lg border p-5">
                        <h2 className="text-text text-base font-semibold">
                            Supprimer cet evenement
                        </h2>
                        <p className="text-text-secondary mt-1 max-w-prose text-sm">
                            L evenement est archive et disparait des listes. Les
                            inscriptions sont conservees.
                        </p>
                        <Button
                            variant="destructive"
                            className="mt-4"
                            onClick={() => setConfirmingDelete(true)}
                        >
                            <Trash2 className="size-4" />
                            Supprimer
                        </Button>
                    </section>
                )}
            </div>

            <Dialog open={confirmingDelete} onOpenChange={setConfirmingDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Supprimer {event.title} ?</DialogTitle>
                        <DialogDescription>
                            {active.length > 0
                                ? `Cet evenement compte ${active.length} inscription(s). Il n apparaitra plus dans vos listes.`
                                : 'Cet evenement n apparaitra plus dans vos listes.'}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmingDelete(false)}
                        >
                            Annuler
                        </Button>

                        <Form {...EventController.destroy.form(event.id)}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-delete-event"
                                >
                                    Supprimer
                                </Button>
                            )}
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

EventShow.layout = ({ event }: Props) => ({
    breadcrumbs: [
        { title: 'Evenements', href: index() },
        { title: event.title, href: show(event.id) },
    ],
});
