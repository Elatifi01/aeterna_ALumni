export type Cycle = 'monthly' | 'yearly';

/** What a landing page contact request is about. */
export type ContactSubject = 'demo' | 'custom' | 'other';

export type PublicPlan = {
    slug: string;
    name: string;
    description: string | null;
    price_monthly: string;
    price_yearly: string;
    max_alumni: number | null;
    max_events: number | null;
    max_campaigns: number | null;
    max_users: number | null;
    alumni_portal: boolean;
};

export function priceFor(plan: PublicPlan, cycle: Cycle): number {
    return Number(cycle === 'yearly' ? plan.price_yearly : plan.price_monthly);
}

export function formatPrice(amount: number): string {
    return `$${amount.toLocaleString('fr-FR', {
        minimumFractionDigits: Number.isInteger(amount) ? 0 : 2,
        maximumFractionDigits: 2,
    })}`;
}

/** Months saved by paying yearly, e.g. 2 when the year costs ten months. */
export function monthsSaved(plan: PublicPlan): number {
    const monthly = Number(plan.price_monthly);

    if (monthly <= 0) {
        return 0;
    }

    return Math.max(0, Math.round(12 - Number(plan.price_yearly) / monthly));
}

function limit(value: number | null, singular: string, plural: string): string {
    if (value === null) {
        return `${plural.charAt(0).toUpperCase()}${plural.slice(1)} illimités`;
    }

    return `${value.toLocaleString('fr-FR')} ${value > 1 ? plural : singular}`;
}

/** What the plan includes, in the words a visitor reads. */
export function features(
    plan: PublicPlan,
): { label: string; included: boolean }[] {
    return [
        { label: limit(plan.max_alumni, 'alumni', 'alumni'), included: true },
        {
            label: limit(plan.max_events, 'événement', 'événements'),
            included: true,
        },
        {
            label: limit(
                plan.max_campaigns,
                'campagne e-mail',
                'campagnes e-mail',
            ),
            included: true,
        },
        {
            label: limit(plan.max_users, 'utilisateur', 'utilisateurs'),
            included: true,
        },
        {
            label: 'Portail diplômé et offres d’emploi',
            included: plan.alumni_portal,
        },
        { label: 'Rôles et permissions sur mesure', included: true },
    ];
}
