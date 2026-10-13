export type PaginatedLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginatedLink[];
};

export type SortDirection = 'asc' | 'desc';

export type PlanUsage = {
    used: number;
    limit: number | null;
    remaining: number | null;
};
