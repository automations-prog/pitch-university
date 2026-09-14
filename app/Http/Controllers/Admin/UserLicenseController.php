<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserLicenseController extends Controller
{
    /**
     * Assign the given license to the user.
     */
    public function store(User $user, License $license): RedirectResponse
    {
        Gate::authorize('update', $user);

        if (! $license->isActive()) {
            throw ValidationException::withMessages([
                'license' => __('Inactive licenses can\'t be assigned.'),
            ]);
        }

        $user->licenses()->syncWithoutDetaching([$license->id]);

        return to_route('admin.users.edit', $user);
    }

    /**
     * Remove the given license from the user.
     */
    public function destroy(User $user, License $license): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->licenses()->detach($license->id);

        return to_route('admin.users.edit', $user);
    }
}
