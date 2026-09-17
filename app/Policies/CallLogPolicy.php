<?php

namespace App\Policies;

use App\Models\CallLog;
use App\Models\User;

class CallLogPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CallLog $callLog): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CallLog $callLog): bool
    {
        return $user->isAdmin();
    }
}
