<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TeamInvitation;
use App\Models\User;

class TeamInvitationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InviteUsers);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::InviteUsers);
    }

    /**
     * Determine whether the user can revoke the model: super admins any
     * link, admins the links of the team they run.
     */
    public function delete(User $user, TeamInvitation $teamInvitation): bool
    {
        return $user->can(Permission::InviteUsers)
            && ($user->isSuperAdmin() || $teamInvitation->team_id === $user->managedTeamId());
    }
}
