<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RoleplaySession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RoleplaySessionPolicy
{
    /**
     * Determine whether the user can view the model. Reviewers see the
     * training calls of agents on their teams; other users' sessions 404
     * so their existence isn't leaked.
     */
    public function view(User $user, RoleplaySession $roleplaySession): Response
    {
        return $user->can(Permission::ReviewRoleplaySessions) && $user->canSee($roleplaySession->user)
            ? Response::allow()
            : $this->update($user, $roleplaySession);
    }

    /**
     * Determine whether the user can update the model. Only the trainee
     * runs their own call.
     */
    public function update(User $user, RoleplaySession $roleplaySession): Response
    {
        return $user->id === $roleplaySession->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
