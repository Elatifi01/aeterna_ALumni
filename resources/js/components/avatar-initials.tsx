import { useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    firstName: string;
    lastName: string;
    className?: string;
    avatarUrl?: string;
};

const AVATAR_POOL = [
    '/images/avatars/alumni-1.jpg',
    '/images/avatars/alumni-2.jpg',
    '/images/avatars/alumni-3.jpg',
    '/images/avatars/alumni-4.jpg',
    '/images/avatars/alumni-5.jpg',
];

export function AvatarInitials({ firstName, lastName, className, avatarUrl }: Props) {
    const [imageError, setImageError] = useState(false);

    const safeFirst = firstName || '';
    const safeLast = lastName || '';
    const initials =
        `${safeFirst.charAt(0)}${safeLast.charAt(0)}`.toUpperCase() || 'AL';

    // Deterministic selection from avatar pool based on name hash
    const seed = (safeFirst + safeLast)
        .split('')
        .reduce((acc, char) => acc + char.charCodeAt(0), 0);
    const resolvedAvatar = avatarUrl || AVATAR_POOL[Math.abs(seed) % AVATAR_POOL.length];

    if (imageError) {
        return (
            <span
                aria-hidden
                className={cn(
                    'bg-brand-light/30 text-primary dark:text-text flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                    className,
                )}
            >
                {initials}
            </span>
        );
    }

    return (
        <div className={cn('relative inline-flex size-9 shrink-0 items-center justify-center', className)}>
            <img
                src={resolvedAvatar}
                alt={`${safeFirst} ${safeLast}`}
                className="size-full rounded-full object-cover border border-slate-200/80 dark:border-white/20 shadow-sm"
                loading="lazy"
                onError={() => setImageError(true)}
            />
            {/* Real avatar badge showing initials */}
            <span
                aria-hidden
                className="absolute -bottom-1 -right-1 px-1 min-w-[16px] h-3.5 rounded-full bg-slate-900/95 text-white border border-white/40 text-[8px] font-bold flex items-center justify-center leading-none shadow-sm"
            >
                {initials}
            </span>
        </div>
    );
}
