import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Apparence" />

            <h1 className="sr-only">Apparence</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Apparence"
                    description="Choisissez le theme clair ou sombre de l interface"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Apparence',
            href: editAppearance(),
        },
    ],
};
