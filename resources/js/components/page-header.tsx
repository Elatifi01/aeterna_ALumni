import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    action?: ReactNode;
};

export function PageHeader({ title, description, action }: Props) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
                <h1 className="text-text text-2xl leading-8 font-semibold tracking-[-0.01em]">
                    {title}
                </h1>
                {description && (
                    <p className="text-text-secondary mt-1 text-sm">
                        {description}
                    </p>
                )}
            </div>
            {/* Pages pass their buttons as one flex row: let it wrap on narrow screens */}
            {action && (
                <div className="max-w-full sm:shrink-0 *:flex-wrap">{action}</div>
            )}
        </div>
    );
}
