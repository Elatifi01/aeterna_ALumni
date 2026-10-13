import { Head } from '@inertiajs/react';
import AlumniController from '@/actions/App/Http/Controllers/AlumniController';
import { PageHeader } from '@/components/page-header';
import { AlumniForm } from '@/pages/alumni/alumni-form';
import { create, index } from '@/routes/alumni';

export default function AlumniCreate() {
    return (
        <>
            <Head title="Ajouter un alumni" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Ajouter un alumni"
                    description="Renseignez les informations de l ancien etudiant"
                />

                <AlumniForm
                    action={AlumniController.store.form()}
                    submitLabel="Enregistrer"
                />
            </div>
        </>
    );
}

AlumniCreate.layout = {
    breadcrumbs: [
        { title: 'Alumni', href: index() },
        { title: 'Ajouter', href: create() },
    ],
};
