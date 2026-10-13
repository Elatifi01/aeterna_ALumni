import { Head } from '@inertiajs/react';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import { PageHeader } from '@/components/page-header';
import {
    CampaignForm,
    type Criteria,
    type FilterOptions,
} from '@/pages/campaigns/campaign-form';
import { create, index } from '@/routes/campaigns';

type Props = {
    criteria: Criteria;
    recipientCount: number;
    totalAlumni: number;
    filterOptions?: FilterOptions;
};

export default function CampaignCreate({
    criteria,
    recipientCount,
    totalAlumni,
    filterOptions,
}: Props) {
    return (
        <>
            <Head title="Creer une campagne" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Creer une campagne"
                    description="Redigez le message, choisissez les destinataires, puis enregistrez le brouillon"
                />

                <CampaignForm
                    action={CampaignController.store.form()}
                    reloadUrl={create().url}
                    criteria={criteria}
                    recipientCount={recipientCount}
                    totalAlumni={totalAlumni}
                    filterOptions={filterOptions}
                    submitLabel="Enregistrer le brouillon"
                />
            </div>
        </>
    );
}

CampaignCreate.layout = {
    breadcrumbs: [
        { title: 'Campagnes', href: index() },
        { title: 'Creer', href: create() },
    ],
};
