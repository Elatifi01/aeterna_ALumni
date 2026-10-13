import { Head } from '@inertiajs/react';
import EventController from '@/actions/App/Http/Controllers/EventController';
import { PageHeader } from '@/components/page-header';
import { EventForm } from '@/pages/events/event-form';
import { create, index } from '@/routes/events';

export default function EventCreate() {
    return (
        <>
            <Head title="Creer un evenement" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Creer un evenement"
                    description="Renseignez les informations, la date et les inscriptions"
                />

                <EventForm
                    action={EventController.store.form()}
                    submitLabel="Creer l evenement"
                />
            </div>
        </>
    );
}

EventCreate.layout = {
    breadcrumbs: [
        { title: 'Evenements', href: index() },
        { title: 'Creer', href: create() },
    ],
};
