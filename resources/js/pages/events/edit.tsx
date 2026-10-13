import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { PageHeader } from '@/components/page-header';
import { EventForm, type EventFormValues } from '@/pages/events/event-form';
import { edit, index } from '@/routes/events';

type Props = {
    event: EventFormValues & { id: number };
};

export default function EventEdit({ event }: Props) {
    return (
        <>
            <Head title={`Modifier ${event.title}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Modifier l evenement"
                    description={event.title}
                />

                <EventForm
                    action={EventController.update.form(event.id)}
                    event={event}
                    submitLabel="Enregistrer les modifications"
                />
            </div>
        </>
    );
}

EventEdit.layout = ({ event }: Props) => ({
    breadcrumbs: [
        { title: 'Evenements', href: index() },
        { title: 'Modifier', href: edit(event.id) },
    ],
});
