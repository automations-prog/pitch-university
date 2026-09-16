import { Link, usePage } from '@inertiajs/react';
import {
    Award,
    ClipboardCheck,
    FileBarChart,
    LayoutGrid,
    ShieldCheck,
    Target,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as licensingIndex } from '@/routes/admin/licensing';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as screeningIndex } from '@/routes/admin/screening';
import { index as usersIndex } from '@/routes/admin/users';
import { index as verticalTrainingIndex } from '@/routes/admin/vertical-training';
import { index as myLicensesIndex } from '@/routes/licenses';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(auth.user.licenses_count > 0
            ? [
                  {
                      title: 'My Licenses',
                      href: myLicensesIndex(),
                      icon: Award,
                  },
              ]
            : []),
        ...(auth.user.role === 'admin'
            ? [
                  {
                      title: 'Licensing',
                      href: licensingIndex(),
                      icon: ShieldCheck,
                  },
                  {
                      title: 'Vertical Training',
                      href: verticalTrainingIndex(),
                      icon: Target,
                  },
                  {
                      title: 'Screening',
                      href: screeningIndex(),
                      icon: ClipboardCheck,
                  },
                  {
                      title: 'Users',
                      href: usersIndex(),
                      icon: Users,
                  },
                  {
                      title: 'Reports',
                      href: reportsIndex(),
                      icon: FileBarChart,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
