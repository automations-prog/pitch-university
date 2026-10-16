import { Link, usePage, usePoll } from '@inertiajs/react';
import {
    Award,
    BookOpen,
    Building2,
    ClipboardCheck,
    KeyRound,
    Link2,
    FileText,
    GraduationCap,
    Headphones,
    House,
    ShieldCheck,
    Target,
    ToggleLeft,
    Users,
    UsersRound,
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
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import { index as acaRoleplayIndex } from '@/routes/aca-roleplay';
import { index as campaignsIndex } from '@/routes/admin/campaigns';
import { index as invitationsIndex } from '@/routes/admin/invitations';
import { index as licensingIndex } from '@/routes/admin/licensing';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as screeningIndex } from '@/routes/admin/screening';
import { index as teamsIndex } from '@/routes/admin/teams';
import { index as trainingTracksIndex } from '@/routes/admin/training-tracks';
import { index as usersIndex } from '@/routes/admin/users';
import { index as verticalTrainingIndex } from '@/routes/admin/vertical-training';
import { index as myLicensesIndex } from '@/routes/licenses';
import { index as mealsRoleplayIndex } from '@/routes/meals-roleplay';
import { index as medicareRoleplayIndex } from '@/routes/medicare-roleplay';
import { index as trainingIndex } from '@/routes/training';
import { index as trainingModulesIndex } from '@/routes/training-modules';
import type { NavGroup, NavItem } from '@/types';

/**
 * Licensing ("Licensing" for admins, "My Licenses" for agents) is hidden from
 * the sidebar for now. Set to true to show it again.
 */
const SHOW_LICENSING = false;

const SCREENING_BADGE_POLL_MS = 30_000;

export function AppSidebar() {
    const { auth, screeningAwaitingReview } = usePage().props;
    const can = useCan();
    const canViewScreening = can('screening.view');

    // Keep the screening badge current while candidates finish AI calls.
    usePoll(
        SCREENING_BADGE_POLL_MS,
        { only: ['screeningAwaitingReview'] },
        { autoStart: canViewScreening },
    );

    /** Keep only the items the user holds the permission for. */
    const allowed = (items: (NavItem & { permission: string })[]): NavItem[] =>
        items
            .filter((item) => can(item.permission))
            .map(({ permission: _permission, ...item }) => item);

    const navGroups: NavGroup[] = [
        {
            items: allowed([
                {
                    title: 'Home',
                    href: dashboard(),
                    icon: House,
                    permission: 'dashboard.view',
                },
            ]),
        },
        {
            label: 'Learning',
            items: allowed([
                ...((auth.user.course_tracks_count ?? 0) > 0
                    ? [
                          {
                              title: 'My Training',
                              href: trainingIndex(),
                              icon: GraduationCap,
                              permission: 'training.view',
                          },
                      ]
                    : []),
                {
                    title: 'Training Tracks',
                    href: trainingTracksIndex(),
                    icon: ToggleLeft,
                    permission: 'training-tracks.view',
                },
                {
                    title: 'Training Modules',
                    href: trainingModulesIndex(),
                    icon: BookOpen,
                    permission: 'training-modules.view',
                },
                {
                    title: 'Vertical Training',
                    href: verticalTrainingIndex(),
                    icon: Target,
                    permission: 'vertical-training.view',
                },
                {
                    title: 'Medicare Roleplay',
                    href: medicareRoleplayIndex(),
                    icon: Headphones,
                    permission: 'roleplay.medicare.view',
                },
                {
                    title: 'ACA Roleplay',
                    href: acaRoleplayIndex(),
                    icon: Headphones,
                    permission: 'roleplay.aca.view',
                },
                {
                    title: 'Meals Roleplay',
                    href: mealsRoleplayIndex(),
                    icon: Headphones,
                    permission: 'roleplay.meals.view',
                },
                ...(SHOW_LICENSING && auth.user.licenses_count > 0
                    ? [
                          {
                              title: 'My Licenses',
                              href: myLicensesIndex(),
                              icon: Award,
                              permission: 'licenses.view',
                          },
                      ]
                    : []),
            ]),
        },
        {
            label: 'Hiring & Team',
            items: allowed([
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
                    permission: 'screening.view',
                },
                {
                    title: 'Users',
                    href: usersIndex(),
                    icon: Users,
                    permission: 'users.view',
                },
                {
                    title: 'Invite Links',
                    href: invitationsIndex(),
                    icon: Link2,
                    permission: 'users.invite',
                },
                {
                    title: 'Reports',
                    href: reportsIndex(),
                    icon: FileText,
                    permission: 'reports.view',
                },
                ...(SHOW_LICENSING
                    ? [
                          {
                              title: 'Licensing',
                              href: licensingIndex(),
                              icon: ShieldCheck,
                              permission: 'licensing.view',
                          },
                      ]
                    : []),
            ]),
        },
        {
            label: 'System',
            items: allowed([
                {
                    title: 'Campaigns',
                    href: campaignsIndex(),
                    icon: Building2,
                    permission: 'campaigns.manage',
                },
                {
                    title: 'Teams',
                    href: teamsIndex(),
                    icon: UsersRound,
                    permission: 'teams.manage',
                },
                {
                    title: 'Roles & Permissions',
                    href: rolesIndex(),
                    icon: KeyRound,
                    permission: 'roles.manage',
                },
            ]),
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
