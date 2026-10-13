import { Form, Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    GraduationCap,
    Mail,
    RotateCcw,
    Search,
    ShieldCheck,
    Trash2,
    UsersRound,
    X,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import TrashController from '@/actions/App/Http/Controllers/TrashController';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index } from '@/routes/trash';

type TrashType = 'alumni' | 'events' | 'campaigns' | 'users' | 'roles';

type TrashItem = {
    type: TrashType;
    type_label: string;
    id: number;
    label: string;
    sublabel: string | null;
    deleted_at: string | null;
};

type Props = {
    items: TrashItem[];
    counts: Record<TrashType, number>;
    filters: { type: TrashType | null; search: string | null };
};

const typeIcons = {
    alumni: GraduationCap,
    events: CalendarDays,
    campaigns: Mail,
    users: UsersRound,
    roles: ShieldCheck,
} as const;

const typeLabels: Record<TrashType, string> = {
    alumni: 'Alumni',
    events: 'Evenements',
    campaigns: 'Campagnes',
    users: 'Utilisateurs',
    roles: 'Roles',
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Corbeille({ items, counts, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [pendingPurge, setPendingPurge] = useState<TrashItem | null>(null);

    const total = Object.values(counts).reduce((sum, count) => sum + count, 0);

    const navigate = (params: Record<string, string | null>) => {
        const merged = {
            type: filters.type,
            search: filters.search,
            ...params,
        };

        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(merged).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        navigate({ search: search || null });
    };

    return (
        <>
            <Head title="Corbeille" />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title="Corbeille"
                    description={
                        total === 0
                            ? 'Aucun element supprime'
                            : `${total} element${total > 1 ? 's' : ''} supprime${total > 1 ? 's' : ''}, restaurables a tout moment`
                    }
                />
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={() => navigate({ type: null })}
                        className={cn(
                            'h-9 rounded-md border px-3 text-sm font-medium',
                            filters.type === null
                                ? 'border-accent bg-accent-soft text-accent'
                                : 'border-border text-text-secondary hover:bg-surface-muted',
                        )}
                    >
                        Tout ({total})
                    </button>
                    {(Object.keys(typeLabels) as TrashType[]).map((type) => (
                        <button
                            key={type}
                            type="button"
                            onClick={() => navigate({ type })}
                            className={cn(
                                'h-9 rounded-md border px-3 text-sm font-medium',
                                filters.type === type
                                    ? 'border-accent bg-accent-soft text-accent'
                                    : 'border-border text-text-secondary hover:bg-surface-muted',
                            )}
                        >
                            {typeLabels[type]} ({counts[type] ?? 0})
                        </button>
                    ))}

                    <form
                        onSubmit={submitSearch}
                        className="relative ml-auto w-full sm:w-64"
                    >
                        <Search className="text-text-muted pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Rechercher dans la corbeille"
                            aria-label="Rechercher dans la corbeille"
                            className="h-9 pl-9"
                        />
                    </form>
                    {(filters.search || filters.type) && (
                        <Button
                            variant="ghost"
                            className="h-9"
                            onClick={() => {
                                setSearch('');
                                router.get(index().url, {}, { replace: true });
                            }}
                        >
                            <X className="size-4" />
                            Reinitialiser
                        </Button>
                    )}
                </div>
                <section className="border-border bg-surface overflow-hidden rounded-lg border">
                    {items.length === 0 ? (
                        <EmptyState
                            icon={Trash2}
                            title={
                                filters.search || filters.type
                                    ? 'Aucun element ne correspond a votre recherche'
                                    : 'La corbeille est vide'
                            }
                            description={
                                filters.search || filters.type
                                    ? 'Modifiez ou reinitialisez vos filtres pour elargir la recherche.'
                                    : 'Les alumni, evenements, campagnes, utilisateurs et roles que vous supprimez arrivent ici et peuvent etre restaures.'
                            }
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {items.map((item) => {
                                const Icon = typeIcons[item.type];

                                return (
                                    <li
                                        key={`${item.type}-${item.id}`}
                                        className="hover:bg-surface-muted flex flex-wrap items-center gap-3 px-5 py-3"
                                    >
                                        <span className="bg-surface-muted text-text-muted flex size-9 shrink-0 items-center justify-center rounded-md">
                                            <Icon className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="text-text block truncate text-sm font-medium">
                                                {item.label}
                                            </span>
                                            <span className="text-text-muted block truncate text-xs">
                                                {item.type_label}
                                                {item.sublabel
                                                    ? ` · ${item.sublabel}`
                                                    : ''}
                                            </span>
                                        </span>
                                        <span className="text-text-muted shrink-0 text-xs">
                                            Supprime le{' '}
                                            {formatDate(item.deleted_at)}
                                        </span>
                                        <span className="flex shrink-0 items-center gap-1">
                                            <Form
                                                {...TrashController.restore.form(
                                                    [item.type, item.id],
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        className="h-9"
                                                        disabled={processing}
                                                    >
                                                        <RotateCcw className="size-3.5" />
                                                        Restaurer
                                                    </Button>
                                                )}
                                            </Form>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-danger size-9"
                                                aria-label={`Supprimer definitivement ${item.label}`}
                                                onClick={() =>
                                                    setPendingPurge(item)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>
            <Dialog
                open={pendingPurge !== null}
                onOpenChange={(open) => !open && setPendingPurge(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Supprimer definitivement ?</DialogTitle>
                        <DialogDescription>
                            {pendingPurge?.label} sera efface de la base de
                            donnees. Cette action est irreversible et ne peut
                            pas etre annulee.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setPendingPurge(null)}
                        >
                            Annuler
                        </Button>
                        {pendingPurge && (
                            <Form
                                {...TrashController.forceDelete.form([
                                    pendingPurge.type,
                                    pendingPurge.id,
                                ])}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setPendingPurge(null)}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                        data-test="confirm-force-delete"
                                    >
                                        Supprimer definitivement
                                    </Button>
                                )}
                            </Form>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Corbeille.layout = {
    breadcrumbs: [{ title: 'Corbeille', href: index() }],
};
