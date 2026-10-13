import { ArrowDown, ArrowUp, Info } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type StatCardProps = {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    delta?: number;
    deltaLabel?: string;
    className?: string;
};

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    delta,
    deltaLabel = 'vs mois dernier',
    className,
}: StatCardProps) {
    const hasDelta = delta !== undefined;
    const isUp = hasDelta && delta > 0;
    const isDown = hasDelta && delta < 0;

    return (
        <div
            className={cn(
                'bg-white dark:bg-slate-800/90 rounded-[28px] p-6 relative overflow-hidden group border border-slate-200/70 dark:border-white/10 shadow-xs hover:shadow-md hover:border-slate-300 dark:hover:border-white/20 transition-[transform,box-shadow,border-color] duration-200 ease-out hover:-translate-y-0.5 active:scale-[0.985] will-change-transform cursor-default',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                    {Icon && (
                        <div className="flex size-8 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-400/15 dark:text-blue-400 transition-colors">
                            <Icon className="size-4" />
                        </div>
                    )}
                    <span className="text-slate-600 dark:text-slate-400 text-[13px] leading-[18px] font-medium">
                        {label}
                    </span>
                </div>
                {hint && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                aria-label={`A propos de : ${label}`}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors"
                            >
                                <Info className="size-3.5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent className="apple-glass rounded-xl text-xs">{hint}</TooltipContent>
                    </Tooltip>
                )}
            </div>
            <p
                data-slot="stat-value"
                className="text-slate-900 dark:text-white mt-3 text-[28px] leading-[34px] font-bold tracking-tight"
            >
                {value}
            </p>
            {hasDelta && (
                <div className="mt-2.5 flex items-center gap-2">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold backdrop-blur-sm',
                            isUp && 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20',
                            isDown && 'bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/20',
                            !isUp &&
                                !isDown &&
                                'bg-slate-200/50 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                        )}
                    >
                        {isUp && <ArrowUp className="size-3" />}
                        {isDown && <ArrowDown className="size-3" />}
                        <span className="tabular">
                            {Math.abs(delta).toFixed(1)}%
                        </span>
                    </span>
                    <span className="text-slate-400 dark:text-slate-500 text-xs">
                        {deltaLabel}
                    </span>
                </div>
            )}
        </div>
    );
}
