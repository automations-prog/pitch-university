<?php

namespace App\Policies;

use App\Models\ScreeningResponse;
use App\Models\User;

class ScreeningResponsePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ScreeningResponse $screeningResponse): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ScreeningResponse $screeningResponse): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ScreeningResponse $screeningResponse): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ScreeningResponse $screeningResponse): bool
    {
        return false;
    }
}
