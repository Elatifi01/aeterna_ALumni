import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/admin/plans';
import type { PlanRow } from '@/pages/admin/plans/index';

type Props = {
    action: Record<string, unknown>;
    plan?: PlanRow;
    submitLabel: string;
};

const limitFields = [
    ['max_alumni', 'Alumni'],
    ['max_events', 'Evenements'],
    ['max_campaigns', 'Campagnes'],
    ['max_users', 'Utilisateurs'],
] as const;

export function PlanForm({ action, plan, submitLabel }: Props) {
    return (
        <Form
            {...action}
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
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Identification
                        </h2>
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                Nom
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                defaultValue={plan?.name}
                                className="h-12"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                name="description"
                                defaultValue={plan?.description ?? ''}
                                className="h-12"
                            />
                            <InputError message={errors.description} />
                        </div>
                        <label className="flex items-center gap-2.5 text-sm">
                            <Checkbox
                                name="is_active"
                                value="1"
                                defaultChecked={plan?.is_active ?? true}
                            />
                            <span className="text-text-secondary">
                                Plan actif et attribuable
                            </span>
                        </label>
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Tarifs
                        </h2>
                        <p className="text-text-secondary text-sm">
                            Montants en USD : Paddle ne prend pas en charge le
                            dirham marocain.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="price_monthly">
                                    Prix mensuel
                                    <span className="text-danger ml-0.5">
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="price_monthly"
                                    name="price_monthly"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    required
                                    defaultValue={plan?.price_monthly ?? '0'}
                                    className="tabular h-12"
                                />
                                <InputError message={errors.price_monthly} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="price_yearly">
                                    Prix annuel
                                    <span className="text-danger ml-0.5">
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="price_yearly"
                                    name="price_yearly"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    required
                                    defaultValue={plan?.price_yearly ?? '0'}
                                    className="tabular h-12"
                                />
                                <InputError message={errors.price_yearly} />
                            </div>
                        </div>
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Limites
                        </h2>
                        <p className="text-text-secondary text-sm">
                            Laissez un champ vide pour un nombre illimite.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {limitFields.map(([name, label]) => (
                                <div key={name} className="grid gap-2">
                                    <Label htmlFor={name}>{label}</Label>
                                    <Input
                                        id={name}
                                        name={name}
                                        type="number"
                                        min={0}
                                        defaultValue={plan?.[name] ?? ''}
                                        placeholder="Illimite"
                                        className="tabular h-12"
                                    />
                                    <InputError message={errors[name]} />
                                </div>
                            ))}
                        </div>
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Paddle
                        </h2>
                        <p className="text-text-secondary text-sm">
                            Identifiants de prix crees dans le tableau de bord
                            Paddle. Sans eux, le plan ne peut pas etre achete en
                            ligne.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="paddle_price_monthly_id">
                                    Prix mensuel Paddle
                                </Label>
                                <Input
                                    id="paddle_price_monthly_id"
                                    name="paddle_price_monthly_id"
                                    defaultValue={
                                        plan?.paddle_price_monthly_id ?? ''
                                    }
                                    placeholder="pri_..."
                                    className="h-12"
                                />
                                <InputError
                                    message={errors.paddle_price_monthly_id}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="paddle_price_yearly_id">
                                    Prix annuel Paddle
                                </Label>
                                <Input
                                    id="paddle_price_yearly_id"
                                    name="paddle_price_yearly_id"
                                    defaultValue={
                                        plan?.paddle_price_yearly_id ?? ''
                                    }
                                    placeholder="pri_..."
                                    className="h-12"
                                />
                                <InputError
                                    message={errors.paddle_price_yearly_id}
                                />
                            </div>
                        </div>
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
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
