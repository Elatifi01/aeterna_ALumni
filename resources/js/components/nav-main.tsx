import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/lib/permissions';
import type { NavSection } from '@/types';

export function NavMain({ sections }: { sections: NavSection[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { can } = usePermissions();

    return (
        <>
            {sections.map((section) => {
                const items = section.items.filter(
                    (item) => !item.permission || can(item.permission),
                );

                if (items.length === 0) {
                    return null;
                }

                return (
                    <SidebarGroup key={section.label} className="px-2 py-0">
                        <SidebarGroupLabel>{section.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                    {item.badge !== undefined &&
                                        item.badge > 0 && (
                                            <SidebarMenuBadge>
                                                {item.badge}
                                            </SidebarMenuBadge>
                                        )}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </>
    );
}
