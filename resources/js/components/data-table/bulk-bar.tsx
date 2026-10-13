import { X } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = {
    count: number;
    onClear: () => void;
    children?: ReactNode;
};

export function BulkBar({ count, onClear, children }: Props) {
    return (
        <div
            role="status"
            className="border-accent/40 bg-accent-soft text-text flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-2"
        >
            <div className="flex items-center gap-3">
                <span className="tabular text-sm font-medium">
                    {count} selectionne{count > 1 ? 's' : ''}
                </span>
                <button
                    type="button"
                    onClick={onClear}
                    className="text-text-secondary hover:text-text flex items-center gap-1 text-sm"
                >
                    <X className="size-3.5" />
                    Annuler la selection
                </button>
            </div>
            {children && (
                <div className="flex items-center gap-2">{children}</div>
            )}
        </div>
    );
}
