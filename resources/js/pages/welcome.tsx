import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    Building2,
    CalendarCheck,
    CheckCircle2,
    Factory,
    GraduationCap,
    Landmark,
    Mail,
    MessageCircle,
    School,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import {
    BriefcaseMascot,
    CursorMascot,
    FacesCluster,
    FolderMascot,
    SparkMascot,
    TicketMascot,
} from '@/components/landing/mascots';
import type { ContactSubject, PublicPlan } from '@/components/landing/plans';
import { PricingSection } from '@/components/landing/pricing-section';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store as storeDemoRequest } from '@/routes/demo-requests';
import type { SharedData } from '@/types';

/* -------------------------------------------------------------------------
   Content — every claim maps to something Aeterna ships today.
   ------------------------------------------------------------------------- */

const toolCards = [
    {
        title: 'Base alumni',
        items: [
            'Fiches de diplômés complètes et à jour',
            'Import depuis Excel ou CSV',
            'Recherche et filtres par promotion',
            'Export de la liste des alumni',
            'Corbeille avec restauration',
            'Tableau de bord et statistiques',
        ],
    },
    {
        title: 'Portail diplômé',
        items: [
            'Événements et inscription en ligne',
            'Messagerie et notifications',
            'Offres d’emploi et candidatures',
            'Aeterna Assistant, l’IA du portail',
            'Profil géré par le diplômé',
        ],
    },
    {
        title: 'Espace administration',
        items: [
            'Gestion des événements et de la présence',
            'Campagnes e-mail ciblées',
            'Utilisateurs, rôles et permissions sur mesure',
            'Accès au portail ouvert ou fermé',
            'Abonnement et paiement en ligne',
        ],
    },
];

const stories: {
    title: string;
    body: string;
    Mascot: (props: { className?: string }) => ReactNode;
}[] = [
    {
        title: 'Centralisez des données alumni toujours à jour',
        body: 'Coordonnées, promotion, filière, parcours et participation aux événements réunis dans une seule base. Importez vos fichiers existants en quelques minutes, puis segmentez vos diplômés pour des actions vraiment ciblées.',
        Mascot: FolderMascot,
    },
    {
        title: 'Organisez rencontres et événements',
        body: 'Publiez une soirée des anciens, un forum ou un webinaire : vos diplômés sont prévenus dans leur portail et s’inscrivent en un clic. Suivez les inscriptions et pointez la présence le jour J.',
        Mascot: TicketMascot,
    },
    {
        title: 'Communiquez avec chaque promotion',
        body: 'Envoyez des campagnes e-mail à une promotion, une filière ou à tout votre réseau. Chaque message arrive aussi dans la boîte de réception du portail, pour que personne ne manque une information importante.',
        Mascot: FacesCluster,
    },
    {
        title: 'Accompagnez leur carrière',
        body: 'Le portail propose des offres d’emploi que vos diplômés peuvent enregistrer et suivre. Vous restez utile bien après la remise des diplômes.',
        Mascot: BriefcaseMascot,
    },
    {
        title: 'Un assistant IA pour votre équipe et vos diplômés',
        body: 'Aeterna Assistant répond aux questions du quotidien, aide à rédiger une campagne et guide les diplômés dans leur portail, en restant dans le périmètre de votre établissement.',
        Mascot: SparkMascot,
    },
];

const audiences: { label: string; icon: LucideIcon }[] = [
    { label: 'Universités', icon: Landmark },
    { label: 'Écoles d’ingénieurs', icon: Factory },
    { label: 'Écoles de commerce', icon: Building2 },
    { label: 'Centres de formation', icon: BookOpen },
    { label: 'Lycées et classes prépa', icon: School },
    { label: 'Associations d’anciens', icon: Users },
    { label: 'Instituts spécialisés', icon: GraduationCap },
    { label: 'Réseaux d’établissements', icon: CalendarCheck },
];

/* -------------------------------------------------------------------------
   Motion — sections rise in once as they enter the viewport.
   ------------------------------------------------------------------------- */

