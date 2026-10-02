<?php

namespace App\Policies;

use App\Models\RoleplaySession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RoleplaySessionPolicy
{
    /**
     * Determine whether the user can view the model. Other users' sessions
     * 404 so their existence isn't leaked.
     */
    public function view(User $user, RoleplaySession $roleplaySession): Response
    {
        return $user->id === $roleplaySession->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RoleplaySession $roleplaySession): Response
    {
        return $this->view($user, $roleplaySession);
    }
}
