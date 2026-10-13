import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types';

type FlashBag = {
    success?: string | null;
    error?: string | null;
    toast?: FlashToast | null;
};

export function useFlashToast(): void {
    useEffect(() => {
        const toasted: string[] = [];

        const claim = (visitId: string | undefined): boolean => {
            if (visitId === undefined) {
                return true;
            }

            if (toasted.includes(visitId)) {
                return false;
            }

            toasted.push(visitId);

            if (toasted.length > 20) {
                toasted.shift();
            }

            return true;
        };

        type Detail = {
            page?: { props?: { flash?: FlashBag } };
            visitId?: string;
            cached?: boolean;
        };

        const show = (detail: Detail | undefined) => {
            const flash = detail?.page?.props?.flash;

            if (!flash || (!flash.error && !flash.success)) {
                return;
            }

            if (!claim(detail?.visitId)) {
                return;
            }

            if (flash.error) {
                toast.error(flash.error);
            } else {
                toast.success(flash.success as string);
            }
        };

        const stopNavigate = router.on('navigate', (event) => {
            const detail = (event as CustomEvent).detail as Detail | undefined;

            if (detail?.cached) {
                return;
            }

            show(detail);
        });

        const stopSuccess = router.on('success', (event) => {
            show((event as CustomEvent).detail as Detail | undefined);
        });

        const stopFlash = router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash as
                | FlashBag
                | undefined;

            const data = flash?.toast;

            if (data) {
                toast[data.type](data.message);
            }
        });

        return () => {
            stopNavigate();
            stopSuccess();
            stopFlash();
        };
    }, []);
}
