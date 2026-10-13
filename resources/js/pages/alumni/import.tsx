import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Download,
    MailCheck,
    FileSpreadsheet,
    Trash2,
    Undo2,
    UploadCloud,
} from 'lucide-react';
import { useRef, useState } from 'react';
import AlumniImportController from '@/actions/App/Http/Controllers/AlumniImportController';
import { ConfirmWithPasswordDialog } from '@/components/confirm-with-password-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { usePermissions } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { index as alumniIndex } from '@/routes/alumni';
import { index as importIndex, template } from '@/routes/alumni/import';
import type { PlanUsage } from '@/types';

type RowError = { line: number; message: string };

type Analysis = {
    ok: boolean;
    error: string | null;
    total: number;
    valid: number;
    invalid: number;
    over_limit: number;
    importable: number;
    remaining: number | null;
    errors: RowError[];
};

type ImportRecord = {
    id: number;
    original_name: string;
    size_bytes: number;
    status: 'pending' | 'imported' | 'undone';
    rows_total: number;
    rows_imported: number;
    rows_invalid: number;
    rows_over_limit: number;
    errors: RowError[];
    without_account: number;
    imported_at: string | null;
    undone_at: string | null;
    created_at: string | null;
    file_exists: boolean;
    uploaded_by: string | null;
};

type Props = {
    usage: PlanUsage;
    columns: string[];
    requiredColumns: string[];
    maxKilobytes: number;
    extensions: string[];
    pending: (ImportRecord & { analysis: Analysis }) | null;
    history: ImportRecord[];
    portalAvailable: boolean;
    offerAccounts: number | null;
    errors: Record<string, string>;
};

