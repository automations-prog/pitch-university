import { Link, usePage, usePoll } from '@inertiajs/react';
import {
    Award,
    ClipboardCheck,
    FileText,
    GraduationCap,
    Headphones,
    House,
    ShieldCheck,
    Target,
    ToggleLeft,
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
import { index as roleplayIndex } from '@/routes/roleplay';
import { index as trainingIndex } from '@/routes/training';
import type { NavGroup } from '@/types';

/**
 * Licensing ("Licensing" for admins, "My Licenses" for agents) is hidden from
 * the sidebar for now. Set to true to show it again.
 */
const SHOW_LICENSING = false;

const SCREENING_BADGE_POLL_MS = 30_000;

export function AppSidebar() {
    const { auth, screeningAwaitingReview } = usePage().props;
    const isAdmin = auth.user.role === 'admin';

    // Keep the screening badge current while candidates finish AI calls.
    usePoll(
        SCREENING_BADGE_POLL_MS,
        { only: ['screeningAwaitingReview'] },
        { autoStart: isAdmin },
    );

    const navGroups: NavGroup[] = [
        {
            items: [{ title: 'Home', href: dashboard(), icon: House }],
        },
        {
            label: 'Learning',
            items: [
                ...((auth.user.course_tracks_count ?? 0) > 0
                    ? [
                          {
                              title: 'My Training',
                              href: trainingIndex(),
                              icon: GraduationCap,
                          },
                      ]
                    : []),
                ...(isAdmin
                    ? [
                          {
                              title: 'Training Tracks',
                              href: trainingTracksIndex(),
                              icon: ToggleLeft,
                          },
                          {
                              title: 'Vertical Training',
                              href: verticalTrainingIndex(),
                              icon: Target,
                          },
                      ]
                    : []),
                {
                    title: 'Roleplay',
                    href: roleplayIndex(),
                    icon: Headphones,
                },
                ...(SHOW_LICENSING && auth.user.licenses_count > 0
                    ? [
                          {
                              title: 'My Licenses',
                              href: myLicensesIndex(),
                              icon: Award,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: 'Hiring & Team',
            items: isAdmin
                ? [
                      {
                          title: 'Screening',
                          href: screeningIndex(),
                          icon: ClipboardCheck,
                          badge: screeningAwaitingReview
                              ? `${screeningAwaitingReview} new`
                              : null,
                          badgeHref: screeningIndex({
                              query: { status: 'awaiting_review' },
                          }),
                      },
                      {
                          title: 'Users',
                          href: usersIndex(),
                          icon: Users,
                      },
                      {
                          title: 'Reports',
                          href: reportsIndex(),
                          icon: FileText,
                      },
                      ...(SHOW_LICENSING
                          ? [
                                {
                                    title: 'Licensing',
                                    href: licensingIndex(),
                                    icon: ShieldCheck,
                                },
                            ]
                          : []),
                  ]
                : [],
        },
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
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
