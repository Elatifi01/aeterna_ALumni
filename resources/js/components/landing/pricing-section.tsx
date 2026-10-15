import { Link } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import { useState } from 'react';
import {
    features,
    formatPrice,
    monthsSaved,
    priceFor,
    type ContactSubject,
    type Cycle,
    type PublicPlan,
} from '@/components/landing/plans';
import { create as signup } from '@/routes/signup';

/**
 * The plans on the landing page, each one a direct path to a paid, ready organization.
 */
export function PricingSection({
    plans,
    onContact,
}: {
    plans: PublicPlan[];
    onContact: (subject: ContactSubject) => void;
}) {
    const [cycle, setCycle] = useState<Cycle>('monthly');
    const bestSaving = Math.max(0, ...plans.map(monthsSaved));
    // The most expensive offered plan is the one we highlight.
    const featured = plans.reduce<PublicPlan | null>(
        (best, plan) =>
            best === null ||
            Number(plan.price_monthly) > Number(best.price_monthly)
                ? plan
                : best,
        null,
    );

    // Every plan from the catalogue, plus the custom offer.
    const cards = plans.length + 1;

    return (
        <section id="tarifs" className="scroll-mt-24 bg-[#eaecf2]">
            <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6">
                <div data-reveal className="text-center">
                    <h2 className="text-3xl font-extrabold tracking-[-0.02em] text-[#0c1e33] sm:text-[2.6rem]">
                        Des tarifs simples, sans engagement caché
                    </h2>
                    <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-[#16375A]/85">
                        Réservez une démo pour bénéficier d’un essai gratuit, ou
                        choisissez votre formule : votre espace est prêt
                        immédiatement.
                    </p>

                    {/* Monthly / yearly switch */}
                    <div className="mt-8 inline-flex items-center rounded-full bg-white p-1 shadow-sm ring-1 ring-slate-200/70">
                        {(['monthly', 'yearly'] as const).map((value) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setCycle(value)}
                                aria-pressed={cycle === value}
                                className={`relative h-10 cursor-pointer rounded-full px-5 text-sm font-semibold transition-[background-color,color] duration-200 ease-out ${
                                    cycle === value
                                        ? 'bg-[#0c1e33] text-white'
                                        : 'text-slate-600 hover:text-[#0c1e33]'
                                }`}
                            >
                                {value === 'monthly' ? 'Mensuel' : 'Annuel'}
                                {value === 'yearly' && bestSaving > 0 && (
                                    <span
                                        className={`ml-2 rounded-full px-2 py-0.5 text-[11px] font-bold ${
                                            cycle === 'yearly'
                                                ? 'bg-white/15 text-white'
                                                : 'bg-emerald-500/15 text-emerald-700'
                                        }`}
                                    >
                                        {bestSaving} mois offerts
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                <div
                    className={`mx-auto mt-12 grid gap-6 ${
                        cards === 2
                            ? 'max-w-4xl md:grid-cols-2'
                            : 'md:grid-cols-2 lg:grid-cols-3'
                    }`}
                >
                    {plans.map((plan) => {
                        const isFeatured =
                            plans.length > 1 && plan.slug === featured?.slug;
                        const price = priceFor(plan, cycle);
                        // The free plan is a demo trial, granted after a demo rather than bought here.
                        const free =
                            Number(plan.price_monthly) <= 0 &&
                            Number(plan.price_yearly) <= 0;

                        return (
                            <article
                                key={plan.slug}
                                data-reveal
                                className={`relative flex flex-col rounded-[40px] p-8 sm:p-9 ${
                                    isFeatured
                                        ? 'bg-[#0c1e33] text-white shadow-[0_40px_80px_-30px_rgba(12,30,51,0.6)]'
                                        : 'bg-white text-[#0c1e33] ring-1 ring-slate-200/70'
                                }`}
                            >
                                {isFeatured && (
                                    <span className="absolute -top-3 left-8 rounded-full bg-[linear-gradient(90deg,#638fe3,#5ccfe6)] px-3 py-1 text-xs font-bold text-white">
                                        Le plus choisi
                                    </span>
                                )}
                                <h3 className="text-2xl font-bold">
                                    {plan.name}
                                </h3>
                                {plan.description && (
                                    <p
                                        className={`mt-2 min-h-[4.25rem] text-sm leading-relaxed ${isFeatured ? 'text-white/70' : 'text-slate-500'}`}
                                    >
                                        {plan.description}
                                    </p>
                                )}

                                <div className="mt-6 flex h-14 items-end gap-2">
                                    <span
                                        className={`font-extrabold tracking-tight whitespace-nowrap ${free ? 'text-4xl' : 'tabular text-5xl'}`}
                                    >
                                        {free
                                            ? 'Essai gratuit'
                                            : formatPrice(price)}
                                    </span>
                                    {!free && (
                                        <span
                                            className={`pb-1.5 text-sm ${isFeatured ? 'text-white/60' : 'text-slate-500'}`}
                                        >
                                            /{' '}
                                            {cycle === 'yearly' ? 'an' : 'mois'}
                                        </span>
                                    )}
                                </div>
                                <p
                                    className={`mt-1 min-h-8 text-xs leading-snug ${isFeatured ? 'text-[#90b6f7]' : 'text-[#638fe3]'}`}
                                >
                                    {free
                                        ? 'Offert après votre démo, pour une durée convenue ensemble'
                                        : cycle === 'yearly' &&
                                            monthsSaved(plan) > 0
                                          ? `Soit ${formatPrice(Number(plan.price_yearly) / 12)} par mois, ${monthsSaved(plan)} mois offerts`
                                          : ''}
                                </p>

                                <ul className="mt-6 flex-1 space-y-3">
                                    {features(plan).map(
                                        ({ label, included }) => (
                                            <li
                                                key={label}
                                                className={`flex items-center gap-3 text-[15px] ${
                                                    included
                                                        ? ''
                                                        : isFeatured
                                                          ? 'text-white/40'
                                                          : 'text-slate-400'
                                                }`}
                                            >
                                                <span
                                                    className={`flex size-5 shrink-0 items-center justify-center rounded-full ${
                                                        included
                                                            ? isFeatured
                                                                ? 'bg-[#638fe3] text-white'
                                                                : 'bg-[#638fe3]/15 text-[#638fe3]'
                                                            : isFeatured
                                                              ? 'bg-white/10'
                                                              : 'bg-slate-100'
                                                    }`}
                                                >
                                                    {included ? (
                                                        <Check
                                                            className="size-3"
                                                            strokeWidth={3}
                                                        />
                                                    ) : (
                                                        <Minus className="size-3" />
                                                    )}
                                                </span>
                                                {label}
                                            </li>
                                        ),
                                    )}
                                </ul>

                                {free ? (
                                    <a
                                        href="#contact"
                                        onClick={() => onContact('demo')}
                                        className={`mt-8 inline-flex h-12 items-center justify-center rounded-xl text-sm font-semibold transition-[background-color,transform] duration-150 ease-out active:scale-[0.97] ${
                                            isFeatured
                                                ? 'bg-white text-[#0c1e33] hover:bg-[#dbe7fd]'
                                                : 'bg-[#0c1e33] text-white hover:bg-[#16375A]'
                                        }`}
                                    >
                                        Réserver une démo
                                    </a>
                                ) : (
                                    <Link
                                        href={signup(
                                            { plan: plan.slug },
                                            { query: { cycle } },
                                        )}
                                        className={`mt-8 inline-flex h-12 items-center justify-center rounded-xl text-sm font-semibold transition-[background-color,transform] duration-150 ease-out active:scale-[0.97] ${
                                            isFeatured
                                                ? 'bg-white text-[#0c1e33] hover:bg-[#dbe7fd]'
                                                : 'bg-[#0c1e33] text-white hover:bg-[#16375A]'
                                        }`}
                                    >
                                        Choisir {plan.name}
                                    </Link>
                                )}
                            </article>
                        );
                    })}

                    <CustomPlanCard onContact={onContact} />
                </div>
            </div>
        </section>
    );
}

const customFeatures = [
    'Volumes d’alumni, d’événements et de campagnes adaptés',
    'Nombre d’utilisateurs à la carte',
    'Portail diplômé et offres d’emploi',
    'Rôles et permissions sur mesure',
    'Paiement mensuel ou annuel',
];

/**
 * The custom offer: no catalogue price, the platform team builds the plan with the institution.
 */
function CustomPlanCard({
    onContact,
}: {
    onContact: (subject: ContactSubject) => void;
}) {
    return (
        <article
            data-reveal
            className="relative flex flex-col rounded-[40px] bg-white p-8 text-[#0c1e33] ring-1 ring-slate-200/70 sm:p-9"
        >
            <span className="absolute -top-3 left-8 rounded-full bg-[linear-gradient(90deg,#b9a6f5,#638fe3)] px-3 py-1 text-xs font-bold text-white">
                Grands réseaux
            </span>
            <h3 className="text-2xl font-bold">Sur mesure</h3>
            <p className="mt-2 min-h-[4.25rem] text-sm leading-relaxed text-slate-500">
                Pour les grands établissements et les réseaux : une formule
                construite avec vous.
            </p>

            <div className="mt-6 flex h-14 items-end gap-2">
                <span className="text-4xl font-extrabold tracking-tight whitespace-nowrap">
                    Sur devis
                </span>
            </div>
            <p className="mt-1 min-h-8 text-xs leading-snug text-[#638fe3]">
                Un tarif calculé selon vos volumes réels
            </p>

            <ul className="mt-6 flex-1 space-y-3">
                {customFeatures.map((label) => (
                    <li
                        key={label}
                        className="flex items-center gap-3 text-[15px]"
                    >
                        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-[#638fe3]/15 text-[#638fe3]">
                            <Check className="size-3" strokeWidth={3} />
                        </span>
                        {label}
                    </li>
                ))}
            </ul>

            <a
                href="#contact"
                onClick={() => onContact('custom')}
                className="mt-8 inline-flex h-12 items-center justify-center rounded-xl bg-[#0c1e33] text-sm font-semibold text-white transition-[background-color,transform] duration-150 ease-out hover:bg-[#16375A] active:scale-[0.97]"
            >
                Nous contacter
            </a>
        </article>
    );
}
