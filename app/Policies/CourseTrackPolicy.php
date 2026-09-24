<?php

namespace App\Policies;

use App\Models\CourseTrack;
use App\Models\User;

class CourseTrackPolicy
{
    /**
     * Determine whether the user can view the model.
     *
     * Everyone, admins included, can only view the tracks toggled on for them.
     */
    public function view(User $user, CourseTrack $courseTrack): bool
    {
        return $user->courseTracks()->whereKey($courseTrack->id)->exists();
    }
}
