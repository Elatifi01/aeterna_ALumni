import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: Record<string, unknown> | null;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
};

export function ConfirmWithPasswordDialog({
    open,
    onOpenChange,
    action,
    title,
    description,
    confirmLabel = 'Confirmer',
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {action !== null && (
                    <Form
                        {...action}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        resetOnSuccess={['password']}
                    >
                        {({
                            processing,
                            errors,
                        }: {
                            processing: boolean;
                            errors: Record<string, string>;
                        }) => (
                            <>
                                <div className="grid gap-2 py-2">
                                    <Label htmlFor="confirm-password">
                                        Votre mot de passe
                                    </Label>
                                    <PasswordInput
                                        id="confirm-password"
                                        name="password"
                                        required
                                        autoFocus
                                        autoComplete="current-password"
                                        className="h-11"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Annuler
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        {confirmLabel}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
