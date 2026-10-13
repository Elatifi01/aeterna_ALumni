import { Form, Link } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/alumni';

export type AlumniFormValues = {
    id?: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    degree: string | null;
    graduation_year: number | null;
    company: string | null;
    job_title: string | null;
    city: string | null;
};

type Props = {
    action: Record<string, unknown>;
    alumni?: AlumniFormValues;
    submitLabel: string;
};

export function AlumniForm({ action, alumni, submitLabel }: Props) {
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
                errors: InertiaFormProps<AlumniFormValues>['errors'];
            }) => (
                <>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Identite
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="first_name"
                                label="Prenom"
                                required
                                defaultValue={alumni?.first_name}
                                error={errors.first_name}
                            />
                            <Field
                                name="last_name"
                                label="Nom"
                                required
                                defaultValue={alumni?.last_name}
                                error={errors.last_name}
                            />
                        </div>
                        <Field
                            name="email"
                            label="Adresse e-mail"
                            type="email"
                            required
                            defaultValue={alumni?.email}
                            error={errors.email}
                        />
                        <Field
                            name="phone"
                            label="Telephone"
                            defaultValue={alumni?.phone ?? ''}
                            error={errors.phone}
                        />
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Parcours
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="degree"
                                label="Diplome"
                                defaultValue={alumni?.degree ?? ''}
                                error={errors.degree}
                            />
                            <Field
                                name="graduation_year"
                                label="Annee de promotion"
                                type="number"
                                defaultValue={
                                    alumni?.graduation_year?.toString() ?? ''
                                }
                                error={errors.graduation_year}
                            />
                        </div>
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-text text-base font-semibold">
                            Situation professionnelle
                        </h2>
                        <Field
                            name="company"
                            label="Entreprise"
                            defaultValue={alumni?.company ?? ''}
                            error={errors.company}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                name="job_title"
                                label="Poste"
                                defaultValue={alumni?.job_title ?? ''}
                                error={errors.job_title}
                            />
                            <Field
                                name="city"
                                label="Ville"
                                defaultValue={alumni?.city ?? ''}
                                error={errors.city}
                            />
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

type FieldProps = {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
    defaultValue?: string;
    error?: string;
};

function Field({
    name,
    label,
    type = 'text',
    required,
    defaultValue,
    error,
}: FieldProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>
                {label}
                {required && (
                    <span className="text-danger ml-0.5" aria-hidden>
                        *
                    </span>
                )}
            </Label>
            <Input
                id={name}
                name={name}
                type={type}
                required={required}
                defaultValue={defaultValue}
                aria-invalid={error ? true : undefined}
                className="h-12"
            />
            <InputError message={error} />
        </div>
    );
}
