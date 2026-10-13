import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

type Props<T> = {
    page: Paginated<T>;
    onNavigate: (params: Record<string, string | number>) => void;
};

const PER_PAGE_CHOICES = [10, 25, 50, 100];

export function DataTablePagination<T>({ page, onNavigate }: Props<T>) {
    const [goTo, setGoTo] = useState('');

    const pages = buildPageList(page.current_page, page.last_page);

    return (
        <div className="border-border flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <label className="text-text-secondary flex items-center gap-2 text-sm">
                Afficher par page
                <select
                    value={page.per_page}
                    onChange={(event) =>
                        onNavigate({ per_page: event.target.value, page: 1 })
                    }
                    className="border-border-strong bg-surface text-text h-9 rounded-md border px-2 text-sm"
                >
                    {PER_PAGE_CHOICES.map((size) => (
                        <option key={size} value={size}>
                            {size}
                        </option>
                    ))}
                </select>
            </label>
            <nav
                className="flex items-center gap-1"
                aria-label="Pagination"
                role="navigation"
            >
                <Button
                    variant="outline"
                    size="icon"
                    className="size-9"
                    disabled={page.current_page <= 1}
                    onClick={() => onNavigate({ page: page.current_page - 1 })}
                    aria-label="Page precedente"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                {pages.map((entry, index) =>
                    entry === null ? (
                        <span
                            key={`gap-${index}`}
                            className="text-text-muted px-1 text-sm"
                        >
                            &hellip;
                        </span>
                    ) : (
                        <button
                            key={entry}
                            type="button"
                            onClick={() => onNavigate({ page: entry })}
                            aria-current={
                                entry === page.current_page ? 'page' : undefined
                            }
                            className={cn(
                                'tabular h-9 min-w-9 rounded-md px-2 text-sm font-medium',
                                entry === page.current_page
                                    ? 'bg-accent text-accent-contrast'
                                    : 'text-text-secondary hover:bg-surface-muted',
                            )}
                        >
                            {entry}
                        </button>
                    ),
                )}

                <Button
                    variant="outline"
                    size="icon"
                    className="size-9"
                    disabled={page.current_page >= page.last_page}
                    onClick={() => onNavigate({ page: page.current_page + 1 })}
                    aria-label="Page suivante"
                >
                    <ChevronRight className="size-4" />
                </Button>
            </nav>
            <form
                className="text-text-secondary flex items-center gap-2 text-sm"
                onSubmit={(event) => {
                    event.preventDefault();
                    const target = Number(goTo);
                    if (target >= 1 && target <= page.last_page) {
                        onNavigate({ page: target });
                        setGoTo('');
                    }
                }}
            >
                <label htmlFor="goto-page">Aller a la page</label>
                <input
                    id="goto-page"
                    inputMode="numeric"
                    value={goTo}
                    onChange={(event) => setGoTo(event.target.value)}
                    className="border-border-strong bg-surface text-text tabular h-9 w-16 rounded-md border px-2 text-sm"
                />
                <Button type="submit" variant="outline" className="h-9">
                    Go
                </Button>
            </form>
        </div>
    );
}

function buildPageList(current: number, last: number): (number | null)[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set<number>([1, last, current]);
    if (current - 1 > 1) pages.add(current - 1);
    if (current + 1 < last) pages.add(current + 1);

    const sorted = [...pages].sort((a, b) => a - b);
    const result: (number | null)[] = [];

    sorted.forEach((value, index) => {
        if (index > 0 && value - sorted[index - 1] > 1) {
            result.push(null);
        }
        result.push(value);
    });

    return result;
}

export function navigateWithParams(
    params: Record<string, string | number | null>,
) {
    const url = new URL(window.location.href);

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    });

    router.get(
        url.pathname + url.search,
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
