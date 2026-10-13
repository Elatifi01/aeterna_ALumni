import { Form, Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    KeyRound,
    Lock,
    LockOpen,
    Pencil,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import AlumniAccountController from '@/actions/App/Http/Controllers/AlumniAccountController';
import AlumniController from '@/actions/App/Http/Controllers/AlumniController';
import { AvatarInitials } from '@/components/avatar-initials';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePermissions } from '@/lib/permissions';
import { edit, index, show } from '@/routes/alumni';

type AlumniDetail = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    degree: string | null;
    graduation_year: number | null;
    company: string | null;
    job_title: string | null;
    city: string | null;
};

type PortalAccount = {
    available: boolean;
    has_account: boolean;
    credentials_sent_at: string | null;
    activated_at: string | null;
    last_login_at: string | null;
    access: boolean;
};

type EventEntry = {
    id: number;
    title: string;
    starts_at: string | null;
    status: 'registered' | 'canceled';
    attended: boolean;
};

type Props = {
    alumni: AlumniDetail;
    events: EventEntry[];
    portal: PortalAccount;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function portalSummary(portal: PortalAccount): string {
    if (!portal.has_account) {
        return 'Cet alumni n a pas encore de compte. Envoyez-lui ses identifiants pour lui ouvrir l acces.';
    }

    if (portal.activated_at === null) {
        return `Identifiants envoyes le ${formatDate(portal.credentials_sent_at)}. En attente de la premiere connexion.`;
    }

    if (!portal.access) {
        return 'L acces au portail est suspendu. Le compte existe toujours et peut etre retabli.';
    }

    return portal.last_login_at === null
        ? `Compte actif depuis le ${formatDate(portal.activated_at)}.`
        : `Compte actif. Derniere connexion le ${formatDate(portal.last_login_at)}.`;
}

export default function AlumniShow({ alumni, events, portal }: Props) {
    const { can } = usePermissions();
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const fullName = `${alumni.first_name} ${alumni.last_name}`;

    const details: { label: string; value: string }[] = [
        { label: 'Adresse e-mail', value: alumni.email },
        { label: 'Telephone', value: alumni.phone ?? '—' },
        { label: 'Diplome', value: alumni.degree ?? '—' },
        {
            label: 'Promotion',
            value: alumni.graduation_year?.toString() ?? '—',
        },
        { label: 'Entreprise', value: alumni.company ?? '—' },
        { label: 'Poste', value: alumni.job_title ?? '—' },
        { label: 'Ville', value: alumni.city ?? '—' },
    ];

    return (
        <>
            <Head title={fullName} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={fullName}
                    description={
                        [alumni.job_title, alumni.company]
                            .filter(Boolean)
                            .join(' · ') || alumni.email
                    }
                    action={
                        can('alumni.update') && (
                            <Button asChild>
                                <Link href={edit(alumni.id)}>
                                    <Pencil className="size-4" />
                                    Modifier
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="grid gap-5 lg:grid-cols-3">
                    <section className="border-border bg-surface rounded-lg border lg:col-span-2">
                        <header className="border-border flex items-center gap-3 border-b px-5 py-4">
                            <AvatarInitials
                                firstName={alumni.first_name}
                                lastName={alumni.last_name}
                                className="size-11 text-sm"
                            />
                            <div>
                                <h2 className="text-text text-base font-semibold">
                                    Informations
                                </h2>
                                <p className="text-text-secondary text-sm">
                                    Fiche de l ancien etudiant
                                </p>
                            </div>
                        </header>
                        <dl className="divide-border divide-y">
                            {details.map((detail) => (
                                <div
                                    key={detail.label}
                                    className="grid gap-1 px-5 py-3 sm:grid-cols-3 sm:gap-4"
                                >
                                    <dt className="text-text-secondary text-sm">
                                        {detail.label}
                                    </dt>
                                    <dd className="text-text text-sm sm:col-span-2">
                                        {detail.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                    <section className="border-border bg-surface rounded-lg border">
                        <header className="border-border border-b px-5 py-4">
                            <h2 className="text-text text-base font-semibold">
                                Evenements
                            </h2>
                            <p className="text-text-secondary text-sm">
                                Inscriptions et presence
                            </p>
                        </header>
                        {events.length === 0 ? (
                            <EmptyState
                                icon={CalendarDays}
                                title="Aucune inscription"
                                description="Cet alumni n est inscrit a aucun evenement."
                            />
                        ) : (
                            <ul className="divide-border divide-y">
                                {events.map((event) => (
                                    <li key={event.id} className="px-5 py-3">
                                        <p className="text-text truncate text-sm font-medium">
                                            {event.title}
                                        </p>
                                        <div className="mt-1 flex items-center gap-2">
                                            <span className="text-text-muted text-xs">
                                                {formatDate(event.starts_at)}
                                            </span>
                                            {event.status === 'canceled' ? (
                                                <Badge variant="secondary">
                                                    Annulee
                                                </Badge>
                                            ) : event.attended ? (
                                                <span className="bg-success-soft text-success rounded-sm px-2 py-0.5 text-xs font-medium">
                                                    Present
                                                </span>
                                            ) : (
                                                <span className="bg-warning-soft text-warning rounded-sm px-2 py-0.5 text-xs font-medium">
                                                    Inscrit
                                                </span>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
                {portal.available &&
                    (can('alumni.invite') || can('alumni.portal')) && (
                        <section className="border-border bg-surface rounded-lg border p-5">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-text flex items-center gap-2 text-base font-semibold">
                                        <KeyRound className="text-text-muted size-4" />
                                        Acces au portail alumni
                                    </h2>
                                    <p className="text-text-secondary mt-1 max-w-prose text-sm">
                                        {portalSummary(portal)}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {portal.has_account &&
                                        can('alumni.portal') && (
                                            <Form
                                                {...AlumniAccountController.toggleAccess.form(
                                                    alumni.id,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="portal_access"
                                                            value={
                                                                portal.access
                                                                    ? '0'
                                                                    : '1'
                                                            }
                                                        />
                                                        <Button
                                                            type="submit"
                                                            variant="outline"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {portal.access ? (
                                                                <>
                                                                    <Lock className="size-4" />
                                                                    Suspendre l
                                                                    acces
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <LockOpen className="size-4" />
                                                                    Retablir l
                                                                    acces
                                                                </>
                                                            )}
                                                        </Button>
                                                    </>
                                                )}
                                            </Form>
                                        )}

                                    {can('alumni.invite') && (
                                        <Form
                                            {...AlumniAccountController.store.form(
                                                alumni.id,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    <Send className="size-4" />
                                                    {portal.has_account
                                                        ? 'Renvoyer les identifiants'
                                                        : 'Envoyer les identifiants'}
                                                </Button>
                                            )}
                                        </Form>
                                    )}

                                    {portal.has_account &&
                                        can('alumni.invite') && (
                                            <Form
                                                {...AlumniAccountController.destroy.form(
                                                    alumni.id,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        disabled={processing}
                                                    >
                                                        Retirer l acces
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                </div>
                            </div>
                        </section>
                    )}

                {can('alumni.delete') && (
                    <section className="border-border bg-surface rounded-lg border p-5">
                        <h2 className="text-text text-base font-semibold">
                            Supprimer cet alumni
                        </h2>
                        <p className="text-text-secondary mt-1 max-w-prose text-sm">
                            La fiche est archivee et disparait des listes. Ses
                            inscriptions aux evenements passes sont conservees.
                        </p>
                        <Button
                            variant="destructive"
                            className="mt-4"
                            onClick={() => setConfirmingDelete(true)}
                        >
                            <Trash2 className="size-4" />
                            Supprimer
                        </Button>
                    </section>
                )}
            </div>
            <Dialog open={confirmingDelete} onOpenChange={setConfirmingDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Supprimer {fullName} ?</DialogTitle>
                        <DialogDescription>
                            Cette fiche n apparaitra plus dans vos listes. Cette
                            action peut etre annulee par un administrateur.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmingDelete(false)}
                        >
                            Annuler
                        </Button>
                        <Form {...AlumniController.destroy.form(alumni.id)}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-delete-alumni"
                                >
                                    Supprimer definitivement de la liste
                                </Button>
                            )}
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AlumniShow.layout = ({ alumni }: Props) => ({
    breadcrumbs: [
        { title: 'Alumni', href: index() },
        {
            title: `${alumni.first_name} ${alumni.last_name}`,
            href: show(alumni.id),
        },
    ],
});
