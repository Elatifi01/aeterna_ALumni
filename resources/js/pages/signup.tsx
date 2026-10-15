import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, CreditCard, Info, Lock, Minus } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import {
    features,
    formatPrice,
    monthsSaved,
    priceFor,
    type Cycle,
    type PublicPlan,
} from '@/components/landing/plans';
import { Spinner } from '@/components/ui/spinner';
import { home, login } from '@/routes';
import { store } from '@/routes/signup';

type Props = { plan: PublicPlan; cycle: Cycle };

const inputClass =
    'h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm text-[#0c1e33] outline-none transition-[border-color,box-shadow] duration-150 ease-out placeholder:text-slate-400 focus:border-[#638fe3] focus:ring-4 focus:ring-[#638fe3]/15 aria-invalid:border-rose-400';

function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <label
                htmlFor={id}
                className="text-sm font-semibold text-[#0c1e33]"
            >
                {label}
            </label>
            {children}
            {hint && !error && <p className="text-xs text-slate-500">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Step({
    number,
    title,
    children,
}: {
    number: number;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-[28px] bg-white p-6 ring-1 ring-slate-200/70 sm:p-8">
            <h2 className="flex items-center gap-3 text-lg font-bold text-[#0c1e33]">
                <span className="tabular flex size-8 items-center justify-center rounded-full bg-[#0c1e33] text-sm text-white">
                    {number}
                </span>
                {title}
            </h2>
            <div className="mt-6 grid gap-5">{children}</div>
        </section>
    );
}

export default function Signup({ plan, cycle: initialCycle }: Props) {
    const [cycle, setCycle] = useState<Cycle>(initialCycle);
    const [cardNumber, setCardNumber] = useState('');
    const [expiry, setExpiry] = useState('');

    const price = priceFor(plan, cycle);
    const isPaid = price > 0;

    const formatCard = (value: string) =>
        setCardNumber(
            value
                .replace(/\D/g, '')
                .slice(0, 19)
                .replace(/(.{4})(?=.)/g, '$1 '),
        );

    const formatExpiry = (value: string) => {
        const digits = value.replace(/\D/g, '').slice(0, 4);
        setExpiry(
            digits.length > 2
                ? `${digits.slice(0, 2)}/${digits.slice(2)}`
                : digits,
        );
    };

    return (
        <>
            <Head title={`Inscription — ${plan.name}`} />

            <div className="min-h-screen bg-[#eaecf2] font-sans font-normal text-[#0c1e33] antialiased">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
                    <Link href={home()} className="flex items-center gap-2">
                        <span className="flex size-9 items-center justify-center rounded-full bg-[#0c1e33] text-white">
                            <AppLogoIcon className="size-5" />
                        </span>
                        <span className="text-lg font-bold tracking-tight">
                            Aeterna
                        </span>
                    </Link>
                    <p className="text-sm text-slate-600">
                        Déjà inscrit ?{' '}
                        <Link
                            href={login()}
                            className="font-semibold text-[#16375A] underline-offset-4 hover:underline"
                        >
                            Se connecter
                        </Link>
                    </p>
                </header>

                <main className="mx-auto max-w-6xl px-4 pb-20 sm:px-6">
                    <Link
                        href={`${home().url}#tarifs`}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 transition-colors duration-150 hover:text-[#0c1e33]"
                    >
                        <ArrowLeft className="size-4" />
                        Voir toutes les formules
                    </Link>
                    <h1 className="mt-4 text-3xl font-extrabold tracking-[-0.02em] sm:text-4xl">
                        Créez votre espace Aeterna
                    </h1>
                    <p className="mt-2 text-slate-600">
                        Quelques informations, et votre établissement est prêt à
                        accueillir ses diplômés.
                    </p>

                    <Form
                        {...store.form({ plan: plan.slug })}
                        options={{ preserveScroll: true }}
                        className="mt-8 grid items-start gap-6 lg:grid-cols-[1fr_380px]"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="billing_cycle"
                                    value={cycle}
                                />

                                <div className="grid gap-6">
                                    <Step
                                        number={1}
                                        title="Votre établissement"
                                    >
                                        <Field
                                            id="organization_name"
                                            label="Nom de l’établissement"
                                            error={errors.organization_name}
                                        >
                                            <input
                                                id="organization_name"
                                                name="organization_name"
                                                required
                                                autoComplete="organization"
                                                placeholder="École Supérieure de Tanger"
                                                className={inputClass}
                                                aria-invalid={
                                                    errors.organization_name
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                        </Field>
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field
                                                id="organization_email"
                                                label="E-mail de contact"
                                                error={
                                                    errors.organization_email
                                                }
                                            >
                                                <input
                                                    id="organization_email"
                                                    name="organization_email"
                                                    type="email"
                                                    required
                                                    placeholder="contact@etablissement.ma"
                                                    className={inputClass}
                                                    aria-invalid={
                                                        errors.organization_email
                                                            ? true
                                                            : undefined
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id="organization_phone"
                                                label="Téléphone (facultatif)"
                                                error={
                                                    errors.organization_phone
                                                }
                                            >
                                                <input
                                                    id="organization_phone"
                                                    name="organization_phone"
                                                    type="tel"
                                                    placeholder="+212 5 00 00 00 00"
                                                    className={inputClass}
                                                />
                                            </Field>
                                        </div>
                                    </Step>

                                    <Step
                                        number={2}
                                        title="Votre compte administrateur"
                                    >
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field
                                                id="admin_name"
                                                label="Nom complet"
                                                error={errors.admin_name}
                                            >
                                                <input
                                                    id="admin_name"
                                                    name="admin_name"
                                                    required
                                                    autoComplete="name"
                                                    placeholder="Youssef Alaoui"
                                                    className={inputClass}
                                                    aria-invalid={
                                                        errors.admin_name
                                                            ? true
                                                            : undefined
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id="admin_email"
                                                label="E-mail de connexion"
                                                error={errors.admin_email}
                                            >
                                                <input
                                                    id="admin_email"
                                                    name="admin_email"
                                                    type="email"
                                                    required
                                                    autoComplete="email"
                                                    placeholder="vous@etablissement.ma"
                                                    className={inputClass}
                                                    aria-invalid={
                                                        errors.admin_email
                                                            ? true
                                                            : undefined
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id="admin_password"
                                                label="Mot de passe"
                                                error={errors.admin_password}
                                                hint="8 caractères minimum"
                                            >
                                                <input
                                                    id="admin_password"
                                                    name="admin_password"
                                                    type="password"
                                                    required
                                                    minLength={8}
                                                    autoComplete="new-password"
                                                    className={inputClass}
                                                    aria-invalid={
                                                        errors.admin_password
                                                            ? true
                                                            : undefined
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id="admin_password_confirmation"
                                                label="Confirmez le mot de passe"
                                            >
                                                <input
                                                    id="admin_password_confirmation"
                                                    name="admin_password_confirmation"
                                                    type="password"
                                                    required
                                                    autoComplete="new-password"
                                                    className={inputClass}
                                                />
                                            </Field>
                                        </div>
                                    </Step>

                                    {isPaid && (
                                        <Step number={3} title="Paiement">
                                            <p className="flex gap-2.5 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                                                <Info className="mt-0.5 size-4 shrink-0" />
                                                Mode démonstration : la carte
                                                est vérifiée mais aucun montant
                                                n’est réellement débité.
                                            </p>
                                            <Field
                                                id="card_holder"
                                                label="Titulaire de la carte"
                                                error={errors.card_holder}
                                            >
                                                <input
                                                    id="card_holder"
                                                    name="card_holder"
                                                    required
                                                    autoComplete="cc-name"
                                                    placeholder="YOUSSEF ALAOUI"
                                                    className={`${inputClass} uppercase`}
                                                    aria-invalid={
                                                        errors.card_holder
                                                            ? true
                                                            : undefined
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id="card_number"
                                                label="Numéro de carte"
                                                error={errors.card_number}
                                            >
                                                <div className="relative">
                                                    <input
                                                        id="card_number"
                                                        name="card_number"
                                                        required
                                                        inputMode="numeric"
                                                        autoComplete="cc-number"
                                                        placeholder="4242 4242 4242 4242"
                                                        value={cardNumber}
                                                        onChange={(event) =>
                                                            formatCard(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className={`${inputClass} tabular pr-12`}
                                                        aria-invalid={
                                                            errors.card_number
                                                                ? true
                                                                : undefined
                                                        }
                                                    />
                                                    <CreditCard className="pointer-events-none absolute top-1/2 right-4 size-5 -translate-y-1/2 text-slate-400" />
                                                </div>
                                            </Field>
                                            <div className="grid grid-cols-2 gap-5">
                                                <Field
                                                    id="card_expiry"
                                                    label="Expiration"
                                                    error={errors.card_expiry}
                                                >
                                                    <input
                                                        id="card_expiry"
                                                        name="card_expiry"
                                                        required
                                                        inputMode="numeric"
                                                        autoComplete="cc-exp"
                                                        placeholder="MM/AA"
                                                        value={expiry}
                                                        onChange={(event) =>
                                                            formatExpiry(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className={`${inputClass} tabular`}
                                                        aria-invalid={
                                                            errors.card_expiry
                                                                ? true
                                                                : undefined
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    id="card_cvc"
                                                    label="Code de sécurité"
                                                    error={errors.card_cvc}
                                                >
                                                    <input
                                                        id="card_cvc"
                                                        name="card_cvc"
                                                        required
                                                        inputMode="numeric"
                                                        maxLength={4}
                                                        autoComplete="cc-csc"
                                                        placeholder="123"
                                                        className={`${inputClass} tabular`}
                                                        aria-invalid={
                                                            errors.card_cvc
                                                                ? true
                                                                : undefined
                                                        }
                                                    />
                                                </Field>
                                            </div>
                                        </Step>
                                    )}
                                </div>

                                {/* Order summary */}
                                <aside className="rounded-[32px] bg-[#0c1e33] p-7 text-white lg:sticky lg:top-6">
                                    <p className="text-xs font-semibold tracking-wider text-[#90b6f7] uppercase">
                                        Votre formule
                                    </p>
                                    <h2 className="mt-1 text-2xl font-bold">
                                        {plan.name}
                                    </h2>

                                    <div className="mt-5 grid grid-cols-2 rounded-full bg-white/10 p-1">
                                        {(['monthly', 'yearly'] as const).map(
                                            (value) => (
                                                <button
                                                    key={value}
                                                    type="button"
                                                    onClick={() =>
                                                        setCycle(value)
                                                    }
                                                    aria-pressed={
                                                        cycle === value
                                                    }
                                                    className={`h-9 cursor-pointer rounded-full text-sm font-semibold transition-[background-color,color] duration-200 ease-out ${
                                                        cycle === value
                                                            ? 'bg-white text-[#0c1e33]'
                                                            : 'text-white/70 hover:text-white'
                                                    }`}
                                                >
                                                    {value === 'monthly'
                                                        ? 'Mensuel'
                                                        : 'Annuel'}
                                                </button>
                                            ),
                                        )}
                                    </div>

                                    <ul className="mt-6 space-y-2.5 text-sm">
                                        {features(plan).map(
                                            ({ label, included }) => (
                                                <li
                                                    key={label}
                                                    className={`flex items-center gap-2.5 ${included ? 'text-white/90' : 'text-white/35'}`}
                                                >
                                                    {included ? (
                                                        <Check className="size-4 text-[#90b6f7]" />
                                                    ) : (
                                                        <Minus className="size-4" />
                                                    )}
                                                    {label}
                                                </li>
                                            ),
                                        )}
                                    </ul>

                                    <div className="mt-6 border-t border-white/10 pt-5">
                                        <div className="flex items-end justify-between">
                                            <span className="text-sm text-white/70">
                                                Total{' '}
                                                {cycle === 'yearly'
                                                    ? 'annuel'
                                                    : 'mensuel'}
                                            </span>
                                            <span className="tabular text-3xl font-extrabold">
                                                {isPaid
                                                    ? formatPrice(price)
                                                    : 'Gratuit'}
                                            </span>
                                        </div>
                                        {cycle === 'yearly' &&
                                            monthsSaved(plan) > 0 && (
                                                <p className="mt-1 text-right text-xs text-emerald-300">
                                                    {monthsSaved(plan)} mois
                                                    offerts par rapport au
                                                    mensuel
                                                </p>
                                            )}
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-6 inline-flex h-12 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-white text-sm font-bold text-[#0c1e33] transition-[background-color,transform] duration-150 ease-out hover:bg-[#dbe7fd] active:scale-[0.97] disabled:opacity-60"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            isPaid && (
                                                <Lock className="size-4" />
                                            )
                                        )}
                                        {isPaid
                                            ? `Payer ${formatPrice(price)} et créer mon espace`
                                            : 'Créer mon espace gratuitement'}
                                    </button>
                                    <p className="mt-3 text-center text-xs text-white/50">
                                        Votre espace est actif dès la
                                        validation.
                                    </p>
                                </aside>
                            </>
                        )}
                    </Form>
                </main>
            </div>
        </>
    );
}
