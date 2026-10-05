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
import { cn } from '@/lib/utils';
import type { NavGroup } from '@/types';

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <>
            {groups
                .filter((group) => group.items.length > 0)
                .map((group, index) => (
                    <SidebarGroup
                        key={group.label ?? index}
                        className="px-2 py-0"
                    >
                        {group.label && (
                            <SidebarGroupLabel className="text-[10px] font-semibold tracking-wider text-sidebar-foreground/60 uppercase">
                                {group.label}
                            </SidebarGroupLabel>
                        )}
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentOrParentUrl(
                                            item.href,
                                        )}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link href={item.href} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                    {item.badge ? (
                                        <SidebarMenuBadge
                                            className={cn(
                                                'right-1.5 h-4.5 rounded-full bg-amber-300 px-1.5 text-[10px] font-semibold text-black! dark:bg-amber-300',
                                                item.badgeHref &&
                                                    'pointer-events-auto hover:bg-amber-200',
                                            )}
                                        >
                                            {item.badgeHref ? (
                                                <Link
                                                    href={item.badgeHref}
                                                    prefetch
                                                >
                                                    {item.badge}
                                                </Link>
                                            ) : (
                                                item.badge
                                            )}
                                        </SidebarMenuBadge>
                                    ) : null}
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
        </>
    );
}
