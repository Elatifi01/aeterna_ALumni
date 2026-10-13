import { Form, Head, Link, router } from '@inertiajs/react';
import { Layers, SlidersHorizontal } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import SubscriptionController from '@/actions/App/Http/Controllers/Admin/SubscriptionController';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/admin/subscriptions';

type Resource = 'alumni' | 'events' | 'campaigns' | 'users';

type Feature = 'alumni_portal';

type Quote = {
    monthly: number;
    yearly: number;
    base: number;
    lines: {
        resource: Resource;
        limit: number | null;
        blocks: number | null;
        amount: number;
    }[];
    features: {
        feature: Feature;
        label: string;
        enabled: boolean;
        amount: number;
    }[];
};

type Props = {
    organizations: { id: number; name: string }[];
    plans: {
        id: number;
        name: string;
        price_monthly: string;
        price_yearly: string;
    }[];
    rates: {
        base_monthly: number;
        units: Record<
            Resource,
            { per: number; price: number; unlimited: number }
        >;
        features: Record<
            Feature,
            { label: string; description: string; price: number }
        >;
        yearly_months: number;
    };
    quote: Quote;
    limits: Record<Resource, number | null>;
    features: Record<Feature, boolean>;
};

const resourceLabels: Record<Resource, string> = {
    alumni: 'Alumni',
    events: 'Evenements',
    campaigns: 'Campagnes',
    users: 'Utilisateurs',
};

function isoDate(monthsAhead = 0): string {
    const date = new Date();
    date.setMonth(date.getMonth() + monthsAhead);

    return date.toISOString().slice(0, 10);
}

function money(value: number): string {
    return value.toLocaleString('fr-FR', { minimumFractionDigits: 2 });
}

