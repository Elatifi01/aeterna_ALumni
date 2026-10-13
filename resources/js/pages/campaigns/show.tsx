import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Mail,
    Pencil,
    RotateCcw,
    Send,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import CampaignController from '@/actions/App/Http/Controllers/CampaignController';
import { AvatarInitials } from '@/components/avatar-initials';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import {
    campaignStatuses,
    StatusBadge,
    type CampaignStatus,
} from '@/components/status-badge';
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
import { formatDate } from '@/pages/campaigns/index';
import { edit, index, show } from '@/routes/campaigns';

type RecipientStatus = 'pending' | 'sent' | 'failed';

type Recipient = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    status: RecipientStatus;
    sent_at: string | null;
    error_message: string | null;
};

type Props = {
    campaign: {
        id: number;
        subject: string;
        content: string;
        status: CampaignStatus;
        recipients_count: number;
        sent_at: string | null;
        created_at: string | null;
    };
    recipients: Recipient[];
    summary: { pending: number; sent: number; failed: number };
    stalled: boolean;
};

const recipientBadges: Record<
    RecipientStatus,
    { label: string; tone: 'success' | 'warning' | 'danger' }
> = {
    pending: { label: 'En attente', tone: 'warning' },
    sent: { label: 'Envoye', tone: 'success' },
    failed: { label: 'Echec', tone: 'danger' },
};

