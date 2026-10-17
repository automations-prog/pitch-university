<?php

namespace App\Enums;

/**
 * Every page and admin action a role can be granted. Each case is stored
 * as a Spatie permission; the Roles & Permissions page edits which roles
 * hold which.
 */
enum Permission: string
{
    case ViewDashboard = 'dashboard.view';
    case ViewTraining = 'training.view';
    case ViewMedicareRoleplay = 'roleplay.medicare.view';
    case ViewAcaRoleplay = 'roleplay.aca.view';
    case ViewMealsRoleplay = 'roleplay.meals.view';
    case ViewLicenses = 'licenses.view';

    case ViewUsers = 'users.view';
    case CreateUsers = 'users.create';
    case UpdateUsers = 'users.update';
    case DeleteUsers = 'users.delete';
    case ImpersonateUsers = 'users.impersonate';
    case InviteUsers = 'users.invite';

    case ViewReports = 'reports.view';
    case ViewTrainingTracks = 'training-tracks.view';
    case ManageTrainingTracks = 'training-tracks.manage';
    case GrantExamRetakes = 'exam-retakes.grant';
    case ReviewRoleplaySessions = 'roleplay-sessions.review';

    case ViewScreening = 'screening.view';
    case ManageScreening = 'screening.manage';
    case ViewTrainingModules = 'training-modules.view';
    case ViewVerticalTraining = 'vertical-training.view';
    case ManageVerticalTraining = 'vertical-training.manage';
    case ViewLicensing = 'licensing.view';
    case ManageLicensing = 'licensing.manage';

    case ManageCampaigns = 'campaigns.manage';
    case ManageTeams = 'teams.manage';
    case ManageRoles = 'roles.manage';

    public function label(): string
    {
        return match ($this) {
            self::ViewDashboard => 'View dashboard',
            self::ViewTraining => 'View my training',
            self::ViewMedicareRoleplay => 'Medicare roleplay',
            self::ViewAcaRoleplay => 'ACA roleplay',
            self::ViewMealsRoleplay => 'Meals roleplay',
            self::ViewLicenses => 'View my licenses',
            self::ViewUsers => 'View users',
            self::CreateUsers => 'Create users',
            self::UpdateUsers => 'Edit users',
            self::DeleteUsers => 'Delete users',
            self::ImpersonateUsers => 'Impersonate users',
            self::InviteUsers => 'Create team invite links',
            self::ViewReports => 'View reports',
            self::ViewTrainingTracks => 'View training tracks',
            self::ManageTrainingTracks => 'Assign training tracks',
            self::GrantExamRetakes => 'Grant exam retakes',
            self::ReviewRoleplaySessions => 'Review roleplay attempts',
            self::ViewScreening => 'View screening',
            self::ManageScreening => 'Manage screening',
            self::ViewTrainingModules => 'View all training modules',
            self::ViewVerticalTraining => 'View vertical training',
            self::ManageVerticalTraining => 'Manage vertical training',
            self::ViewLicensing => 'View licensing',
            self::ManageLicensing => 'Manage licensing',
            self::ManageCampaigns => 'Manage campaigns',
            self::ManageTeams => 'Manage teams',
            self::ManageRoles => 'Manage roles & permissions',
        };
    }

    /**
     * The page group the permission is listed under.
     */
    public function group(): string
    {
        return match ($this) {
            self::ViewDashboard, self::ViewTraining, self::ViewLicenses => 'General',
            self::ViewMedicareRoleplay, self::ViewAcaRoleplay, self::ViewMealsRoleplay => 'Roleplay',
            self::ViewUsers, self::CreateUsers, self::UpdateUsers, self::DeleteUsers, self::ImpersonateUsers, self::InviteUsers => 'Users',
            self::ViewReports, self::ViewTrainingTracks, self::ManageTrainingTracks, self::GrantExamRetakes, self::ReviewRoleplaySessions => 'Performance & quality',
            self::ViewScreening, self::ManageScreening => 'Screening',
            self::ViewTrainingModules, self::ViewVerticalTraining, self::ManageVerticalTraining, self::ViewLicensing, self::ManageLicensing => 'Content',
            self::ManageCampaigns, self::ManageTeams, self::ManageRoles => 'System',
        };
    }

    /**
     * The roleplay vertical this permission opens, if it is a roleplay one.
     */
    public function roleplayVertical(): ?RoleplayVertical
    {
        return match ($this) {
            self::ViewMedicareRoleplay => RoleplayVertical::Medicare,
            self::ViewAcaRoleplay => RoleplayVertical::Aca,
            self::ViewMealsRoleplay => RoleplayVertical::Meals,
            default => null,
        };
    }

    /**
     * The permission that opens the given roleplay vertical's pages.
     */
    public static function forRoleplay(RoleplayVertical $vertical): self
    {
        return match ($vertical) {
            RoleplayVertical::Medicare => self::ViewMedicareRoleplay,
            RoleplayVertical::Aca => self::ViewAcaRoleplay,
            RoleplayVertical::Meals => self::ViewMealsRoleplay,
        };
    }

    /**
     * The permissions each role starts with. Super admins always hold
     * every permission.
     *
     * @return list<self>
     */
    public static function defaultsFor(UserRole $role): array
    {
        $agent = [
            self::ViewDashboard,
            self::ViewTraining,
            self::ViewMedicareRoleplay,
            self::ViewAcaRoleplay,
            self::ViewMealsRoleplay,
            self::ViewLicenses,
        ];

        return match ($role) {
            UserRole::SuperAdmin => self::cases(),
            UserRole::Admin => [
                ...$agent,
                self::ViewUsers,
                self::ViewReports,
                self::ViewTrainingTracks,
                self::GrantExamRetakes,
                self::ReviewRoleplaySessions,
                self::CreateUsers,
                self::UpdateUsers,
                self::DeleteUsers,
                self::ImpersonateUsers,
                self::InviteUsers,
                self::ManageTrainingTracks,
                self::ViewTrainingModules,
                self::ViewVerticalTraining,
                self::ManageVerticalTraining,
                self::ViewLicensing,
                self::ManageLicensing,
            ],
            UserRole::Agent => $agent,
        };
    }
}
