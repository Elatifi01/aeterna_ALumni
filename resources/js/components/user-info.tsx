import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <div className="relative size-8 shrink-0">
                <Avatar className="size-8 overflow-hidden rounded-full">
                    <AvatarImage src={user.avatar || '/images/avatars/admin.jpg'} alt={user.name} />
                    <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                        {getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="absolute -bottom-0.5 -right-0.5 px-0.5 min-w-[14px] h-3.5 rounded-full bg-slate-900 border border-white/40 text-[7px] font-bold text-white flex items-center justify-center leading-none">
                    {getInitials(user.name)}
                </span>
            </div>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="text-muted-foreground truncate text-xs">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
