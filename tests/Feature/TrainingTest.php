<?php

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a module in the track with the given number of lessons and a
 * five-question quiz whose correct answer is always the first choice.
 */
function trackModuleWithLessons(CourseTrack $track, int $position = 0, int $lessonCount = 2): CourseModule
{
    $module = CourseModule::factory()->for($track, 'track')->create(['position' => $position]);

    foreach (range(0, $lessonCount - 1) as $lessonPosition) {
        CourseLesson::factory()->for($module, 'module')->create(['position' => $lessonPosition]);
    }

    return $module;
}

/**
 * Build quiz answers for the module with the given number of correct answers.
 *
 * @return array<string, int>
 */
function quizAnswers(CourseModule $module, int $correctCount): array
{
    return collect($module->quiz['questions'])
        ->values()
        ->mapWithKeys(fn (array $question, int $index) => [$question['id'] => $index < $correctCount ? $question['answer_index'] : 1])
        ->all();
}

/**
 * Create an agent who has been assigned the given track.
 */
function agentAssignedTo(CourseTrack $track): User
{
    $agent = User::factory()->create();
    $agent->courseTracks()->attach($track);

    return $agent;
}

test('guests are redirected to the login page', function () {
    $this->get(route('training.index'))->assertRedirect(route('login'));
});

test('agents only see the tracks assigned to them', function () {
    $assigned = CourseTrack::factory()->create();
    CourseTrack::factory()->create();
    $agent = agentAssignedTo($assigned);

    $this->actingAs($agent)
        ->get(route('training.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('training/index')
            ->has('tracks', 1)
            ->where('tracks.0.slug', $assigned->slug));
});

test('admins only see and open tracks toggled on for them too', function () {
    $assigned = CourseTrack::factory()->create();
    $unassigned = CourseTrack::factory()->create();
    $admin = User::factory()->admin()->create();
    $admin->courseTracks()->attach($assigned);

    $this->actingAs($admin)
        ->get(route('training.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tracks', 1)
            ->where('tracks.0.slug', $assigned->slug));

    $this->actingAs($admin)->get(route('training.tracks.show', $assigned))->assertOk();
    $this->actingAs($admin)->get(route('training.tracks.show', $unassigned))->assertForbidden();
});

test('agents can not open a track that is not assigned to them', function () {
    $track = CourseTrack::factory()->create();
    $module = trackModuleWithLessons($track);
    $agent = agentAssignedTo(CourseTrack::factory()->create());

    $this->actingAs($agent)->get(route('training.tracks.show', $track))->assertForbidden();
    $this->actingAs($agent)->get(route('training.modules.show', [$track, $module]))->assertForbidden();
    $this->actingAs($agent)
        ->post(route('training.lessons.complete', [$track, $module, $module->lessons->first()]))
        ->assertForbidden();
});

test('the next module stays locked until the previous module quiz is passed', function () {
    $track = CourseTrack::factory()->create();
    $first = trackModuleWithLessons($track, 0);
    $second = trackModuleWithLessons($track, 1);
    $agent = agentAssignedTo($track);

    $this->actingAs($agent)
        ->get(route('training.tracks.show', $track))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training/track')
            ->has('modules', 2)
            ->where('modules.0.progress.is_unlocked', true)
            ->where('modules.1.progress.is_unlocked', false));

    $this->actingAs($agent)->get(route('training.modules.show', [$track, $second]))->assertForbidden();

    CourseQuizAttempt::factory()->failed()->for($agent)->for($first, 'module')->create();
    $this->actingAs($agent)->get(route('training.modules.show', [$track, $second]))->assertForbidden();

    CourseQuizAttempt::factory()->for($agent)->for($first, 'module')->create();
    $this->actingAs($agent)->get(route('training.modules.show', [$track, $second]))->assertOk();
});

