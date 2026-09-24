<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseTrack;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserTrackController extends Controller
{
    /**
     * Give the user access to the given training track.
     */
    public function store(User $user, CourseTrack $track): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->courseTracks()->syncWithoutDetaching([$track->id]);

        return back();
    }

    /**
     * Remove the user's access to the given training track. Their progress is kept.
     */
    public function destroy(User $user, CourseTrack $track): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->courseTracks()->detach($track->id);

        return back();
    }
}
