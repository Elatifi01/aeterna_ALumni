import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { DataTablePagination } from '@/components/data-table/pagination';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import type { Paginated, SortDirection } from '@/types';

export type Column<T> = {
    key: string;
    header: string;
    sortable?: boolean;
    cell: (row: T) => ReactNode;
    className?: string;
    secondary?: boolean;
};

type Props<T> = {
    rows: Paginated<T>;
    columns: Column<T>[];
    rowKey: (row: T) => number | string;
    sort: string;
    direction: SortDirection;
    onNavigate: (params: Record<string, string | number | null>) => void;
    selected: (number | string)[];
    onSelectedChange: (selected: (number | string)[]) => void;
    actions?: (row: T) => ReactNode;
    empty: ReactNode;
    bulkBar?: (selected: (number | string)[], clear: () => void) => ReactNode;
};

export function DataTable<T>({
    rows,
    columns,
    rowKey,
    sort,
    direction,
    onNavigate,
    selected,
    onSelectedChange,
    actions,
    empty,
    bulkBar,
}: Props<T>) {
    const pageKeys = rows.data.map(rowKey);
    const allSelected =
        pageKeys.length > 0 && pageKeys.every((key) => selected.includes(key));
    const someSelected = pageKeys.some((key) => selected.includes(key));

    const toggleAll = () => {
        onSelectedChange(
            allSelected
                ? selected.filter((key) => !pageKeys.includes(key))
                : [...new Set([...selected, ...pageKeys])],
        );
    };

    const toggleRow = (key: number | string) => {
        onSelectedChange(
            selected.includes(key)
                ? selected.filter((value) => value !== key)
                : [...selected, key],
        );
    };

    const sortIcon = (column: Column<T>) => {
        if (!column.sortable) {
            return null;
        }

        if (sort !== column.key) {
            return (
                <ChevronsUpDown className="text-text-muted size-3.5 opacity-0 transition group-hover:opacity-100" />
            );
        }

        return direction === 'asc' ? (
            <ChevronUp className="text-accent size-3.5" />
        ) : (
            <ChevronDown className="text-accent size-3.5" />
        );
    };

    const headerCheckboxState = allSelected
        ? true
        : someSelected
          ? 'indeterminate'
          : false;

    return (
        <div className="relative space-y-3">
            {bulkBar &&
                selected.length > 0 &&
                bulkBar(selected, () => onSelectedChange([]))}

            <div className="apple-glass overflow-hidden rounded-2xl border border-white/80 dark:border-white/10 shadow-xs">
                {rows.data.length === 0 ? (
                    empty
                ) : (
                    // relative: absolutely positioned bits inside the table (checkbox inputs) must be clipped
                    // by this scroller too, or they widen the whole page on phones.
                    <div className="relative overflow-x-auto">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="bg-white/40 dark:bg-white/[0.02] border-b border-slate-200/60 dark:border-white/10">
                                    <th className="h-11 w-12 px-4">
                                        <Checkbox
                                            checked={headerCheckboxState}
                                            onCheckedChange={toggleAll}
                                            aria-label="Tout selectionner"
                                        />
                                    </th>
                                    {columns.map((column) => (
                                        <th
                                            key={column.key}
                                            scope="col"
                                            className={cn(
                                                'text-text-secondary h-11 px-4 text-[13px] font-medium',
                                                column.secondary &&
                                                    'hidden xl:table-cell',
                                                column.className,
                                            )}
                                        >
                                            {column.sortable ? (
                                                <button
                                                    type="button"
                                                    className="group hover:text-text flex items-center gap-1"
                                                    onClick={() =>
                                                        onNavigate({
                                                            sort: column.key,
                                                            direction:
                                                                sort ===
                                                                    column.key &&
                                                                direction ===
                                                                    'asc'
                                                                    ? 'desc'
                                                                    : 'asc',
                                                            page: 1,
                                                        })
                                                    }
                                                >
                                                    {column.header}
                                                    {sortIcon(column)}
                                                </button>
                                            ) : (
                                                column.header
                                            )}
                                        </th>
                                    ))}

                                    {actions && (
                                        <th className="w-12 px-4">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.data.map((row) => {
                                    const key = rowKey(row);
                                    const isSelected = selected.includes(key);

                                    return (
                                        <tr
                                            key={key}
                                            className={cn(
                                                'border-slate-100 dark:border-white/5 border-b last:border-b-0 transition-colors',
                                                isSelected
                                                    ? 'bg-blue-500/10'
                                                    : 'hover:bg-slate-50/60 dark:hover:bg-white/[0.03]',
                                            )}
                                        >
                                            <td className="relative h-[52px] px-4">
                                                {isSelected && (
                                                    <span
                                                        aria-hidden
                                                        className="bg-accent absolute inset-y-0 left-0 w-[3px]"
                                                    />
                                                )}
                                                <Checkbox
                                                    checked={isSelected}
                                                    onCheckedChange={() =>
                                                        toggleRow(key)
                                                    }
                                                    aria-label="Selectionner la ligne"
                                                />
                                            </td>
                                            {columns.map((column) => (
                                                <td
                                                    key={column.key}
                                                    className={cn(
                                                        'text-text px-4 text-sm',
                                                        column.secondary &&
                                                            'hidden xl:table-cell',
                                                        column.className,
                                                    )}
                                                >
                                                    {column.cell(row)}
                                                </td>
                                            ))}

                                            {actions && (
                                                <td className="px-4 text-right">
                                                    {actions(row)}
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {rows.total > 0 && (
                    <DataTablePagination page={rows} onNavigate={onNavigate} />
                )}
            </div>
        </div>
    );
}
