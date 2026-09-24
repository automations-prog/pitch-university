<?php

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view a user\'s progress in each assigned track', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $track = CourseTrack::factory()->create();
    $first = CourseModule::factory()->for($track, 'track')->create(['position' => 0]);
    $second = CourseModule::factory()->for($track, 'track')->create(['position' => 1]);
    $lessons = CourseLesson::factory()->count(2)->for($first, 'module')->sequence(['position' => 0], ['position' => 1])->create();
    CourseLesson::factory()->for($second, 'module')->create();

    $agent->courseTracks()->attach($track);
    $agent->completedCourseLessons()->attach($lessons);
    CourseQuizAttempt::factory()->failed()->for($agent)->for($first, 'module')->create();
    CourseQuizAttempt::factory()->for($agent)->for($first, 'module')->create(['score_pct' => 90]);

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.show', $agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/training-tracks/show')
            ->where('user.id', $agent->id)
            ->has('tracks', 1)
            ->where('tracks.0.slug', $track->slug)
            ->where('tracks.0.is_assigned', true)
            ->where('tracks.0.passed_modules', 1)
            ->where('tracks.0.total_modules', 2)
            ->where('tracks.0.modules.0.is_passed', true)
            ->where('tracks.0.modules.0.completed_lessons', 2)
            ->where('tracks.0.modules.0.best_score', 90)
            ->where('tracks.0.modules.0.attempts_count', 2)
            ->where('tracks.0.modules.0.attempts.0.score_pct', 90)
            ->where('tracks.0.modules.0.attempts.1.passed', false)
            ->where('tracks.0.modules.1.is_unlocked', true)
            ->where('tracks.0.modules.1.attempts', [])
            ->where('availableTracks.0.is_assigned', true));
});

test('tracks the user was removed from are shown only if they have progress', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $removedWithProgress = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($removedWithProgress, 'track')->create();
    CourseQuizAttempt::factory()->failed()->for($agent)->for($module, 'module')->create();
    CourseTrack::factory()->create(); // no progress, not assigned

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.show', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tracks', 1)
            ->where('tracks.0.slug', $removedWithProgress->slug)
            ->where('tracks.0.is_assigned', false)
            ->has('availableTracks', 2));
});

test('agents can not view a user\'s training progress', function () {
    $agent = User::factory()->create();

    $this->actingAs($agent)
        ->get(route('admin.training-tracks.show', User::factory()->create()))
        ->assertForbidden();
});
