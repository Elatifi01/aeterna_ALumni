import { Head } from '@inertiajs/react';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import { PageHeader } from '@/components/page-header';
import {
    CampaignForm,
    type Criteria,
    type FilterOptions,
} from '@/pages/campaigns/campaign-form';
import { edit, index } from '@/routes/campaigns';

type Props = {
    campaign: {
        id: number;
        subject: string;
        content: string;
        recipients_count: number;
    };
    criteria: Criteria;
    recipientCount: number;
    totalAlumni: number;
    filterOptions?: FilterOptions;
};

export default function CampaignEdit({
    campaign,
    criteria,
    recipientCount,
    totalAlumni,
    filterOptions,
}: Props) {
    return (
        <>
            <Head title={`Modifier ${campaign.subject}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Modifier la campagne"
                    description={campaign.subject}
                />

                <CampaignForm
                    action={CampaignController.update.form(campaign.id)}
                    reloadUrl={edit(campaign.id).url}
                    campaign={campaign}
                    criteria={criteria}
                    recipientCount={recipientCount}
                    totalAlumni={totalAlumni}
                    filterOptions={filterOptions}
                    submitLabel="Enregistrer les modifications"
                />
            </div>
        </>
    );
}

CampaignEdit.layout = ({ campaign }: Props) => ({
    breadcrumbs: [
        { title: 'Campagnes', href: index() },
        { title: 'Modifier', href: edit(campaign.id) },
    ],
});
