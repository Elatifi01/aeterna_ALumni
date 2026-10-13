import { Head } from '@inertiajs/react';
import AlumniController from '@/actions/App/Http/Controllers/AlumniController';
import { PageHeader } from '@/components/page-header';
import { AlumniForm, type AlumniFormValues } from '@/pages/alumni/alumni-form';
import { edit, index } from '@/routes/alumni';

type Props = {
    alumni: AlumniFormValues & { id: number };
};

export default function AlumniEdit({ alumni }: Props) {
    return (
        <>
            <Head title={`Modifier ${alumni.first_name} ${alumni.last_name}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Modifier un alumni"
                    description={`${alumni.first_name} ${alumni.last_name}`}
                />

                <AlumniForm
                    action={AlumniController.update.form(alumni.id)}
                    alumni={alumni}
                    submitLabel="Enregistrer les modifications"
                />
            </div>
        </>
    );
}

AlumniEdit.layout = ({ alumni }: Props) => ({
    breadcrumbs: [
        { title: 'Alumni', href: index() },
        { title: 'Modifier', href: edit(alumni.id) },
    ],
});