export default function CampaignShow({
    campaign,
    recipients,
    summary,
    stalled,
}: Props) {
    const { can } = usePermissions();
    const [confirmingSend, setConfirmingSend] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [confirmingResendAll, setConfirmingResendAll] = useState(false);

    const isDraft = campaign.status === 'draft';
    const isSending = campaign.status === 'sending';
    const isFinished =
        campaign.status === 'sent' || campaign.status === 'failed';

    useEffect(() => {
        if (!isSending) {
            return;
        }

        const timer = setInterval(() => {
            router.reload({
                only: ['campaign', 'recipients', 'summary', 'stalled'],
            });
        }, 3000);

        return () => clearInterval(timer);
    }, [isSending]);

    return (
        <>
            <Head title={campaign.subject} />
            <div className="flex flex-1 flex-col gap-5 p-4">
                <PageHeader
                    title={campaign.subject}
                    description={
                        campaign.sent_at
                            ? `Envoyee le ${formatDate(campaign.sent_at)}`
                            : `Creee le ${formatDate(campaign.created_at)}`
                    }
                    action={
                        isDraft &&
                        can('campaigns.send') && (
                            <Button onClick={() => setConfirmingSend(true)}>
                                <Send className="size-4" />
                                Envoyer la campagne
                            </Button>
                        )
                    }
                />
                {stalled && (
                    <div className="border-warning/40 bg-warning-soft flex items-start gap-3 rounded-lg border p-4">
                        <AlertTriangle className="text-warning mt-0.5 size-5 shrink-0" />
                        <div className="text-sm">
                            <p className="text-text font-medium">
                                L envoi est a l arret
                            </p>
                            <p className="text-text-secondary mt-1">
                                {summary.pending} destinataire
                                {summary.pending > 1 ? 's' : ''} attend
                                {summary.pending > 1 ? 'ent' : ''} toujours, et
                                rien n a progresse depuis plusieurs minutes. Les
                                envois passent par une file d attente : verifiez
                                qu un worker tourne.
                            </p>
                            <p className="text-text-muted mt-2">
                                En developpement,{' '}
                                <code className="bg-surface-muted rounded px-1 py-0.5">
                                    composer dev
                                </code>{' '}
                                lance le serveur, la file d attente et Vite
                                ensemble. Sinon,{' '}
                                <code className="bg-surface-muted rounded px-1 py-0.5">
                                    php artisan queue:work
                                </code>
                                . La campagne reprendra la ou elle en est.
                            </p>
                        </div>
                    </div>
                )}

                <div className="flex items-center gap-2">
                    <StatusBadge
                        tone={campaignStatuses[campaign.status].tone}
                        label={campaignStatuses[campaign.status].label}
                    />
                    {isDraft && can('campaigns.create') && (
                        <Button variant="outline" className="h-8" asChild>
                            <Link href={edit(campaign.id)}>
                                <Pencil className="size-3.5" />
                                Modifier
                            </Link>
                        </Button>
                    )}
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Destinataires"
                        value={campaign.recipients_count}
                        icon={Mail}
                        hint="Alumni selectionnes au moment de l enregistrement."
                    />
                    <StatCard
                        label="Envoyes"
                        value={summary.sent}
                        icon={CheckCircle2}
                        hint="Messages remis au serveur d envoi."
                    />
                    <StatCard
                        label="En attente"
                        value={summary.pending}
                        icon={Clock}
                        hint="Messages encore dans la file d envoi."
                    />
                    <StatCard
                        label="Echecs"
                        value={summary.failed}
                        icon={XCircle}
                        hint="Messages que le serveur a refuses."
                    />
                </div>
                <section className="border-border bg-surface rounded-lg border p-5">
                    <h2 className="text-text text-base font-semibold">
                        Message
                    </h2>
                    <p className="text-text-secondary mt-2 text-sm whitespace-pre-line">
                        {campaign.content}
                    </p>
                </section>
                <section className="border-border bg-surface overflow-hidden rounded-lg border">
                    <header className="border-border flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <h2 className="text-text text-base font-semibold">
                                Destinataires
                            </h2>
                            <p className="text-text-secondary text-sm">
                                Statut de remise par alumni
                            </p>
                        </div>
                        {isSending && (
                            <span className="text-text-muted text-xs">
                                Actualisation automatique…
                            </span>
                        )}
                    </header>
                    <ul className="divide-border divide-y">
                        {recipients.map((recipient) => (
                            <li
                                key={recipient.id}
                                className="flex items-center gap-3 px-5 py-3"
                            >
                                <AvatarInitials
                                    firstName={recipient.first_name}
                                    lastName={recipient.last_name}
                                    className="size-8"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="text-text block truncate text-sm font-medium">
                                        {recipient.first_name}{' '}
                                        {recipient.last_name}
                                    </span>
                                    <span className="text-text-muted block truncate text-xs">
                                        {recipient.error_message ??
                                            recipient.email}
                                    </span>
                                </span>
                                <StatusBadge
                                    tone={
                                        recipientBadges[recipient.status].tone
                                    }
                                    label={
                                        recipientBadges[recipient.status].label
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                </section>
                {isFinished && can('campaigns.send') && (
                    <section className="border-border bg-surface rounded-lg border p-5">
                        <h2 className="text-text text-base font-semibold">
                            Renvoyer la campagne
                        </h2>
                        <p className="text-text-secondary mt-1 max-w-prose text-sm">
                            {summary.failed > 0
                                ? `${summary.failed} envoi(s) ont echoue. Vous pouvez les reessayer sans deranger les alumni qui ont bien recu le message.`
                                : 'Tous les envois ont abouti. Vous pouvez tout de meme renvoyer le message a l ensemble des destinataires.'}
                        </p>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {summary.failed > 0 && (
                                <Form
                                    {...CampaignController.resend.form(
                                        campaign.id,
                                    )}
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="scope"
                                                value="failed"
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                data-test="resend-failed"
                                            >
                                                <RotateCcw className="size-4" />
                                                Reessayer les {
                                                    summary.failed
                                                }{' '}
                                                echec(s)
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}

                            <Button
                                variant="outline"
                                onClick={() => setConfirmingResendAll(true)}
                            >
                                <Send className="size-4" />
                                Renvoyer a tous les destinataires
                            </Button>
                        </div>
                    </section>
                )}

                {can('campaigns.create') && (
                    <section className="border-border bg-surface rounded-lg border p-5">
                        <h2 className="text-text text-base font-semibold">
                            {isDraft
                                ? 'Supprimer ce brouillon'
                                : 'Supprimer cette campagne'}
                        </h2>
                        <p className="text-text-secondary mt-1 max-w-prose text-sm">
                            {isDraft
                                ? 'Le brouillon disparait de vos listes. Vous pourrez le recuperer depuis la corbeille.'
                                : 'La campagne disparait de vos listes et de vos statistiques, et reste recuperable depuis la corbeille. Les e-mails deja envoyes ne peuvent pas etre rappeles.'}
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
            <Dialog open={confirmingSend} onOpenChange={setConfirmingSend}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Envoyer la campagne ?</DialogTitle>
                        <DialogDescription>
                            La campagne sera envoyee a{' '}
                            {campaign.recipients_count} alumni. Cette action est
                            definitive.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmingSend(false)}
                        >
                            Annuler
                        </Button>
                        <Form
                            {...CampaignController.send.form(campaign.id)}
                            onSuccess={() => setConfirmingSend(false)}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="confirm-send-campaign"
                                >
                                    <Send className="size-4" />
                                    Envoyer maintenant
                                </Button>
                            )}
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog
                open={confirmingResendAll}
                onOpenChange={setConfirmingResendAll}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Renvoyer a tous ?</DialogTitle>
                        <DialogDescription>
                            Les {campaign.recipients_count} destinataires
                            recevront ce message une seconde fois, y compris les{' '}
                            {summary.sent} qui l ont deja recu.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmingResendAll(false)}
                        >
                            Annuler
                        </Button>
                        <Form
                            {...CampaignController.resend.form(campaign.id)}
                            onSuccess={() => setConfirmingResendAll(false)}
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="scope"
                                        value="all"
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test="confirm-resend-all"
                                    >
                                        <Send className="size-4" />
                                        Renvoyer maintenant
                                    </Button>
                                </>
                            )}
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog open={confirmingDelete} onOpenChange={setConfirmingDelete}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {isDraft
                                ? 'Supprimer ce brouillon ?'
                                : 'Supprimer cette campagne ?'}
                        </DialogTitle>
                        <DialogDescription>
                            {campaign.subject} n apparaitra plus dans vos
                            listes. La campagne reste recuperable depuis la
                            corbeille.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setConfirmingDelete(false)}
                        >
                            Annuler
                        </Button>
                        <Form {...CampaignController.destroy.form(campaign.id)}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-delete-campaign"
                                >
                                    Supprimer
                                </Button>
                            )}
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

CampaignShow.layout = ({ campaign }: Props) => ({
    breadcrumbs: [
        { title: 'Campagnes', href: index() },
        { title: campaign.subject, href: show(campaign.id) },
    ],
});
