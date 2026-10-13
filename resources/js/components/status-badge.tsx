import { cn } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const toneClasses: Record<StatusTone, string> = {
    success: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20',
    warning: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/20',
    danger: 'bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/20',
    info: 'bg-blue-500/15 text-blue-700 dark:text-blue-400 border border-blue-500/20',
    neutral: 'bg-slate-200/60 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-300/40 dark:border-white/10',
};

export function StatusBadge({
    tone,
    label,
    className,
}: {
    tone: StatusTone;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap backdrop-blur-xs',
                toneClasses[tone],
                className,
            )}
        >
            {label}
        </span>
    );
}

type StatusMap<T extends string> = Record<
    T,
    { label: string; tone: StatusTone }
>;

export type EventStatus = 'draft' | 'published' | 'closed' | 'canceled';

export const eventStatuses: StatusMap<EventStatus> = {
    draft: { label: 'Brouillon', tone: 'warning' },
    published: { label: 'Publie', tone: 'success' },
    closed: { label: 'Termine', tone: 'neutral' },
    canceled: { label: 'Annule', tone: 'danger' },
};

export type CampaignStatus = 'draft' | 'sending' | 'sent' | 'failed';

export const campaignStatuses: StatusMap<CampaignStatus> = {
    draft: { label: 'Brouillon', tone: 'warning' },
    sending: { label: 'En cours d envoi', tone: 'info' },
    sent: { label: 'Envoyee', tone: 'success' },
    failed: { label: 'Echec', tone: 'danger' },
};

export type SubscriptionStatus = 'pending' | 'active' | 'expired' | 'canceled';

export const subscriptionStatuses: StatusMap<SubscriptionStatus> = {
    pending: { label: 'En attente', tone: 'warning' },
    active: { label: 'Actif', tone: 'success' },
    expired: { label: 'Expire', tone: 'neutral' },
    canceled: { label: 'Annule', tone: 'danger' },
};
