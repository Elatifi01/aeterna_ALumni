import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

export default function AppLogoLockup({ className }: { className?: string }) {
    return (
        <div className={cn('flex flex-col items-center gap-3', className)}>
            <AppLogoIcon className="text-primary dark:text-accent size-12" />
            <span className="text-text text-2xl font-semibold tracking-[0.14em] uppercase">
                Aeterna
            </span>
        </div>
    );
}