function useReveal() {
    const root = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const nodes =
            root.current?.querySelectorAll<HTMLElement>('[data-reveal]');

        if (
            !nodes ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            nodes?.forEach((node) => node.setAttribute('data-shown', ''));

            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.setAttribute('data-shown', '');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: '0px 0px -10% 0px' },
        );

        nodes.forEach((node) => observer.observe(node));

        return () => observer.disconnect();
    }, []);

    return root;
}

/* -------------------------------------------------------------------------
   Building blocks
   ------------------------------------------------------------------------- */

const display = 'font-extrabold tracking-[-0.02em] text-[#0c1e33]';

const messagePlaceholders: Record<ContactSubject, string> = {
    demo: 'Combien de diplômés suivez-vous ? Qu’attendez-vous d’Aeterna ?',
    custom: 'Nombre de diplômés, d’utilisateurs, d’établissements… Dites-nous ce dont vous avez besoin.',
    other: 'Votre question',
};

function DemoButton({
    className = '',
    onClick,
}: {
    className?: string;
    onClick: () => void;
}) {
    return (
        <a
            href="#contact"
            onClick={onClick}
            className={`inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#0c1e33] px-7 text-sm font-semibold text-white shadow-lg shadow-[#0c1e33]/15 transition-[background-color,transform] duration-150 ease-out hover:bg-[#16375A] active:scale-[0.97] ${className}`}
        >
            Demander une démo
        </a>
    );
}

function Field({
    id,
    label,
    required = false,
    error,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <label
                htmlFor={id}
                className="text-sm font-semibold text-[#0c1e33]"
            >
                {label}
                {required && (
                    <span className="ml-1 text-xs font-normal text-[#638fe3] italic">
                        (obligatoire)
                    </span>
                )}
            </label>
            {children}
            <InputError message={error} />
        </div>
    );
}

const inputClass =
    'h-12 w-full rounded-xl border-[1.5px] border-[#0c1e33]/80 bg-[#f6f8fc] px-4 text-sm text-[#0c1e33] outline-none transition-[border-color,box-shadow] duration-150 ease-out placeholder:text-slate-400 focus:border-[#638fe3] focus:ring-4 focus:ring-[#638fe3]/15';

/* -------------------------------------------------------------------------
   Page
   ------------------------------------------------------------------------- */

