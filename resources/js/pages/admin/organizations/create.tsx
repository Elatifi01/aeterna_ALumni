import { Form, Head, Link } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import OrganizationController from '@/actions/App/Http/Controllers/Admin/OrganizationController';
import InputError from '@/components/input-error';
import { LogoUploadField } from '@/components/logo-upload-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/admin/organizations';

type PlanOption = {
    id: number;
    name: string;
    price_monthly: string;
    price_yearly: string;
    is_free: boolean;
};

type Props = { plans: PlanOption[] };

const sections = ['Organisation', 'Administrateur', 'Abonnement'] as const;

export default function OrganizationCreate({ plans }: Props) {
    const [planId, setPlanId] = useState<string>('');
    const [cycle, setCycle] = useState<'monthly' | 'yearly'>('monthly');

    const chosen = plans.find((plan) => String(plan.id) === planId);

    return (
        <>
            <Head title="Creer une organisation" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Creer une organisation"
                    description="L organisation, son premier administrateur et son abonnement de depart"
                />
                <Form
                    {...OrganizationController.store.form()}
                    options={{ preserveScroll: true }}
                    className="max-w-[640px] space-y-8"
                >
                    {({
                        processing,
                        errors,
                    }: {
                        processing: boolean;
                        errors: Record<string, string>;
                    }) => (
                        <>
                            <Section index={0}>
                                <LogoUploadField error={errors.logo} />
                                <Field
                                    name="name"
                                    label="Nom de l organisation"
                                    required
                                    error={errors.name}
                                />
                                <Field
                                    name="email"
                                    label="E-mail de contact"
                                    type="email"
                                    required
                                    error={errors.email}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        name="phone"
                                        label="Telephone"
                                        error={errors.phone}
                                    />
                                    <Field
                                        name="address"
                                        label="Adresse"
                                        error={errors.address}
                                    />
                                </div>
                            </Section>
                            <Section index={1}>
                                <p className="text-text-secondary text-sm">
                                    Ce compte permettra a l organisation de se
                                    connecter pour la premiere fois.
                                </p>
                                <Field
                                    name="admin_name"
                                    label="Nom de l administrateur"
                                    required
                                    error={errors.admin_name}
                                />
                                <Field
                                    name="admin_email"
                                    label="E-mail de connexion"
                                    type="email"
                                    required
                                    error={errors.admin_email}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        name="admin_password"
                                        label="Mot de passe"
                                        type="password"
                                        required
                                        error={errors.admin_password}
                                    />
                                    <Field
                                        name="admin_password_confirmation"
                                        label="Confirmer"
                                        type="password"
                                        required
                                    />
                                </div>
                            </Section>
                            <Section index={2}>
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
                                        <option value="">
                                            Aucun abonnement pour le moment
                                        </option>
                                        {plans.map((plan) => (
                                            <option
                                                key={plan.id}
                                                value={plan.id}
                                            >
                                                {plan.is_free
                                                    ? `${plan.name} (essai gratuit)`
                                                    : plan.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.plan_id} />
                                </div>
                                {chosen?.is_free && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="trial_days">
                                            Durée de l essai (jours)
                                        </Label>
                                        <Input
                                            id="trial_days"
                                            name="trial_days"
                                            type="number"
                                            min={1}
                                            max={90}
                                            defaultValue={7}
                                            required
                                            className="h-12 max-w-40"
                                        />
                                        <p className="text-text-muted text-xs">
                                            À la fin de l essai, l espace de l
                                            organisation est bloqué jusqu à ce
                                            que vous le prolongiez ou lui
                                            attribuiez un plan payant.
                                        </p>
                                        <InputError
                                            message={errors.trial_days}
                                        />
                                    </div>
                                )}
                                {chosen !== undefined && !chosen.is_free && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="billing_cycle">
                                            Cycle de facturation
                                        </Label>
                                        <select
                                            id="billing_cycle"
                                            name="billing_cycle"
                                            value={cycle}
                                            onChange={(event) =>
                                                setCycle(
                                                    event.target.value as
                                                        | 'monthly'
                                                        | 'yearly',
                                                )
                                            }
                                            className="border-border-strong bg-surface text-text h-12 rounded-md border px-3 text-sm"
                                        >
                                            <option value="monthly">
                                                Mensuel
                                                {chosen
                                                    ? ` — ${chosen.price_monthly} USD`
                                                    : ''}
                                            </option>
                                            <option value="yearly">
                                                Annuel
                                                {chosen
                                                    ? ` — ${chosen.price_yearly} USD`
                                                    : ''}
                                            </option>
                                        </select>
                                        <InputError
                                            message={errors.billing_cycle}
                                        />
                                    </div>
                                )}
                            </Section>
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
                                    Creer l organisation
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

OrganizationCreate.layout = {
    breadcrumbs: [
        { title: 'Organisations', href: index() },
        { title: 'Creer', href: create() },
    ],
};

function Section({
    index: position,
    children,
}: {
    index: number;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4">
            <div className="flex items-center gap-3">
                <span
                    aria-hidden
                    className="bg-accent text-accent-contrast tabular flex size-7 items-center justify-center rounded-full text-sm font-semibold"
                >
                    {position + 1}
                </span>
                <h2 className="text-text text-base font-semibold">
                    {sections[position]}
                </h2>
            </div>
            {children}
        </section>
    );
}

function Field({
    name,
    label,
    type = 'text',
    required,
    error,
    className,
}: {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
    error?: string;
    className?: string;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={name}>
                {label}
                {required && <span className="text-danger ml-0.5">*</span>}
            </Label>
            <Input
                id={name}
                name={name}
                type={type}
                required={required}
                className="h-12"
                aria-invalid={error ? true : undefined}
            />
            <InputError message={error} />
        </div>
    );
}
