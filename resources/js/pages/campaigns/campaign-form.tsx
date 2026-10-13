import { Form, Link, router } from '@inertiajs/react';
import { Users, UsersRound } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index } from '@/routes/campaigns';

export type RecipientMode = 'all' | 'filtered';

export type Criteria = {
    recipients: RecipientMode;
    search: string | null;
    graduation_year: number | null;
    degree: string | null;
    company: string | null;
};

export type FilterOptions = {
    graduationYears: number[];
    degrees: string[];
    companies: string[];
};

type Props = {
    action: Record<string, unknown>;
    reloadUrl: string;
    campaign?: { subject: string; content: string };
    criteria: Criteria;
    recipientCount: number;
    totalAlumni: number;
    filterOptions?: FilterOptions;
    submitLabel: string;
};

const sections = ['Contenu', 'Destinataires', 'Envoi'] as const;

export function CampaignForm({
    action,
    reloadUrl,
    campaign,
    criteria,
    recipientCount,
    totalAlumni,
    filterOptions,
    submitLabel,
}: Props) {
    const [mode, setMode] = useState<RecipientMode>(criteria.recipients);

    const reload = (next: Partial<Criteria>) => {
        const merged = { ...criteria, recipients: mode, ...next };

        router.get(
            reloadUrl,
            Object.fromEntries(
                Object.entries(merged).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['recipientCount', 'criteria'],
            },
        );
    };

    const chooseMode = (next: RecipientMode) => {
        setMode(next);
        reload({ recipients: next });
    };

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
                    <Section index={0}>
                        <div className="grid gap-2">
                            <Label htmlFor="subject">
                                Objet
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            <Input
                                id="subject"
                                name="subject"
                                required
                                defaultValue={campaign?.subject}
                                className="h-12"
                                aria-invalid={errors.subject ? true : undefined}
                            />
                            <InputError message={errors.subject} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="content">
                                Message
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            <textarea
                                id="content"
                                name="content"
                                rows={10}
                                required
                                defaultValue={campaign?.content}
                                className="border-border-strong bg-surface text-text placeholder:text-text-muted rounded-md border px-3 py-2 text-sm"
                            />
                            <p className="text-text-muted text-xs">
                                Le message est envoye tel quel, precede du nom
                                de votre organisation.
                            </p>
                            <InputError message={errors.content} />
                        </div>
                    </Section>
                    <Section index={1}>
                        <input type="hidden" name="recipients" value={mode} />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <ChoiceTile
                                icon={<UsersRound className="size-5" />}
                                label="Tous les alumni"
                                description={`${totalAlumni} alumni`}
                                selected={mode === 'all'}
                                onSelect={() => chooseMode('all')}
                            />
                            <ChoiceTile
                                icon={<Users className="size-5" />}
                                label="Selection filtree"
                                description="Par promotion, diplome ou entreprise"
                                selected={mode === 'filtered'}
                                onSelect={() => chooseMode('filtered')}
                            />
                        </div>
                        {mode === 'filtered' && (
                            <div className="border-border grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                <FilterSelect
                                    name="graduation_year"
                                    label="Promotion"
                                    value={criteria.graduation_year}
                                    options={(
                                        filterOptions?.graduationYears ?? []
                                    ).map((year) => ({
                                        value: year,
                                        label: String(year),
                                    }))}
                                    onChange={(value) =>
                                        reload({
                                            graduation_year: value
                                                ? Number(value)
                                                : null,
                                        })
                                    }
                                />
                                <FilterSelect
                                    name="degree"
                                    label="Diplome"
                                    value={criteria.degree}
                                    options={(filterOptions?.degrees ?? []).map(
                                        (degree) => ({
                                            value: degree,
                                            label: degree,
                                        }),
                                    )}
                                    onChange={(value) =>
                                        reload({ degree: value || null })
                                    }
                                />
                                <FilterSelect
                                    name="company"
                                    label="Entreprise"
                                    value={criteria.company}
                                    options={(
                                        filterOptions?.companies ?? []
                                    ).map((company) => ({
                                        value: company,
                                        label: company,
                                    }))}
                                    onChange={(value) =>
                                        reload({ company: value || null })
                                    }
                                />
                                <div className="grid gap-1.5">
                                    <Label htmlFor="search">Recherche</Label>
                                    <Input
                                        id="search"
                                        name="search"
                                        defaultValue={criteria.search ?? ''}
                                        placeholder="Nom, e-mail, entreprise"
                                        className="h-10"
                                        onBlur={(event) =>
                                            reload({
                                                search:
                                                    event.target.value || null,
                                            })
                                        }
                                    />
                                </div>
                            </div>
                        )}

                        <p
                            className={cn(
                                'text-sm',
                                recipientCount === 0
                                    ? 'text-danger'
                                    : 'text-text-secondary',
                            )}
                            aria-live="polite"
                        >
                            {recipientCount === 0
                                ? 'Aucun alumni ne correspond a cette selection.'
                                : `${recipientCount} destinataire${recipientCount > 1 ? 's' : ''} selectionne${recipientCount > 1 ? 's' : ''}.`}
                        </p>
                        <InputError message={errors.recipients} />
                    </Section>
                    <Section index={2}>
                        <p className="text-text-secondary text-sm">
                            La campagne est enregistree comme brouillon. Vous
                            pourrez la relire, puis l envoyer depuis sa fiche.
                        </p>
                        <div className="border-border flex items-center justify-end gap-3 border-t pt-6">
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Annuler</Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || recipientCount === 0}
                                className="h-12 w-full sm:w-auto"
                            >
                                {processing && <Spinner />}
                                {submitLabel}
                            </Button>
                        </div>
                    </Section>
                </>
            )}
        </Form>
    );
}

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

function FilterSelect({
    name,
    label,
    value,
    options,
    onChange,
}: {
    name: string;
    label: string;
    value: string | number | null;
    options: { value: string | number; label: string }[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={name}>{label}</Label>
            <select
                id={name}
                name={name}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className="border-border-strong bg-surface text-text h-10 rounded-md border px-2 text-sm"
            >
                <option value="">Tous</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
