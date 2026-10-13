import { ListFilter, Search, X } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { navigateWithParams } from '@/components/data-table/pagination';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type FilterField = {
    name: string;
    label: string;
    value: string | number | null;
    options: { value: string | number; label: string }[];
};

type Props = {
    searchValue: string | null;
    searchPlaceholder: string;
    searchLabel: string;
    filters?: FilterField[];
    children?: ReactNode;
};

export function Toolbar({
    searchValue,
    searchPlaceholder,
    searchLabel,
    filters = [],
    children,
}: Props) {
    const [search, setSearch] = useState(searchValue ?? '');

    const activeCount = filters.filter(
        (filter) => filter.value !== null && filter.value !== '',
    ).length;

    const hasAnything = activeCount > 0 || Boolean(searchValue);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        navigateWithParams({ search: search || null, page: 1 });
    };

    const clearAll = () => {
        setSearch('');
        navigateWithParams({
            search: null,
            page: 1,
            ...Object.fromEntries(filters.map((filter) => [filter.name, null])),
        });
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <form
                onSubmit={submitSearch}
                className="relative flex-1 sm:max-w-xs"
            >
                <Search className="text-slate-400 pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={searchPlaceholder}
                    aria-label={searchLabel}
                    className="h-9.5 pl-9 rounded-xl bg-white/80 dark:bg-slate-900/70"
                />
            </form>
            {filters.length > 0 && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="h-9.5 rounded-xl shadow-2xs">
                            <ListFilter className="size-4 text-slate-500" />
                            Filtrer
                            {activeCount > 0 && (
                                <span className="bg-blue-600 text-white tabular ml-1 rounded-full px-1.5 text-xs font-semibold">
                                    {activeCount}
                                </span>
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="apple-glass w-72 p-3.5 rounded-2xl border border-white/80 dark:border-white/10 shadow-xl">
                        <div className="space-y-3">
                            {filters.map((filter) => (
                                <div key={filter.name} className="grid gap-1.5">
                                    <Label htmlFor={`filter-${filter.name}`} className="text-xs font-semibold text-slate-600 dark:text-slate-400">
                                        {filter.label}
                                    </Label>
                                    <select
                                        id={`filter-${filter.name}`}
                                        value={filter.value ?? ''}
                                        onChange={(event) =>
                                            navigateWithParams({
                                                [filter.name]:
                                                    event.target.value || null,
                                                page: 1,
                                            })
                                        }
                                        className="border-slate-200/80 dark:border-white/10 bg-white/80 dark:bg-slate-900/80 text-slate-900 dark:text-white h-9.5 rounded-xl border px-3 text-sm focus:border-blue-500 outline-none transition-all"
                                    >
                                        <option value="">Tous</option>
                                        {filter.options.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            ))}
                        </div>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            {hasAnything && (
                <Button variant="ghost" className="h-9" onClick={clearAll}>
                    <X className="size-4" />
                    Reinitialiser
                </Button>
            )}

            {children && (
                <div className="ml-auto flex items-center gap-2">
                    {children}
                </div>
            )}
        </div>
    );
}
