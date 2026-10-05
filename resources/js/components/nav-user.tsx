import { usePage } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppearance } from '@/hooks/use-appearance';
import { useIsMobile } from '@/hooks/use-mobile';

export function NavUser() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const ThemeIcon = resolvedAppearance === 'dark' ? Sun : Moon;

    if (!auth.user) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem className="flex items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            data-test="sidebar-menu-button"
                        >
                            <UserInfo user={auth.user} showRole />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        }
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
                <button
                    type="button"
                    onClick={() =>
                        updateAppearance(
                            resolvedAppearance === 'dark' ? 'light' : 'dark',
                        )
                    }
                    className="flex size-8 shrink-0 items-center justify-center rounded-md border border-sidebar-border text-sidebar-foreground/80 transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground group-data-[collapsible=icon]:hidden"
                >
                    <ThemeIcon className="size-3.5" />
                    <span className="sr-only">Toggle theme</span>
                </button>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
