import { Form, Head } from '@inertiajs/react';
import { Briefcase, Building2, ExternalLink, MapPin, X } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    applications as applicationsRoute,
    destroy,
} from '@/routes/portal/jobs';
import type { Paginated } from '@/types';

type Application = {
    id: number;
    title: string;
    company: string | null;
    location: string | null;
    source: string | null;
    link: string;
    status: 'saved' | 'applied';
    applied_at: string | null;
    created_at: string | null;
};

type Props = {
    applications: Paginated<Application>;
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

export default function PortalJobApplications({ applications }: Props) {
    return (
        <>
            <Head title="Mes candidatures" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Mes candidatures"
                    description="Les offres que vous avez enregistrees ou auxquelles vous avez postule"
                />
                <section className="border-border bg-surface rounded-lg border">
                    {applications.data.length === 0 ? (
                        <EmptyState
                            icon={Briefcase}
                            title="Aucune offre enregistree"
                            description="Enregistrez une offre depuis la recherche pour la retrouver ici."
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {applications.data.map((application) => (
                                <li
                                    key={application.id}
                                    className="flex flex-col gap-3 px-5 py-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h3 className="text-text text-sm font-semibold">
                                                {application.title}
                                            </h3>
                                            <div className="text-text-muted mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                                {application.company && (
                                                    <span className="flex items-center gap-1.5">
                                                        <Building2 className="size-3.5" />
                                                        {application.company}
                                                    </span>
                                                )}
                                                {application.location && (
                                                    <span className="flex items-center gap-1.5">
                                                        <MapPin className="size-3.5" />
                                                        {application.location}
                                                    </span>
                                                )}
                                                <span>
                                                    {application.status ===
                                                    'applied'
                                                        ? `Postule le ${formatDate(application.applied_at)}`
                                                        : `Enregistree le ${formatDate(application.created_at)}`}
                                                </span>
                                            </div>
                                        </div>
                                        <StatusBadge
                                            tone={
                                                application.status === 'applied'
                                                    ? 'success'
                                                    : 'info'
                                            }
                                            label={
                                                application.status === 'applied'
                                                    ? 'Candidature'
                                                    : 'Enregistree'
                                            }
                                        />
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a
                                                href={application.link}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <ExternalLink className="size-4" />
                                                Ouvrir l offre
                                            </a>
                                        </Button>
                                        <Form
                                            {...destroy.form(application.id)}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={processing}
                                                >
                                                    {processing ? (
                                                        <Spinner />
                                                    ) : (
                                                        <X className="size-4" />
                                                    )}
                                                    Retirer
                                                </Button>
                                            )}
                                        </Form>
                                        {application.source && (
                                            <span className="text-text-muted ml-auto text-xs">
                                                via {application.source}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

PortalJobApplications.layout = {
    breadcrumbs: [{ title: 'Mes candidatures', href: applicationsRoute() }],
};
