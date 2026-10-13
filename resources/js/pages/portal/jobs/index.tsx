import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Bookmark,
    BookmarkCheck,
    Briefcase,
    Building2,
    ExternalLink,
    MapPin,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index as jobsIndex, store } from '@/routes/portal/jobs';

type Offer = {
    id: string;
    title: string;
    company: string | null;
    company_url: string | null;
    location: string | null;
    snippet: string | null;
    employment_type: string | null;
    experience_level: string | null;
    link: string;
    source: string;
    source_label: string;
    state: 'saved' | 'applied' | null;
};

type Source = { key: string; label: string };

type SourceStatus = {
    label: string;
    degraded: boolean;
    reason: string | null;
    returned: number;
};

type Category = { id: string; name: string; count: number | null };

type Props = {
    filters: {
        keywords: string;
        city: string;
        category: string | null;
        page: number;
    };
    offers: Offer[];
    returned: number;
    sourceStatuses: Record<string, SourceStatus>;
    searched: boolean;
    available: boolean;
    sources: Source[];
    categories: Category[];
};

const degradedMessages: Record<string, string> = {
    'not-configured':
        'Les offres d emploi ne sont pas encore configurees sur cette plateforme.',
    'budget-exhausted':
        'Le nombre de recherches autorisees ce mois-ci est atteint. Les resultats ci-dessous datent de votre derniere recherche.',
    'rate-limited':
        'Trop de recherches en peu de temps. Patientez une minute puis reessayez.',
    'upstream-error':
        'Le service d offres est momentanement indisponible. Reessayez dans quelques minutes.',
};