export default function SubscriptionCreate({
    organizations,
    plans,
    rates,
    quote,
    limits,
    features,
}: Props) {
    const [mode, setMode] = useState<'plan' | 'custom'>('plan');
    const [planId, setPlanId] = useState<string>(
        plans[0] ? String(plans[0].id) : '',
    );
    const [cycle, setCycle] = useState<'monthly' | 'yearly'>('monthly');
    const [endsAt, setEndsAt] = useState(isoDate(1));

    const chosenPlan = plans.find((plan) => String(plan.id) === planId);

    const reprice = (
        next: Partial<Record<Resource, string>>,
        nextFeatures: Partial<Record<Feature, boolean>> = {},
    ) => {
        const merged: Record<string, string> = {};

        (Object.keys(resourceLabels) as Resource[]).forEach((resource) => {
            const value =
                next[resource] ??
                (limits[resource] === null ? '' : String(limits[resource]));

            if (value !== '') {
                merged[`limits[${resource}]`] = value;
            }
        });

        (Object.keys(rates.features) as Feature[]).forEach((feature) => {
            const enabled = nextFeatures[feature] ?? features[feature];

            if (enabled) {
                merged[`features[${feature}]`] = '1';
            }
        });

        router.get(create().url, merged, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['quote', 'limits', 'features'],
        });
    };

    const chooseCycle = (next: 'monthly' | 'yearly') => {
        setCycle(next);
        setEndsAt(isoDate(next === 'yearly' ? 12 : 1));
    };

    const price =
        mode === 'custom'
            ? cycle === 'yearly'
                ? quote.yearly
                : quote.monthly
            : chosenPlan
              ? Number(
                    cycle === 'yearly'
                        ? chosenPlan.price_yearly
                        : chosenPlan.price_monthly,
                )
              : null;

    return (
        <>
            <Head title="Attribuer un abonnement" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Attribuer un abonnement"
                    description="L abonnement actif precedent de cette organisation sera clos"
                />
                <Form
                    {...SubscriptionController.store.form()}
                    options={{ preserveScroll: true }}
                    className="max-w-[680px] space-y-8"
                >
                    {({
                        processing,
                        errors,
                    }: {
                        processing: boolean;
                        errors: Record<string, string>;
                    }) => (
                        <>
                            <input type="hidden" name="mode" value={mode} />
                            <section className="space-y-4">
                                <h2 className="text-text text-base font-semibold">
                                    Organisation
                                </h2>
                                <div className="grid gap-2">
                                    <Label htmlFor="organization_id">
                                        Organisation
                                        <span className="text-danger ml-0.5">
                                            *
                                        </span>
                                    </Label>
                                    <select
                                        id="organization_id"
                                        name="organization_id"
                                        required
                                        defaultValue=""
                                        className="border-border-strong bg-surface text-text h-12 rounded-md border px-3 text-sm"
                                    >
                                        <option value="" disabled>
                                            Choisir une organisation
                                        </option>
                                        {organizations.map((organization) => (
                                            <option
                                                key={organization.id}
                                                value={organization.id}
                                            >
                                                {organization.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.organization_id}
                                    />
                                </div>
                            </section>
                            <section className="space-y-4">
                                <h2 className="text-text text-base font-semibold">
                                    Formule
                                </h2>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <ChoiceTile
                                        icon={<Layers className="size-5" />}
                                        label="Plan du catalogue"
                                        description="Un des plans proposes a toutes les organisations"
                                        selected={mode === 'plan'}
                                        onSelect={() => setMode('plan')}
                                    />
                                    <ChoiceTile
                                        icon={
                                            <SlidersHorizontal className="size-5" />
                                        }
                                        label="Abonnement sur mesure"
                                        description="Des limites choisies pour cette organisation, prix calcule"
                                        selected={mode === 'custom'}
                                        onSelect={() => setMode('custom')}
                                    />
                                </div>
                                {mode === 'plan' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="plan_id">Plan</Label>
                                        <select
                                            id="plan_id"
                                            name="plan_id"
                                            value={planId}
                                            onChange={(event) =>
                                                setPlanId(event.target.value)
                                            }
                                            className="border-border-strong bg-surface text-text h-12 rounded-md border px-3 text-sm"
                                        >
                                            {plans.map((plan) => (
                                                <option
                                                    key={plan.id}
                                                    value={plan.id}
                                                >
                                                    {plan.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.plan_id} />
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Nom de l abonnement
                                                <span className="text-danger ml-0.5">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                placeholder="Sur mesure — Universite Hassan II"
                                                className="h-12"
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="border-border grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                            {(
                                                Object.keys(
                                                    resourceLabels,
                                                ) as Resource[]
                                            ).map((resource) => (
                                                <div
                                                    key={resource}
                                                    className="grid gap-1.5"
                                                >
                                                    <Label
                                                        htmlFor={`limit-${resource}`}
                                                    >
                                                        {
                                                            resourceLabels[
                                                                resource
                                                            ]
                                                        }
                                                    </Label>
                                                    <Input
                                                        id={`limit-${resource}`}
                                                        name={`limits[${resource}]`}
                                                        type="number"
                                                        min={1}
                                                        defaultValue={
                                                            limits[resource] ??
                                                            ''
                                                        }
                                                        placeholder="Illimite"
                                                        className="tabular h-10"
                                                        onBlur={(event) =>
                                                            reprice({
                                                                [resource]:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                    />
                                                    <span className="text-text-muted text-xs">
                                                        {
                                                            rates.units[
                                                                resource
                                                            ].price
                                                        }{' '}
                                                        USD par tranche de{' '}
                                                        {
                                                            rates.units[
                                                                resource
                                                            ].per
                                                        }
                                                        , ou{' '}
                                                        {
                                                            rates.units[
                                                                resource
                                                            ].unlimited
                                                        }{' '}
                                                        illimite
                                                    </span>
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `limits.${resource}`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                        <div className="border-border grid gap-3 rounded-lg border p-4">
                                            {(
                                                Object.keys(
                                                    rates.features,
                                                ) as Feature[]
                                            ).map((feature) => (
                                                <label
                                                    key={feature}
                                                    className="flex cursor-pointer items-start gap-3"
                                                >
                                                    <Checkbox
                                                        name={`features[${feature}]`}
                                                        value="1"
                                                        checked={
                                                            features[feature]
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            reprice(
                                                                {},
                                                                {
                                                                    [feature]:
                                                                        checked ===
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                        className="mt-0.5"
                                                    />
                                                    <span className="grid gap-0.5">
                                                        <span className="text-text text-sm font-medium">
                                                            {
                                                                rates.features[
                                                                    feature
                                                                ].label
                                                            }
                                                        </span>
                                                        <span className="text-text-muted text-xs">
                                                            {
                                                                rates.features[
                                                                    feature
                                                                ].description
                                                            }{' '}
                                                            —{' '}
                                                            {
                                                                rates.features[
                                                                    feature
                                                                ].price
                                                            }{' '}
                                                            USD / mois
                                                        </span>
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                        <div className="border-border bg-surface-muted rounded-lg border p-4">
                                            <p className="text-text text-sm font-medium">
                                                Prix calcule
                                            </p>
                                            <dl className="mt-3 space-y-1 text-sm">
                                                <Line
                                                    label="Base"
                                                    value={money(quote.base)}
                                                />
                                                {quote.lines.map((line) => (
                                                    <Line
                                                        key={line.resource}
                                                        label={`${resourceLabels[line.resource]} — ${
                                                            line.limit === null
                                                                ? 'illimite'
                                                                : `${line.limit} (${line.blocks} tranche${(line.blocks ?? 0) > 1 ? 's' : ''})`
                                                        }`}
                                                        value={money(
                                                            line.amount,
                                                        )}
                                                    />
                                                ))}
                                                {quote.features
                                                    .filter(
                                                        (line) => line.enabled,
                                                    )
                                                    .map((line) => (
                                                        <Line
                                                            key={line.feature}
                                                            label={line.label}
                                                            value={money(
                                                                line.amount,
                                                            )}
                                                        />
                                                    ))}
                                            </dl>
                                            <div className="border-border mt-3 flex items-baseline justify-between border-t pt-3">
                                                <span className="text-text text-sm font-semibold">
                                                    Total
                                                </span>
                                                <span className="text-text tabular text-lg font-semibold">
                                                    {money(quote.monthly)} USD /
                                                    mois
                                                </span>
                                            </div>
                                            <p className="text-text-muted tabular mt-1 text-right text-xs">
                                                {money(quote.yearly)} USD / an (
                                                {rates.yearly_months} mois
                                                factures)
                                            </p>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="price_override">
                                                Prix negocie (facultatif)
                                            </Label>
                                            <Input
                                                id="price_override"
                                                name="price_override"
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                placeholder={money(
                                                    cycle === 'yearly'
                                                        ? quote.yearly
                                                        : quote.monthly,
                                                )}
                                                className="tabular h-12"
                                            />
                                            <p className="text-text-muted text-xs">
                                                Laissez vide pour facturer le
                                                prix calcule.
                                            </p>
                                            <InputError
                                                message={errors.price_override}
                                            />
                                        </div>
                                    </div>
                                )}
                            </section>
                            <section className="space-y-4">
                                <h2 className="text-text text-base font-semibold">
                                    Periode
                                </h2>
                                <div className="grid gap-2">
                                    <Label htmlFor="billing_cycle">
                                        Cycle de facturation
                                    </Label>
                                    <select
                                        id="billing_cycle"
                                        name="billing_cycle"
                                        value={cycle}
                                        onChange={(event) =>
                                            chooseCycle(
                                                event.target.value as
                                                    | 'monthly'
                                                    | 'yearly',
                                            )
                                        }
                                        className="border-border-strong bg-surface text-text h-12 rounded-md border px-3 text-sm"
                                    >
                                        <option value="monthly">Mensuel</option>
                                        <option value="yearly">Annuel</option>
                                    </select>
                                    <InputError
                                        message={errors.billing_cycle}
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="starts_at">
                                            Date de debut
                                            <span className="text-danger ml-0.5">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="starts_at"
                                            name="starts_at"
                                            type="date"
                                            required
                                            defaultValue={isoDate()}
                                            className="h-12"
                                        />
                                        <InputError
                                            message={errors.starts_at}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="ends_at">
                                            Date de fin
                                            <span className="text-danger ml-0.5">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="ends_at"
                                            name="ends_at"
                                            type="date"
                                            required
                                            value={endsAt}
                                            onChange={(event) =>
                                                setEndsAt(event.target.value)
                                            }
                                            className="h-12"
                                        />
                                        <InputError message={errors.ends_at} />
                                    </div>
                                </div>
                                {price !== null && (
                                    <p className="text-text-secondary text-sm">
                                        Montant facture : {money(price)} USD (
                                        {cycle === 'yearly'
                                            ? 'annuel'
                                            : 'mensuel'}
                                        ).
                                    </p>
                                )}
                            </section>
                            <div className="border-border flex items-center justify-end gap-3 border-t pt-6">
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Annuler</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 w-full sm:w-auto"
                                >
                                    {processing && <Spinner />}
                                    Attribuer l abonnement
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

function Line({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-4">
            <dt className="text-text-secondary">{label}</dt>
            <dd className="text-text tabular">{value} USD</dd>
        </div>
    );
}

function ChoiceTile({
    icon,
    label,
    description,
    selected,
    onSelect,
}: {
    icon: ReactNode;
    label: string;
    description: string;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                'flex flex-col items-start gap-1 rounded-md border p-4 text-left transition',
                selected
                    ? 'border-accent bg-accent-soft'
                    : 'border-border hover:bg-surface-muted',
            )}
        >
            <span className={selected ? 'text-accent' : 'text-text-muted'}>
                {icon}
            </span>
            <span className="text-text text-sm font-medium">{label}</span>
            <span className="text-text-muted text-xs">{description}</span>
        </button>
    );
}

SubscriptionCreate.layout = {
    breadcrumbs: [
        { title: 'Abonnements', href: index() },
        { title: 'Attribuer', href: create() },
    ],
};
