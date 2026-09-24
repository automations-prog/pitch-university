<?php

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view progress and best quiz scores for the selected track', function () {
    $admin = User::factory()->admin()->create();
    $track = CourseTrack::factory()->create(['position' => 1]);
    $module = CourseModule::factory()->for($track, 'track')->create();
    $lesson = CourseLesson::factory()->for($module, 'module')->create();

    $learner = User::factory()->create(['name' => 'A Learner']);
    $learner->courseTracks()->attach($track);
    $learner->completedCourseLessons()->attach($lesson);
    CourseQuizAttempt::factory()->failed()->for($learner)->for($module, 'module')->create();
    CourseQuizAttempt::factory()->for($learner)->for($module, 'module')->create(['score_pct' => 90]);

    $notStarted = User::factory()->create(['name' => 'B Not Started']);
    $notStarted->courseTracks()->attach($track);

    User::factory()->create(); // not assigned, should not appear

    $otherTrack = CourseTrack::factory()->create(['position' => 0]);
    User::factory()->create()->courseTracks()->attach($otherTrack);

    $this->actingAs($admin)
        ->get(route('admin.training.progress', ['track' => $track->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/training/progress')
            ->where('filters.track', $track->slug)
            ->has('tracks', 2)
            ->has('users.data', 2)
            ->where('users.data.0.id', $learner->id)
            ->where('users.data.0.is_assigned', true)
            ->where('users.data.0.completed_lessons', 1)
            ->where("users.data.0.modules.{$module->slug}.best_score", 90)
            ->where("users.data.0.modules.{$module->slug}.passed", true)
            ->where("users.data.0.modules.{$module->slug}.attempts_count", 2)
            ->where('users.data.1.id', $notStarted->id)
            ->where("users.data.1.modules.{$module->slug}", null)
            ->where('totalLessons', 1));
});

test('the report defaults to the first track', function () {
    $first = CourseTrack::factory()->create(['position' => 0]);
    CourseTrack::factory()->create(['position' => 1]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.training.progress'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.track', $first->slug));
});

test('agents can not view the training progress report', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.training.progress'))
        ->assertForbidden();
});