export default function Welcome({ plans }: { plans: PublicPlan[] }) {
    const root = useReveal();
    // What the contact form is about; every button that leads to it sets it first.
    const [subject, setSubject] = useState<ContactSubject>('demo');
    const askDemo = () => setSubject('demo');
    const contact = (value: ContactSubject) => {
        setSubject(value);
        window.setTimeout(
            () =>
                document.getElementById('name')?.focus({ preventScroll: true }),
            400,
        );
    };
    const { flash } = usePage<
        SharedData & { flash?: { success?: string | null } }
    >().props;

    return (
        <>
            <Head title="Aeterna — la plateforme alumni des établissements">
                <meta
                    name="description"
                    content="Aeterna aide les universités et écoles à garder le lien avec leurs diplômés : base alumni, événements, campagnes, portail diplômé et offres d’emploi."
                />
            </Head>

            <style>{`
                [data-reveal] { opacity: 0; transform: translateY(18px); transition: opacity 600ms cubic-bezier(0.22, 1, 0.36, 1), transform 600ms cubic-bezier(0.22, 1, 0.36, 1); }
                [data-reveal][data-shown] { opacity: 1; transform: none; }
                html { scroll-behavior: smooth; }
                @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
            `}</style>

            <div
                ref={root}
                className="min-h-screen bg-white font-sans font-normal text-[#0c1e33] antialiased"
            >
                {/* ---------------- Navigation ---------------- */}
                <header className="fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-5">
                    <nav className="mx-auto flex h-14 max-w-6xl items-center justify-between rounded-full border border-white/70 bg-white/75 pr-2 pl-4 shadow-[0_8px_30px_-12px_rgba(12,30,51,0.25)] backdrop-blur-xl">
                        <a href="#top" className="flex items-center gap-2">
                            <span className="flex size-8 items-center justify-center rounded-full bg-[#0c1e33] text-white">
                                <AppLogoIcon className="size-5" />
                            </span>
                            <span className="text-base font-bold tracking-tight">
                                Aeterna
                            </span>
                        </a>
                        <div className="hidden items-center gap-7 text-sm font-medium text-slate-600 md:flex">
                            <a
                                href="#outils"
                                className="transition-colors duration-150 hover:text-[#0c1e33]"
                            >
                                Fonctionnalités
                            </a>
                            <a
                                href="#pourquoi"
                                className="transition-colors duration-150 hover:text-[#0c1e33]"
                            >
                                Pourquoi Aeterna
                            </a>
                            <a
                                href="#tarifs"
                                className="transition-colors duration-150 hover:text-[#0c1e33]"
                            >
                                Tarifs
                            </a>
                            <a
                                href="#contact"
                                onClick={() => contact('other')}
                                className="transition-colors duration-150 hover:text-[#0c1e33]"
                            >
                                Contact
                            </a>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <Link
                                href={login()}
                                className="hidden h-10 items-center rounded-full px-4 text-sm font-semibold text-[#0c1e33] transition-colors duration-150 hover:bg-slate-100 sm:inline-flex"
                            >
                                Se connecter
                            </Link>
                            <a
                                href="#contact"
                                onClick={askDemo}
                                className="inline-flex h-10 items-center rounded-full bg-[#0c1e33] px-4 text-sm font-semibold text-white transition-[background-color,transform] duration-150 ease-out hover:bg-[#16375A] active:scale-[0.97]"
                            >
                                Demander une démo
                            </a>
                        </div>
                    </nav>
                </header>

                {/* ---------------- Hero ---------------- */}
                <section
                    id="top"
                    className="relative overflow-hidden bg-[#eaecf2]"
                >
                    <div className="absolute inset-0 bg-[linear-gradient(100deg,#9dbcf8_0%,#d6e3fc_48%,#bfeaf2_100%)]" />
                    <div className="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-transparent to-[#eaecf2]" />
                    <div className="pointer-events-none absolute -top-24 right-[-10%] size-[520px] rounded-full bg-white/40 blur-3xl" />

                    <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pt-32 pb-16 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:pt-40 lg:pb-24">
                        <div data-reveal>
                            <span className="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-[#16375A] backdrop-blur">
                                <span className="size-1.5 rounded-full bg-[#638fe3]" />
                                Logiciel de gestion des alumni
                            </span>
                            <h1
                                className={`${display} mt-5 text-4xl leading-[1.08] sm:text-5xl lg:text-[3.6rem]`}
                            >
                                Gardez le lien avec chacun de vos diplômés.
                            </h1>
                            <p className="mt-5 max-w-xl text-lg leading-relaxed text-[#16375A]/80">
                                Aeterna réunit votre base alumni, vos
                                événements, vos campagnes et un portail dédié à
                                vos diplômés, dans un seul espace à l’image de
                                votre établissement.
                            </p>
                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <DemoButton onClick={askDemo} />
                                <a
                                    href="#outils"
                                    className="group inline-flex h-12 items-center gap-2 rounded-xl px-5 text-sm font-semibold text-[#0c1e33] transition-colors duration-150 hover:bg-white/60"
                                >
                                    Découvrir les fonctionnalités
                                    <ArrowRight className="size-4 transition-transform duration-150 ease-out group-hover:translate-x-0.5" />
                                </a>
                            </div>
                        </div>

                        <div data-reveal className="relative">
                            <div className="overflow-hidden rounded-[40px] rounded-tr-[120px] shadow-[0_40px_80px_-30px_rgba(12,30,51,0.45)]">
                                <img
                                    src="/images/morocco-campus.jpg"
                                    alt="Campus universitaire au Maroc"
                                    className="aspect-[5/4] w-full object-cover"
                                />
                            </div>

                            {/* Product glimpses: what an alumni sees in their portal */}
                            <div className="absolute -bottom-6 -left-4 flex items-center gap-3 rounded-2xl border border-white/80 bg-white/85 p-3 pr-5 shadow-xl backdrop-blur-xl sm:-left-10">
                                <span className="flex size-10 items-center justify-center rounded-full bg-[#638fe3]/15 text-[#638fe3]">
                                    <CalendarCheck className="size-5" />
                                </span>
                                <span className="grid">
                                    <span className="text-xs font-bold">
                                        Soirée des anciens
                                    </span>
                                    <span className="text-[11px] text-slate-500">
                                        Inscription confirmée
                                    </span>
                                </span>
                            </div>
                            <div className="absolute top-8 -right-3 hidden items-center gap-3 rounded-2xl border border-white/80 bg-white/85 p-3 pr-5 shadow-xl backdrop-blur-xl sm:flex">
                                <span className="flex size-10 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600">
                                    <Mail className="size-5" />
                                </span>
                                <span className="grid">
                                    <span className="text-xs font-bold">
                                        Nouveau message
                                    </span>
                                    <span className="text-[11px] text-slate-500">
                                        Promotion 2019
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ---------------- Intro ---------------- */}
                <section className="bg-[#eaecf2]">
                    <div className="mx-auto max-w-5xl px-4 pt-10 pb-20 sm:px-6">
                        <div
                            data-reveal
                            className="flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:gap-10"
                        >
                            <CursorMascot className="w-32 shrink-0 sm:w-40" />
                            <h2
                                className={`${display} text-3xl leading-tight sm:text-[2.6rem]`}
                            >
                                Pilotez l’engagement de vos alumni, simplement
                            </h2>
                        </div>
                        <p
                            data-reveal
                            className="mt-8 text-lg leading-relaxed text-[#16375A]/85"
                        >
                            Rester en contact avec ses anciens étudiants est
                            essentiel pour le rayonnement d’un établissement.
                            Fichiers Excel dispersés et e-mails isolés rendent
                            ce suivi difficile. Aeterna rassemble tout au même
                            endroit : vous gérez les fiches, organisez les
                            événements et communiquez avec chaque promotion,
                            pendant que la plateforme s’occupe du reste.
                        </p>
                        <div data-reveal className="mt-10 flex justify-center">
                            <DemoButton onClick={askDemo} />
                        </div>
                    </div>
                </section>

                {/* ---------------- Tools ---------------- */}
                <section id="outils" className="scroll-mt-24 bg-white">
                    <div className="mx-auto max-w-6xl px-4 py-24 sm:px-6">
                        <div data-reveal className="mx-auto max-w-4xl">
                            <h2
                                className={`${display} text-3xl leading-tight sm:text-[2.6rem]`}
                            >
                                Les outils qui servent vos objectifs
                            </h2>
                            <p className="mt-6 text-lg leading-relaxed text-[#16375A]/85">
                                Aeterna s’adapte à votre stratégie alumni : vous
                                choisissez qui fait quoi grâce aux rôles sur
                                mesure, vous ouvrez ou fermez chaque partie du
                                portail, et votre équipe retrouve tout dans un
                                espace clair.
                            </p>
                        </div>

                        <div className="mt-14 grid gap-8 md:grid-cols-3">
                            {toolCards.map((card) => (
                                <div
                                    key={card.title}
                                    data-reveal
                                    className="relative pr-2 pb-2"
                                >
                                    {/* The gradient lip behind each card */}
                                    <div className="absolute inset-0 top-2 left-2 rounded-[40px] rounded-tr-[56px] bg-[linear-gradient(90deg,#638fe3,#b9a6f5_60%,#5ccfe6)]" />
                                    <article className="relative h-full rounded-[40px] rounded-tr-[56px] bg-[#eef1f8] px-7 pt-9 pb-10">
                                        <h3 className="text-center text-2xl font-bold">
                                            {card.title}
                                        </h3>
                                        <ul className="mt-6 space-y-3.5">
                                            {card.items.map((item) => (
                                                <li
                                                    key={item}
                                                    className="flex gap-3 text-[15px] leading-snug font-semibold"
                                                >
                                                    <span className="mt-1 size-3.5 shrink-0 rounded-full bg-[#638fe3]" />
                                                    {item}
                                                </li>
                                            ))}
                                        </ul>
                                    </article>
                                </div>
                            ))}
                        </div>

                        <div data-reveal className="mt-14 flex justify-center">
                            <DemoButton onClick={askDemo} />
                        </div>
                    </div>
                </section>

                {/* ---------------- How it helps ---------------- */}
                <section className="bg-[linear-gradient(100deg,#9dbcf8_0%,#d6e3fc_48%,#bfeaf2_100%)]">
                    <div className="mx-auto max-w-5xl px-4 py-24 sm:px-6">
                        <h2
                            data-reveal
                            className={`${display} mx-auto max-w-3xl text-center text-3xl leading-tight sm:text-[2.6rem]`}
                        >
                            Comment Aeterna renforce le lien avec vos diplômés
                        </h2>

                        <div className="mt-14 space-y-8">
                            {stories.map(({ title, body, Mascot }) => (
                                <article
                                    key={title}
                                    data-reveal
                                    className="rounded-[36px] bg-white p-7 shadow-[0_20px_50px_-30px_rgba(12,30,51,0.35)] sm:p-10"
                                >
                                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-8">
                                        <Mascot className="w-20 shrink-0 sm:w-24" />
                                        <h3 className="text-2xl leading-tight font-bold sm:text-[1.9rem]">
                                            {title}
                                        </h3>
                                    </div>
                                    <p className="mt-5 text-[17px] leading-relaxed text-[#16375A]/85">
                                        {body}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ---------------- Why ---------------- */}
                <section id="pourquoi" className="scroll-mt-24 bg-white">
                    <div className="mx-auto max-w-5xl px-4 py-24 sm:px-6">
                        <h2
                            data-reveal
                            className={`${display} text-center text-3xl sm:text-[2.6rem]`}
                        >
                            Pourquoi choisir Aeterna
                        </h2>

                        <div className="mt-12 grid gap-6 md:grid-cols-2">
                            <article
                                data-reveal
                                className="rounded-[56px] bg-[#0c1e33] px-9 py-12 text-white"
                            >
                                <h3 className="text-center text-2xl font-bold sm:text-[1.75rem]">
                                    Simple pour toute votre équipe
                                </h3>
                                <p className="mt-5 text-[17px] leading-relaxed text-white/85">
                                    Une interface claire, en français, pensée
                                    pour les services relations alumni. Votre
                                    équipe se concentre sur le lien avec les
                                    diplômés, pas sur l’apprentissage d’un outil
                                    compliqué.
                                </p>
                            </article>
                            <article
                                data-reveal
                                className="rounded-[56px] bg-[#0c1e33] px-9 py-12 text-white"
                            >
                                <h3 className="text-center text-2xl font-bold sm:text-[1.75rem]">
                                    Vos données restent les vôtres
                                </h3>
                                <p className="mt-5 text-[17px] leading-relaxed text-white/85">
                                    Chaque établissement dispose de son propre
                                    espace, strictement isolé des autres. Vous
                                    décidez qui accède à quoi, rôle par rôle.
                                </p>
                            </article>
                            <article
                                data-reveal
                                className="relative overflow-hidden rounded-[56px] bg-[#0c1e33] px-9 py-12 text-white md:col-span-2"
                            >
                                <div className="pointer-events-none absolute -top-20 -right-16 size-64 rounded-full bg-[#638fe3]/30 blur-3xl" />
                                <h3 className="relative text-center text-2xl font-bold sm:text-[1.75rem]">
                                    Aux couleurs de votre établissement
                                </h3>
                                <p className="relative mx-auto mt-5 max-w-3xl text-center text-[17px] leading-relaxed text-white/85">
                                    Votre logo et votre nom accompagnent vos
                                    diplômés dans leur portail, à une adresse
                                    propre à votre établissement. Une expérience
                                    cohérente, du premier e-mail à l’inscription
                                    à l’événement.
                                </p>
                            </article>
                        </div>
                    </div>
                </section>

                <PricingSection plans={plans} onContact={contact} />

                {/* ---------------- Contact form: demo, custom plan or anything else ---------------- */}
                <section id="contact" className="scroll-mt-24 bg-white">
                    <div className="mx-auto max-w-4xl px-4 py-24 sm:px-6">
                        <div data-reveal className="text-center">
                            <h2 className={`${display} text-4xl sm:text-5xl`}>
                                Contactez-nous
                            </h2>
                            <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-[#16375A]/85">
                                Une démo, une formule sur mesure ou une simple
                                question : laissez-nous vos coordonnées, notre
                                équipe vous recontacte rapidement.
                            </p>
                        </div>

                        {flash?.success ? (
                            <div
                                data-reveal
                                className="mt-10 flex flex-col items-center gap-3 rounded-[32px] bg-[#eef1f8] px-6 py-12 text-center"
                            >
                                <CheckCircle2 className="size-10 text-emerald-500" />
                                <p className="text-xl font-bold">
                                    {flash.success}
                                </p>
                                <p className="text-sm text-slate-500">
                                    Vous pouvez déjà vous connecter si votre
                                    établissement utilise Aeterna.
                                </p>
                            </div>
                        ) : (
                            <Form
                                {...storeDemoRequest.form()}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="mt-10 grid gap-5"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Field
                                            id="subject"
                                            label="Objet"
                                            required
                                            error={errors.subject}
                                        >
                                            <select
                                                id="subject"
                                                name="subject"
                                                required
                                                value={subject}
                                                onChange={(event) =>
                                                    setSubject(
                                                        event.target
                                                            .value as ContactSubject,
                                                    )
                                                }
                                                className={`${inputClass} cursor-pointer`}
                                            >
                                                <option value="demo">
                                                    Demander une démo
                                                </option>
                                                <option value="custom">
                                                    Formule sur mesure
                                                </option>
                                                <option value="other">
                                                    Autre demande
                                                </option>
                                            </select>
                                        </Field>
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <Field
                                                id="name"
                                                label="Nom"
                                                required
                                                error={errors.name}
                                            >
                                                <input
                                                    id="name"
                                                    name="name"
                                                    required
                                                    autoComplete="name"
                                                    placeholder="Votre nom"
                                                    className={inputClass}
                                                />
                                            </Field>
                                            <Field
                                                id="organization"
                                                label="Établissement"
                                                required
                                                error={errors.organization}
                                            >
                                                <input
                                                    id="organization"
                                                    name="organization"
                                                    required
                                                    autoComplete="organization"
                                                    placeholder="Nom de votre établissement"
                                                    className={inputClass}
                                                />
                                            </Field>
                                            <Field
                                                id="email"
                                                label="E-mail"
                                                required
                                                error={errors.email}
                                            >
                                                <input
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    required
                                                    autoComplete="email"
                                                    placeholder="nom@etablissement.ma"
                                                    className={inputClass}
                                                />
                                            </Field>
                                            <Field
                                                id="phone"
                                                label="Téléphone"
                                                error={errors.phone}
                                            >
                                                <input
                                                    id="phone"
                                                    name="phone"
                                                    type="tel"
                                                    autoComplete="tel"
                                                    placeholder="+212 6 00 00 00 00"
                                                    className={inputClass}
                                                />
                                            </Field>
                                        </div>
                                        <Field
                                            id="message"
                                            label="Message"
                                            error={errors.message}
                                        >
                                            <textarea
                                                id="message"
                                                name="message"
                                                rows={5}
                                                placeholder={
                                                    messagePlaceholders[subject]
                                                }
                                                className={`${inputClass} h-auto resize-y py-3`}
                                            />
                                        </Field>
                                        <div className="mt-2 flex justify-center">
                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="inline-flex h-12 min-w-56 cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#0c1e33] px-8 text-sm font-semibold text-white transition-[background-color,transform] duration-150 ease-out hover:bg-[#16375A] active:scale-[0.97] disabled:opacity-60"
                                            >
                                                {processing && <Spinner />}
                                                Être recontacté
                                            </button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                </section>

                {/* ---------------- Audiences ---------------- */}
                <section className="bg-white">
                    <div className="mx-auto max-w-6xl px-4 pb-24 sm:px-6">
                        <h2
                            data-reveal
                            className={`${display} text-center text-3xl sm:text-[2.6rem]`}
                        >
                            Conçu pour tous les établissements
                        </h2>
                        <div className="mt-12 grid grid-cols-2 gap-4 md:grid-cols-4">
                            {audiences.map(({ label, icon: Icon }) => (
                                <div
                                    key={label}
                                    data-reveal
                                    className="flex flex-col items-center gap-3 rounded-3xl border border-slate-200/80 px-4 py-8 text-center transition-[border-color,transform] duration-200 ease-out hover:-translate-y-0.5 hover:border-[#638fe3]/50"
                                >
                                    <span className="flex size-14 items-center justify-center rounded-2xl bg-[#eef1f8] text-[#16375A]">
                                        <Icon className="size-7" />
                                    </span>
                                    <span className="text-sm font-bold">
                                        {label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* ---------------- Footer ---------------- */}
                <footer className="bg-[#0c1e33] text-white">
                    <div className="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-[1.2fr_1fr_1fr]">
                        <div>
                            <div className="flex items-center gap-3">
                                <span className="flex size-11 items-center justify-center rounded-full bg-white/10">
                                    <AppLogoIcon className="size-7" />
                                </span>
                                <span className="text-3xl font-extrabold tracking-tight">
                                    Aeterna
                                </span>
                            </div>
                            <p className="mt-5 max-w-xs text-sm leading-relaxed text-white/70">
                                La plateforme qui aide les établissements à
                                garder le lien avec leurs diplômés.
                            </p>
                        </div>
                        <div>
                            <p className="text-sm font-bold">Plateforme</p>
                            <ul className="mt-4 space-y-3 text-sm">
                                <li>
                                    <a
                                        href="#outils"
                                        className="text-[#90b6f7] transition-colors duration-150 hover:text-white"
                                    >
                                        Fonctionnalités
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="#pourquoi"
                                        className="text-[#90b6f7] transition-colors duration-150 hover:text-white"
                                    >
                                        Pourquoi Aeterna
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="#contact"
                                        onClick={askDemo}
                                        className="text-[#90b6f7] transition-colors duration-150 hover:text-white"
                                    >
                                        Demander une démo
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href="#contact"
                                        onClick={() => contact('custom')}
                                        className="text-[#90b6f7] transition-colors duration-150 hover:text-white"
                                    >
                                        Formule sur mesure
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <p className="text-sm font-bold">Espaces</p>
                            <ul className="mt-4 space-y-3 text-sm">
                                <li>
                                    <Link
                                        href={login()}
                                        className="text-[#90b6f7] transition-colors duration-150 hover:text-white"
                                    >
                                        Espace établissement
                                    </Link>
                                </li>
                                <li className="text-white/60">
                                    Portail diplômé : l’adresse vous est
                                    communiquée par votre établissement
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div className="border-t border-white/10">
                        <p className="mx-auto max-w-6xl px-4 py-6 text-xs text-white/55 sm:px-6">
                            © {new Date().getFullYear()} Aeterna. Tous droits
                            réservés.
                        </p>
                    </div>
                </footer>

                {/* Floating helper, the counterpart of the reference's chat bubble */}
                <a
                    href="#contact"
                    onClick={() => contact('other')}
                    className="group fixed right-4 bottom-4 z-40 flex items-center gap-3 sm:right-6 sm:bottom-6"
                    aria-label="Une question ? Contactez-nous"
                >
                    <span className="hidden rounded-2xl border border-slate-200/80 bg-white px-4 py-2.5 text-sm font-medium shadow-lg sm:block">
                        Une question ? Parlons-en
                    </span>
                    <span className="flex size-14 items-center justify-center rounded-2xl bg-[#638fe3] text-white shadow-lg shadow-[#638fe3]/30 transition-transform duration-150 ease-out group-hover:scale-105 group-active:scale-[0.97]">
                        <MessageCircle className="size-6" />
                    </span>
                </a>
            </div>
        </>
    );
}
