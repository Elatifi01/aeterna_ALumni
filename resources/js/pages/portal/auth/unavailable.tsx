import { Head } from '@inertiajs/react';
import { Lock } from 'lucide-react';

type Props = {
    organization: {
        name: string;
        slug: string;
        logo_url: string | null;
    };
};

export default function PortalUnavailable({ organization }: Props) {
    return (
        <>
            <Head title="Portail indisponible" />
            <div className="flex flex-col items-center gap-4 text-center">
                <span className="bg-surface-muted text-text-muted flex size-12 items-center justify-center rounded-full">
                    <Lock className="size-6" />
                </span>
                <p className="text-text-secondary text-sm">
                    Le portail alumni de {organization.name} n est pas
                    accessible pour le moment.
                </p>
                <p className="text-text-muted text-sm">
                    Rapprochez-vous de votre etablissement pour en savoir plus.
                </p>
            </div>
        </>
    );
}

PortalUnavailable.layout = {
    title: 'Portail indisponible',
    description: '',
};
