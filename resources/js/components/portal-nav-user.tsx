import { Link, router, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, ShieldCheck, UserRound } from 'lucide-react';
import { AvatarInitials } from '@/components/avatar-initials';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes/portal';
import { password, profile } from '@/routes/portal/settings';
import type { SharedData } from '@/types';

export function PortalNavUser() {
    const { auth } = usePage<SharedData>().props;
    const { state, isMobile } = useSidebar();
    const cleanup = useMobileNavigation();

    const alumni = auth?.alumni ?? null;

    if (alumni === null) {
        return null;
    }

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                        >
                            <AvatarInitials
                                firstName={alumni.first_name}
                                lastName={alumni.last_name}
                                className="size-8"
                            />
                            <div className="grid flex-1 text-left leading-tight">
                                <span className="text-text truncate text-sm font-medium">
                                    {alumni.full_name}
                                </span>
                                <span className="text-text-muted truncate text-xs">
                                    {alumni.email}
                                </span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side={
                            state === 'collapsed' && !isMobile ? 'right' : 'top'
                        }
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <AvatarInitials
                                    firstName={alumni.first_name}
                                    lastName={alumni.last_name}
                                    className="size-8"
                                />
                                <div className="grid flex-1 leading-tight">
                                    <span className="text-text truncate text-sm font-medium">
                                        {alumni.full_name}
                                    </span>
                                    <span className="text-text-muted truncate text-xs">
                                        {alumni.email}
                                    </span>
                                </div>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link
                                    className="block w-full cursor-pointer"
                                    href={profile()}
                                    prefetch
                                    onClick={cleanup}
                                >
                                    <UserRound className="mr-2" />
                                    Profil
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link
                                    className="block w-full cursor-pointer"
                                    href={password()}
                                    prefetch
                                    onClick={cleanup}
                                >
                                    <ShieldCheck className="mr-2" />
                                    Securite
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href={logout()}
                                as="button"
                                onClick={handleLogout}
                            >
                                <LogOut className="mr-2" />
                                Se deconnecter
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
