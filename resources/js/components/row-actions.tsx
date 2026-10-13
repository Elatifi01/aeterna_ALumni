import { Link } from '@inertiajs/react';
import { Eye, Pencil, Trash2 } from 'lucide-react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type Href = NonNullable<InertiaLinkProps['href']>;

type Props = {
    label: string;
    show?: Href;
    edit?: Href;
    onDelete?: () => void;
    children?: ReactNode;
};

export function RowActions({ label, show, edit, onDelete, children }: Props) {
    return (
        <div className="flex items-center justify-end gap-0.5">
            {show && (
                <Action
                    tooltip="Voir"
                    ariaLabel={`Voir ${label}`}
                    href={show}
                    icon={<Eye className="size-4" />}
                />
            )}

            {edit && (
                <Action
                    tooltip="Modifier"
                    ariaLabel={`Modifier ${label}`}
                    href={edit}
                    icon={<Pencil className="size-4" />}
                />
            )}

            {children}

            {onDelete && (
                <Action
                    tooltip="Supprimer"
                    ariaLabel={`Supprimer ${label}`}
                    onClick={onDelete}
                    icon={<Trash2 className="size-4" />}
                    destructive
                />
            )}
        </div>
    );
}

function Action({
    tooltip,
    ariaLabel,
    href,
    onClick,
    icon,
    destructive,
}: {
    tooltip: string;
    ariaLabel: string;
    href?: Href;
    onClick?: () => void;
    icon: ReactNode;
    destructive?: boolean;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                {href ? (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="text-text-secondary hover:text-text size-8"
                        aria-label={ariaLabel}
                        asChild
                    >
                        <Link href={href}>{icon}</Link>
                    </Button>
                ) : (
                    <Button
                        variant="ghost"
                        size="icon"
                        className={
                            destructive
                                ? 'text-text-secondary hover:text-danger size-8'
                                : 'text-text-secondary hover:text-text size-8'
                        }
                        aria-label={ariaLabel}
                        onClick={onClick}
                    >
                        {icon}
                    </Button>
                )}
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}