export default function PortalJobsIndex({
    filters,
    offers,
    returned,
    sourceStatuses,
    searched,
    available,
    sources,
    categories,
}: Props) {
    const [keywords, setKeywords] = useState(filters.keywords);
    const [city, setCity] = useState(filters.city);
    const [category, setCategory] = useState(filters.category ?? '');
    const degradedSources = Object.entries(sourceStatuses).filter(
        ([, status]) => status.degraded,
    );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            jobsIndex().url,
            {
                ...(keywords === '' ? {} : { keywords }),
                ...(city === '' ? {} : { city }),
                ...(category === '' ? {} : { category }),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const narrowedAway = searched && offers.length === 0 && returned > 0;

    return (
        <>
            <Head title="Offres d emploi" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Offres d emploi"
                    description={
                        sources.length > 0
                            ? `Recherchez parmi les offres publiees sur ${sources.map((one) => one.label).join(', ')}`
                            : 'Recherchez parmi les offres publiees sur les sites d emploi'
                    }
                />
                {!available && (
                    <div className="border-warning/40 bg-warning-soft flex items-start gap-3 rounded-lg border p-4">
                        <AlertTriangle className="text-warning mt-0.5 size-5 shrink-0" />
                        <p className="text-text-secondary text-sm">
                            {degradedMessages['not-configured']}
                        </p>
                    </div>
                )}
                {degradedSources.map(([key, status]) => (
                    <div
                        key={key}
                        className="border-warning/40 bg-warning-soft flex items-start gap-3 rounded-lg border p-4"
                    >
                        <AlertTriangle className="text-warning mt-0.5 size-5 shrink-0" />
                        <p className="text-text-secondary text-sm">
                            <span className="text-text font-medium">
                                {status.label} :
                            </span>{' '}
                            {(status.reason !== null &&
                                degradedMessages[status.reason]) ??
                                'Les offres ne sont pas disponibles pour le moment.'}
                        </p>
                    </div>
                ))}

                {available && (
                    <form
                        onSubmit={submit}
                        className="border-border bg-surface grid gap-4 rounded-lg border p-4 sm:grid-cols-[1.6fr_1.4fr_1fr_auto]"
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="keywords">Mots-cles</Label>
                            <Input
                                id="keywords"
                                value={keywords}
                                onChange={(event) =>
                                    setKeywords(event.target.value)
                                }
                                placeholder="developpeur, comptable…"
                                className="h-11"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="category">Metier</Label>
                            <select
                                id="category"
                                value={category}
                                onChange={(event) =>
                                    setCategory(event.target.value)
                                }
                                className="border-border-strong bg-surface text-text h-11 rounded-md border px-3 text-sm"
                            >
                                <option value="">Tous les metiers</option>
                                {categories.map((one) => (
                                    <option key={one.id} value={one.id}>
                                        {one.name}
                                        {one.count !== null
                                            ? ` (${one.count})`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="city">Ville</Label>
                            <Input
                                id="city"
                                value={city}
                                onChange={(event) =>
                                    setCity(event.target.value)
                                }
                                placeholder="Casablanca"
                                className="h-11"
                            />
                        </div>
                        <div className="flex items-end">
                            <Button
                                type="submit"
                                className="h-11 w-full sm:w-auto"
                                disabled={
                                    keywords.trim() === '' && category === ''
                                }
                            >
                                <Search className="size-4" />
                                Rechercher
                            </Button>
                        </div>
                    </form>
                )}

                {searched && (
                    <p className="text-text-muted flex items-center justify-between gap-3 text-sm">
                        <span>
                            {offers.length} offre{offers.length > 1 ? 's' : ''}
                            {filters.city !== '' && offers.length < returned ? (
                                <>
                                    {' a '}
                                    <span className="capitalize">
                                        {filters.city}
                                    </span>
                                    {`, sur ${returned} trouvees`}
                                </>
                            ) : (
                                ' trouvees'
                            )}
                        </span>
                        <Link
                            href={jobsIndex({ query: { reset: 1 } })}
                            className="text-text-secondary hover:text-text underline-offset-4 hover:underline"
                        >
                            Nouvelle recherche
                        </Link>
                    </p>
                )}

                <section className="border-border bg-surface rounded-lg border">
                    {offers.length === 0 ? (
                        <EmptyState
                            icon={Briefcase}
                            title={
                                narrowedAway
                                    ? `Aucune offre a ${filters.city.replace(/^./, (c) => c.toUpperCase())}`
                                    : searched
                                      ? 'Aucune offre trouvee'
                                      : 'Lancez une recherche'
                            }
                            description={
                                narrowedAway
                                    ? `${returned} offres correspondent a votre recherche, mais aucune dans cette ville. Videz le champ Ville pour les voir toutes.`
                                    : searched
                                      ? 'Essayez d autres mots-cles ou choisissez un autre metier.'
                                      : 'Saisissez un metier ou une competence pour voir les offres.'
                            }
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {offers.map((offer) => (
                                <li
                                    key={offer.id}
                                    className="flex flex-col gap-3 px-5 py-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h3 className="text-text flex flex-wrap items-center gap-2 text-sm font-semibold">
                                                {offer.title}
                                                <span className="bg-surface-muted text-text-secondary rounded-full px-2 py-0.5 text-[11px] font-medium">
                                                    {offer.source_label}
                                                </span>
                                            </h3>
                                            <div className="text-text-muted mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                                {offer.company && (
                                                    <span className="flex items-center gap-1.5">
                                                        <Building2 className="size-3.5" />
                                                        {offer.company}
                                                    </span>
                                                )}
                                                {offer.location && (
                                                    <span className="flex items-center gap-1.5">
                                                        <MapPin className="size-3.5" />
                                                        {offer.location}
                                                    </span>
                                                )}
                                                {offer.employment_type && (
                                                    <span>
                                                        {offer.employment_type}
                                                    </span>
                                                )}
                                                {offer.experience_level && (
                                                    <span>
                                                        {offer.experience_level}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        {offer.state !== null && (
                                            <StatusBadge
                                                tone={
                                                    offer.state === 'applied'
                                                        ? 'success'
                                                        : 'info'
                                                }
                                                label={
                                                    offer.state === 'applied'
                                                        ? 'Candidature'
                                                        : 'Enregistree'
                                                }
                                            />
                                        )}
                                    </div>
                                    {offer.snippet && (
                                        <p className="text-text-secondary text-sm">
                                            {offer.snippet}
                                        </p>
                                    )}

                                    <div className="flex flex-wrap items-center gap-2">
                                        <Form
                                            {...store.form()}
                                            options={{ preserveScroll: true }}
                                            onSuccess={() =>
                                                window.open(
                                                    offer.link,
                                                    '_blank',
                                                    'noopener,noreferrer',
                                                )
                                            }
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <OfferFields
                                                        offer={offer}
                                                        status="applied"
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        {processing && (
                                                            <Spinner />
                                                        )}
                                                        <ExternalLink className="size-4" />
                                                        Postuler
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                        {offer.state === null && (
                                            <Form
                                                {...store.form()}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <OfferFields
                                                            offer={offer}
                                                            status="saved"
                                                        />
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {processing ? (
                                                                <Spinner />
                                                            ) : (
                                                                <Bookmark className="size-4" />
                                                            )}
                                                            Enregistrer
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        )}

                                        {offer.state === 'saved' && (
                                            <span className="text-text-muted flex items-center gap-1.5 text-xs">
                                                <BookmarkCheck className="size-4" />
                                                Dans vos offres enregistrees
                                            </span>
                                        )}

                                        <span className="text-text-muted ml-auto text-xs">
                                            via emploi.ma
                                        </span>
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

function OfferFields({
    offer,
    status,
}: {
    offer: Offer;
    status: 'saved' | 'applied';
}) {
    return (
        <>
            <input type="hidden" name="external_id" value={offer.id} />
            <input type="hidden" name="title" value={offer.title} />
            <input type="hidden" name="company" value={offer.company ?? ''} />
            <input type="hidden" name="location" value={offer.location ?? ''} />
            <input type="hidden" name="source" value={offer.source_label} />
            <input type="hidden" name="link" value={offer.link} />
            <input type="hidden" name="status" value={status} />
        </>
    );
}

PortalJobsIndex.layout = {
    breadcrumbs: [{ title: 'Offres d emploi', href: jobsIndex() }],
};
