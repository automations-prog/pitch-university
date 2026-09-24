import { Link, usePage } from '@inertiajs/react';
import {
    Award,
    ClipboardCheck,
    FileBarChart,
    GraduationCap,
    LayoutGrid,
    ShieldCheck,
    Target,
    ToggleRight,
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
import { index as trainingTracksIndex } from '@/routes/admin/training-tracks';
import { index as usersIndex } from '@/routes/admin/users';
import { index as verticalTrainingIndex } from '@/routes/admin/vertical-training';
import { index as myLicensesIndex } from '@/routes/licenses';
import { index as trainingIndex } from '@/routes/training';
import type { NavItem } from '@/types';

/**
 * Licensing ("Licensing" for admins, "My Licenses" for agents) is hidden from
 * the sidebar for now. Set to true to show it again.
 */
const SHOW_LICENSING = false;

export function AppSidebar() {
    const { auth } = usePage().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...((auth.user.course_tracks_count ?? 0) > 0
            ? [
                  {
                      title: 'My Training',
                      href: trainingIndex(),
                      icon: GraduationCap,
                  },
              ]
            : []),
        ...(SHOW_LICENSING && auth.user.licenses_count > 0
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
                  ...(SHOW_LICENSING
                      ? [
                            {
                                title: 'Licensing',
                                href: licensingIndex(),
                                icon: ShieldCheck,
                            },
                        ]
                      : []),
                  {
                      title: 'Training Tracks',
                      href: trainingTracksIndex(),
                      icon: ToggleRight,
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
