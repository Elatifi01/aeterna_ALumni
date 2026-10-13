import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

export function usePermissions() {
    const { auth } = usePage<SharedData>().props;

    const permissions = auth?.permissions ?? [];
    const isSuperAdmin = auth?.isSuperAdmin ?? false;

    const can = (permission: string): boolean =>
        isSuperAdmin || permissions.includes(permission);

    const canAny = (...wanted: string[]): boolean => wanted.some(can);

    return { can, canAny, isSuperAdmin, permissions };
}
