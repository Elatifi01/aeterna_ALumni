import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: Record<string, unknown> | null;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    fields?: Record<string, string | number | (string | number)[]>;
    onSuccess?: () => void;
};

export function ConfirmDeleteDialog({
    open,
    onOpenChange,
    action,
    title,
    description,
    confirmLabel = 'Supprimer',
    fields = {},
    onSuccess,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        Annuler
                    </Button>
                    {action && (
                        <Form
                            {...action}
                            options={{ preserveScroll: true }}
                            onSuccess={() => {
                                onOpenChange(false);
                                onSuccess?.();
                            }}
                        >
                            {({ processing }) => (
                                <>
                                    {Object.entries(fields).flatMap(
                                        ([name, value]) =>
                                            (Array.isArray(value)
                                                ? value
                                                : [value]
                                            ).map((one, index) => (
                                                <input
                                                    key={`${name}-${index}`}
                                                    type="hidden"
                                                    name={
                                                        Array.isArray(value)
                                                            ? `${name}[]`
                                                            : name
                                                    }
                                                    value={one}
                                                />
                                            )),
                                    )}

                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                        data-test="confirm-delete"
                                    >
                                        {confirmLabel}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