test('completing a lesson records it and moves to the next lesson', function () {
    $track = CourseTrack::factory()->create();
    $module = trackModuleWithLessons($track);
    [$first, $second] = $module->lessons;
    $agent = agentAssignedTo($track);

    $this->actingAs($agent)
        ->post(route('training.lessons.complete', [$track, $module, $first]))
        ->assertRedirect(route('training.lessons.show', [$track, $module, $second]));

    $this->actingAs($agent)
        ->post(route('training.lessons.complete', [$track, $module, $second]))
        ->assertRedirect(route('training.modules.show', [$track, $module]));

    expect($agent->completedCourseLessons()->pluck('course_lessons.id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id]);
});

test('a module or lesson from another track or module returns not found', function () {
    $track = CourseTrack::factory()->create();
    $module = trackModuleWithLessons($track, 0);
    $otherLesson = trackModuleWithLessons($track, 1)->lessons->first();
    $otherTrackModule = trackModuleWithLessons(CourseTrack::factory()->create());
    $agent = agentAssignedTo($track);

    $this->actingAs($agent)
        ->get(route('training.lessons.show', [$track, $module, $otherLesson]))
        ->assertNotFound();

    $this->actingAs($agent)
        ->get(route('training.modules.show', [$track, $otherTrackModule]))
        ->assertNotFound();
});

test('the quiz stays locked until every lesson is complete', function () {
    $track = CourseTrack::factory()->create();
    $module = trackModuleWithLessons($track);
    $agent = agentAssignedTo($track);

    $agent->completedCourseLessons()->attach($module->lessons->first());

    $this->actingAs($agent)->get(route('training.quiz.show', [$track, $module]))->assertForbidden();
    $this->actingAs($agent)
        ->post(route('training.quiz.store', [$track, $module]), ['answers' => quizAnswers($module, 5)])
        ->assertForbidden();

    $agent->completedCourseLessons()->attach($module->lessons->last());

    $this->actingAs($agent)->get(route('training.quiz.show', [$track, $module]))->assertOk();
});

test('the quiz page does not expose correct answers before an attempt', function () {
    $track = CourseTrack::factory()->create();
    $module = trackModuleWithLessons($track);
    $agent = agentAssignedTo($track);
    $agent->completedCourseLessons()->attach($module->lessons);

    $this->actingAs($agent)
        ->get(route('training.quiz.show', [$track, $module]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training/quiz')
            ->has('questions', 5)
            ->missing('questions.0.answer_index')
            ->missing('questions.0.explanation')
            ->where('latestAttempt', null));
});

test('quiz attempts are graded against the pass percentage', function (int $correctCount, int $expectedScore, bool $expectedPassed) {
    $track = CourseTrack::factory()->create();
    $module = trackModuleWithLessons($track);
    $agent = agentAssignedTo($track);
    $agent->completedCourseLessons()->attach($module->lessons);

    $this->actingAs($agent)
        ->post(route('training.quiz.store', [$track, $module]), ['answers' => quizAnswers($module, $correctCount)])
        ->assertRedirect(route('training.quiz.show', [$track, $module]));

    $attempt = $agent->courseQuizAttempts()->sole();
    expect($attempt->score_pct)->toBe($expectedScore)
        ->and($attempt->passed)->toBe($expectedPassed);

    $this->actingAs($agent)
        ->get(route('training.quiz.show', [$track, $module]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestAttempt.score_pct', $expectedScore)
            ->where('latestAttempt.passed', $expectedPassed)
            ->where('latestAttempt.results.0.answer_index', 0)
            ->where('isPassed', $expectedPassed));
})->with([
    'all correct' => [5, 100, true],
    'exactly the pass mark' => [4, 80, true],
    'below the pass mark' => [3, 60, false],
]);

test('every quiz question must be answered with a valid choice', function () {
    $track = CourseTrack::factory()->create();
    $module = trackModuleWithLessons($track);
    $agent = agentAssignedTo($track);
    $agent->completedCourseLessons()->attach($module->lessons);

    $answers = quizAnswers($module, 5);
    $questionIds = array_keys($answers);
    unset($answers[$questionIds[0]]);
    $answers[$questionIds[1]] = 9;

    $this->actingAs($agent)
        ->post(route('training.quiz.store', [$track, $module]), ['answers' => $answers])
        ->assertSessionHasErrors(["answers.{$questionIds[0]}", "answers.{$questionIds[1]}"]);

    expect($agent->courseQuizAttempts()->count())->toBe(0);
});