function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} o`;
    }

    return bytes < 1024 * 1024
        ? `${Math.round(bytes / 1024)} Ko`
        : `${(bytes / 1024 / 1024).toFixed(1)} Mo`;
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function AlumniImportPage({
    usage,
    columns,
    requiredColumns,
    maxKilobytes,
    extensions,
    pending,
    history,
    portalAvailable,
    offerAccounts,
    errors,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [discarding, setDiscarding] = useState<ImportRecord | null>(null);
    const [undoing, setUndoing] = useState<ImportRecord | null>(null);
    const { can } = usePermissions();

    const justImported =
        history.find((one) => one.id === offerAccounts) ?? null;
    const [asking, setAsking] = useState<ImportRecord | null>(justImported);

    const accept = extensions.map((one) => `.${one}`).join(',');

    const send = (file: File) => {
        setUploading(true);

        router.post(
            AlumniImportController.store.url(),
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setUploading(false),
            },
        );
    };

    const analysis = pending?.analysis ?? null;

    return (
        <>
            <Head title="Importer des alumni" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Importer des alumni"
                    description="Ajoutez plusieurs alumni a partir d un fichier Excel ou CSV"
                    action={
                        <Button asChild variant="outline">
                            <a href={template().url}>
                                <Download className="size-4" />
                                Telecharger le modele
                            </a>
                        </Button>
                    }
                />
                <p className="text-text-secondary -mt-2 text-sm">
                    Votre plan autorise{' '}
                    {usage.limit === null ? (
                        <strong>un nombre illimite d alumni</strong>
                    ) : (
                        <>
                            <strong>{usage.limit} alumni</strong>, dont{' '}
                            <strong>{usage.used}</strong> deja enregistres — il
                            vous reste <strong>{usage.remaining}</strong> place
                            {(usage.remaining ?? 0) > 1 ? 's' : ''}
                        </>
                    )}
                    . La premiere ligne du fichier est l en-tete et n est jamais
                    comptee.
                </p>
                <section
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDragging(false);

                        const file = event.dataTransfer.files?.[0];

                        if (file) {
                            send(file);
                        }
                    }}
                    className={cn(
                        'flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed px-6 py-14 text-center transition',
                        dragging
                            ? 'border-accent bg-accent-soft'
                            : 'border-border-strong bg-surface',
                    )}
                >
                    <span className="bg-surface-muted text-text-muted flex size-12 items-center justify-center rounded-full">
                        {uploading ? (
                            <Spinner />
                        ) : (
                            <UploadCloud className="size-6" />
                        )}
                    </span>
                    <div>
                        <p className="text-text text-sm font-medium">
                            Glissez votre fichier ici
                        </p>
                        <p className="text-text-muted mt-1 text-sm">
                            ou{' '}
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                className="text-accent underline underline-offset-4"
                            >
                                parcourez vos fichiers
                            </button>
                        </p>
                    </div>
                    <p className="text-text-muted text-xs">
                        {extensions.join(', ')} —{' '}
                        {Math.round(maxKilobytes / 1024)} Mo maximum
                    </p>
                    <input
                        ref={inputRef}
                        type="file"
                        accept={accept}
                        className="hidden"
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                send(file);
                            }

                            event.target.value = '';
                        }}
                    />
                    <InputError message={errors?.file} className="mt-1" />
                </section>
                {pending !== null && analysis !== null && (
                    <section className="border-border bg-surface rounded-lg border">
                        <header className="border-border flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                            <div className="flex items-center gap-3">
                                <FileSpreadsheet className="text-text-muted size-5" />
                                <div>
                                    <p className="text-text text-sm font-medium">
                                        {pending.original_name}
                                    </p>
                                    <p className="text-text-muted text-xs">
                                        {formatSize(pending.size_bytes)} —
                                        depose le{' '}
                                        {formatDate(pending.created_at)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Form
                                    {...AlumniImportController.run.form(
                                        pending.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                analysis.importable === 0
                                            }
                                        >
                                            {processing && <Spinner />}
                                            Importer {analysis.importable}{' '}
                                            alumni
                                        </Button>
                                    )}
                                </Form>
                                <Button
                                    variant="ghost"
                                    onClick={() => setDiscarding(pending)}
                                >
                                    <Trash2 className="size-4" />
                                    Supprimer le fichier
                                </Button>
                            </div>
                        </header>
                        <div className="grid gap-4 p-5 sm:grid-cols-4">
                            <Figure
                                label="Lignes lues"
                                value={analysis.total}
                                hint="Sans l en-tete"
                            />
                            <Figure
                                label="Valides"
                                value={analysis.valid}
                                tone="success"
                            />
                            <Figure
                                label="Ignorees"
                                value={analysis.invalid}
                                tone={
                                    analysis.invalid > 0 ? 'warning' : undefined
                                }
                            />
                            <Figure
                                label="Au-dela du plan"
                                value={analysis.over_limit}
                                tone={
                                    analysis.over_limit > 0
                                        ? 'danger'
                                        : undefined
                                }
                            />
                        </div>
                        {analysis.over_limit > 0 && (
                            <div className="border-border mx-5 mb-5 flex items-start gap-3 rounded-lg border border-dashed p-4">
                                <AlertTriangle className="text-warning mt-0.5 size-5 shrink-0" />
                                <p className="text-text-secondary text-sm">
                                    Votre plan ne permet d ajouter que{' '}
                                    <strong>{analysis.importable}</strong>{' '}
                                    alumni de plus. Les{' '}
                                    <strong>{analysis.over_limit}</strong>{' '}
                                    dernieres lignes valides ne seront pas
                                    importees. Passez a un plan superieur puis
                                    redeposez le meme fichier pour ajouter le
                                    reste.
                                </p>
                            </div>
                        )}

                        {analysis.errors.length > 0 && (
                            <div className="border-border border-t">
                                <p className="text-text px-5 py-3 text-sm font-medium">
                                    Lignes ignorees
                                </p>
                                <ul className="divide-border divide-y">
                                    {analysis.errors.map((error) => (
                                        <li
                                            key={error.line}
                                            className="flex items-baseline gap-3 px-5 py-2.5"
                                        >
                                            <span className="text-text-muted tabular w-16 shrink-0 text-xs">
                                                Ligne {error.line}
                                            </span>
                                            <span className="text-text-secondary text-sm">
                                                {error.message}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </section>
                )}

                <section className="border-border bg-surface rounded-lg border p-5">
                    <h2 className="text-text text-base font-semibold">
                        Colonnes attendues
                    </h2>
                    <p className="text-text-secondary mt-1 text-sm">
                        Conservez la premiere ligne du modele. Les colonnes
                        marquees d une etoile sont obligatoires ; les autres
                        peuvent rester vides.
                    </p>
                    <ul className="mt-3 flex flex-wrap gap-2">
                        {columns.map((column) => (
                            <li
                                key={column}
                                className="border-border text-text-secondary rounded-md border px-2.5 py-1 text-xs"
                            >
                                {column}
                                {requiredColumns.includes(column) && (
                                    <span className="text-danger ml-0.5">
                                        *
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
                {history.length > 0 && (
                    <section className="border-border bg-surface rounded-lg border">
                        <header className="border-border border-b px-5 py-4">
                            <h2 className="text-text text-base font-semibold">
                                Imports precedents
                            </h2>
                        </header>
                        <ul className="divide-border divide-y">
                            {history.map((record) => (
                                <li
                                    key={record.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                                >
                                    <div className="min-w-0">
                                        <p className="text-text flex items-center gap-2 text-sm font-medium">
                                            {record.original_name}
                                            <StatusBadge
                                                tone={
                                                    record.status === 'undone'
                                                        ? 'neutral'
                                                        : 'success'
                                                }
                                                label={
                                                    record.status === 'undone'
                                                        ? 'Annule'
                                                        : 'Importe'
                                                }
                                            />
                                        </p>
                                        <p className="text-text-muted mt-1 text-xs">
                                            {record.rows_imported} alumni
                                            importes
                                            {record.rows_invalid > 0 &&
                                                `, ${record.rows_invalid} ignorees`}
                                            {record.rows_over_limit > 0 &&
                                                `, ${record.rows_over_limit} au-dela du plan`}
                                            {' — '}
                                            {formatDate(record.imported_at)}
                                            {record.uploaded_by &&
                                                ` par ${record.uploaded_by}`}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {record.status === 'imported' &&
                                            portalAvailable &&
                                            record.without_account > 0 &&
                                            can('alumni.invite') && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setAsking(record)
                                                    }
                                                >
                                                    <MailCheck className="size-4" />
                                                    Creer{' '}
                                                    {record.without_account}{' '}
                                                    comptes
                                                </Button>
                                            )}

                                        {record.status === 'imported' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setUndoing(record)
                                                }
                                            >
                                                <Undo2 className="size-4" />
                                                Annuler cet import
                                            </Button>
                                        )}

                                        {record.file_exists && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setDiscarding(record)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                Supprimer le fichier
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <div>
                    <Link
                        href={alumniIndex()}
                        className="text-text-muted hover:text-text text-sm"
                    >
                        Retour aux alumni
                    </Link>
                </div>
            </div>
            <Dialog
                open={asking !== null}
                onOpenChange={(open) => !open && setAsking(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Creer les comptes du portail ?
                        </DialogTitle>
                        <DialogDescription>
                            {asking?.rows_imported} alumni viennent d etre
                            importes, dont{' '}
                            <strong>{asking?.without_account}</strong> sans
                            compte. Voulez-vous leur ouvrir un acces au portail
                            et leur envoyer leurs identifiants par e-mail ?
                            <br />
                            <br />
                            Chacun recevra un mot de passe provisoire qu il
                            devra changer a sa premiere connexion. Vous pourrez
                            aussi le faire plus tard, alumni par alumni.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAsking(null)}
                        >
                            Plus tard
                        </Button>
                        {asking !== null && (
                            <Form
                                {...AlumniImportController.accounts.form(
                                    asking.id,
                                )}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setAsking(null)}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        <MailCheck className="size-4" />
                                        Creer et envoyer
                                    </Button>
                                )}
                            </Form>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <ConfirmWithPasswordDialog
                open={discarding !== null}
                onOpenChange={(open) => !open && setDiscarding(null)}
                action={
                    discarding === null
                        ? null
                        : AlumniImportController.destroy.form(discarding.id)
                }
                title="Supprimer ce fichier ?"
                description={
                    <>
                        Le fichier <strong>{discarding?.original_name}</strong>{' '}
                        sera efface de nos serveurs. Il contient les coordonnees
                        de personnes reelles, alors confirmez avec votre mot de
                        passe.
                        {discarding?.status === 'imported' && (
                            <>
                                {' '}
                                Les alumni deja importes ne sont pas supprimes.
                            </>
                        )}
                    </>
                }
                confirmLabel="Supprimer le fichier"
            />
            <ConfirmWithPasswordDialog
                open={undoing !== null}
                onOpenChange={(open) => !open && setUndoing(null)}
                action={
                    undoing === null
                        ? null
                        : AlumniImportController.undo.form(undoing.id)
                }
                title="Annuler cet import ?"
                description={
                    <>
                        Les <strong>{undoing?.rows_imported}</strong> alumni
                        ajoutes par ce fichier seront deplaces vers la
                        corbeille, d ou vous pourrez les restaurer. Les alumni
                        ajoutes autrement ne sont pas touches.
                    </>
                }
                confirmLabel="Annuler l import"
            />
        </>
    );
}

function Figure({
    label,
    value,
    hint,
    tone,
}: {
    label: string;
    value: number;
    hint?: string;
    tone?: 'success' | 'warning' | 'danger';
}) {
    const toneClass =
        tone === 'success'
            ? 'text-success'
            : tone === 'warning'
              ? 'text-warning'
              : tone === 'danger'
                ? 'text-danger'
                : 'text-text';

    return (
        <div className="border-border rounded-lg border p-4">
            <p className="text-text-muted text-xs">{label}</p>
            <p className={cn('tabular mt-1 text-2xl font-semibold', toneClass)}>
                {value}
            </p>
            {hint && <p className="text-text-muted mt-0.5 text-xs">{hint}</p>}
        </div>
    );
}

AlumniImportPage.layout = {
    breadcrumbs: [
        { title: 'Alumni', href: alumniIndex() },
        { title: 'Importer', href: importIndex() },
    ],
};
