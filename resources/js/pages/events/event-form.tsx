import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index } from '@/routes/events';
import { eventStatuses, type EventStatus } from '@/components/status-badge';

export type EventFormValues = {
    id?: number;
    title: string;
    description: string | null;
    location: string | null;
    starts_at: string | null;
    ends_at: string | null;
    capacity: number | null;
    status: EventStatus;
};

type Props = {
    action: Record<string, unknown>;
    event?: EventFormValues;
    submitLabel: string;
};

const sections = ['Informations', 'Date et lieu', 'Inscriptions'] as const;

export function EventForm({ action, event, submitLabel }: Props) {
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
                            <Label htmlFor="title">
                                Titre
                                <span className="text-danger ml-0.5">*</span>
                            </Label>
                            <Input
                                id="title"
                                name="title"
                                required
                                defaultValue={event?.title}
                                className="h-12"
                                aria-invalid={errors.title ? true : undefined}
                            />
                            <InputError message={errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                rows={4}
                                defaultValue={event?.description ?? ''}
                                className="border-border-strong bg-surface text-text placeholder:text-text-muted rounded-md border px-3 py-2 text-sm"
                            />
                            <InputError message={errors.description} />
                        </div>
                    </Section>
                    <Section index={1}>
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
                                    type="datetime-local"
                                    required
                                    defaultValue={event?.starts_at ?? ''}
                                    className="h-12"
                                />
                                <InputError message={errors.starts_at} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ends_at">Date de fin</Label>
                                <Input
                                    id="ends_at"
                                    name="ends_at"
                                    type="datetime-local"
                                    defaultValue={event?.ends_at ?? ''}
                                    className="h-12"
                                />
                                <InputError message={errors.ends_at} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="location">Lieu</Label>
                            <Input
                                id="location"
                                name="location"
                                defaultValue={event?.location ?? ''}
                                className="h-12"
                            />
                            <InputError message={errors.location} />
                        </div>
                    </Section>
                    <Section index={2}>
                        <div className="grid gap-2">
                            <Label htmlFor="capacity">Capacite</Label>
                            <Input
                                id="capacity"
                                name="capacity"
                                type="number"
                                min={1}
                                defaultValue={event?.capacity ?? ''}
                                className="h-12"
                            />
                            <p className="text-text-muted text-xs">
                                Laissez vide pour un nombre de places illimite.
                            </p>
                            <InputError message={errors.capacity} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="status">Statut</Label>
                            <select
                                id="status"
                                name="status"
                                defaultValue={event?.status ?? 'draft'}
                                className="border-border-strong bg-surface text-text h-12 rounded-md border px-3 text-sm"
                            >
                                {(
                                    Object.keys(eventStatuses) as EventStatus[]
                                ).map((status) => (
                                    <option key={status} value={status}>
                                        {eventStatuses[status].label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.status} />
                        </div>
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
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function Section({
    index: position,
    children,
    className,
}: {
    index: number;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('space-y-4', className)}>
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
