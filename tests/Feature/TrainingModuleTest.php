<?php

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A track with two modules of two lessons each. Nobody has passed the
 * first, so in My Training the second is locked.
 *
 * @return array{0: CourseTrack, 1: CourseModule, 2: CourseModule}
 */
function trackWithTwoModules(): array
{
    $track = CourseTrack::factory()->create();
    $modules = [];

    foreach ([0, 1] as $position) {
        $module = CourseModule::factory()->for($track, 'track')->create(['position' => $position]);
        CourseLesson::factory()->count(2)->sequence(['position' => 0], ['position' => 1])->for($module, 'module')->create();
        $modules[] = $module;
    }

    return [$track, ...$modules];
}

test('every track is listed, including ones not assigned to the viewer', function () {
    [$track] = trackWithTwoModules();
    $other = CourseTrack::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('training-modules.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training-modules/index')
            ->has('tracks', 2)
            ->where('tracks.0.slug', $track->slug)
            ->where('tracks.0.module_count', 2)
            ->where('tracks.1.slug', $other->slug));
});

test('a module locked in My Training opens here, with its lessons and practice quiz', function () {
    [$track, , $lockedModule] = trackWithTwoModules();
    $lesson = $lockedModule->lessons()->first();
    $admin = User::factory()->admin()->create();
    $question = $lockedModule->quiz['questions'][0];

    $this->actingAs($admin)
        ->get(route('training-modules.modules.show', [$track, $lockedModule]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training-modules/module')
            ->has('lessons', 2));

    $this->actingAs($admin)
        ->get(route('training-modules.lessons.show', [$track, $lockedModule, $lesson]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training-modules/lesson')
            ->where('lesson.slug', $lesson->slug)
            ->where('lessonNumber', 1));

    $this->actingAs($admin)
        ->get(route('training-modules.quiz.show', [$track, $lockedModule]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training-modules/quiz')
            ->where('questions.0.id', $question['id'])
            ->where('questions.0.answer_index', $question['answer_index']));
});

test('browsing the modules records no lesson progress or quiz attempts', function () {
    [$track, $module] = trackWithTwoModules();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('training-modules.lessons.show', [$track, $module, $module->lessons()->first()]))->assertOk();
    $this->actingAs($admin)->get(route('training-modules.quiz.show', [$track, $module]))->assertOk();

    expect($admin->completedCourseLessons()->count())->toBe(0);
    $this->assertDatabaseEmpty('course_quiz_attempts');
});

test('a lesson is only reachable under its own module', function () {
    [$track, $module, $otherModule] = trackWithTwoModules();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('training-modules.lessons.show', [$track, $module, $otherModule->lessons()->first()]))
        ->assertNotFound();
});
