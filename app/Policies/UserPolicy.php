<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewUsers);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::ViewUsers) && $user->canSee($model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::CreateUsers);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UpdateUsers) && ($user->canManage($model) || ($user->isSuperAdmin() && $user->is($model)));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::DeleteUsers) && $user->canManage($model);
    }

    /**
     * Determine whether the user can impersonate the model.
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->can(Permission::ImpersonateUsers) && $user->canManage($model) && $model->canBeImpersonated();
    }

    /**
     * Determine whether the user can see the model's training progress.
     */
    public function viewProgress(User $user, User $model): bool
    {
        return $user->can(Permission::ViewTrainingTracks) && $user->canSee($model);
    }

    /**
     * Determine whether the user can assign training tracks to the model.
     */
    public function assignTracks(User $user, User $model): bool
    {
        return $user->can(Permission::ManageTrainingTracks) && ($user->canManage($model) || $user->isSuperAdmin());
    }

    /**
     * Determine whether the user can grant the model an exam retake.
     */
    public function grantExamRetake(User $user, User $model): bool
    {
        return $user->can(Permission::GrantExamRetakes) && $user->canSee($model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
