import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedData } from '@/types';

export default function AppLogo() {
    const { auth, name } = usePage<SharedData>().props;

    const organization = auth?.organization ?? null;
    const title = organization?.name ?? name;
    const subtitle = auth?.isSuperAdmin
        ? 'Plateforme'
        : (organization?.plan_name ?? null);

    return (
        <>
            {organization?.logo_url ? (
                <span className="border-border bg-surface flex aspect-square size-9 items-center justify-center overflow-hidden rounded-lg border">
                    <img
                        src={organization.logo_url}
                        alt={`Logo ${organization.name}`}
                        className="size-full object-contain"
                    />
                </span>
            ) : (
                <span className="bg-primary text-accent-contrast dark:bg-surface-muted dark:text-text flex aspect-square size-9 items-center justify-center rounded-lg">
                    <AppLogoIcon className="size-5" />
                </span>
            )}

            <div className="ml-1 grid flex-1 text-left">
                <span className="text-text truncate text-sm leading-tight font-semibold">
                    {title}
                </span>
                {subtitle && (
                    <span className="text-text-muted truncate text-xs leading-tight">
                        {subtitle}
                    </span>
                )}
            </div>
        </>
    );
}
