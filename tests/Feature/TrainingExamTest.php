<?php

use App\Models\CourseExam;
use App\Models\CourseExamAttempt;
use App\Models\CourseExamRetakeGrant;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agent assigned to a one-module track with a final exam (two sections, each
 * drawing 5 of 8 questions whose correct answer is always the first choice).
 *
 * @return array{0: User, 1: CourseTrack, 2: CourseExam, 3: CourseModule}
 */
function examSetup(bool $modulesPassed = true): array
{
    $track = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($track, 'track')->create();
    $exam = CourseExam::factory()->for($track, 'track')->create();
    $agent = User::factory()->create();
    $agent->courseTracks()->attach($track);

    if ($modulesPassed) {
        CourseQuizAttempt::factory()->for($agent)->for($module, 'module')->create();
    }

    return [$agent, $track, $exam, $module];
}

/**
 * Answers for the open attempt with the given number of correct answers.
 *
 * @return array<string, int>
 */
function examAnswers(CourseExamAttempt $attempt, int $correctCount): array
{
    return collect($attempt->question_ids)
        ->values()
        ->mapWithKeys(fn (string $questionId, int $index) => [$questionId => $index < $correctCount ? 0 : 1])
        ->all();
}

function startSection(User $agent, CourseTrack $track, string $section): CourseExamAttempt
{
    test()->actingAs($agent)
        ->post(route('training.exam.start', [$track, $section]))
        ->assertRedirect(route('training.exam.take', [$track, $section]));

    return $agent->courseExamAttempts()->where('section', $section)->whereNull('submitted_at')->sole();
}

test('the exam stays locked until every module is passed', function () {
    [$agent, $track] = examSetup(modulesPassed: false);

    $this->actingAs($agent)
        ->get(route('training.tracks.show', $track))
        ->assertInertia(fn (Assert $page) => $page
            ->where('exam.status', 'locked')
            ->where('exam.is_unlocked', false));

    $this->actingAs($agent)->get(route('training.exam.show', $track))->assertForbidden();
    $this->actingAs($agent)->post(route('training.exam.start', [$track, 'product']))->assertForbidden();
});

test('agents can not open the exam of a track that is not assigned to them', function () {
    [, $track] = examSetup();

    $this->actingAs(User::factory()->create())
        ->get(route('training.exam.show', $track))
        ->assertForbidden();
});

test('a track without an exam has no exam page', function () {
    $track = CourseTrack::factory()->create();
    $agent = User::factory()->create();
    $agent->courseTracks()->attach($track);

    $this->actingAs($agent)
        ->get(route('training.tracks.show', $track))
        ->assertInertia(fn (Assert $page) => $page->where('exam', null));

    $this->actingAs($agent)->get(route('training.exam.show', $track))->assertNotFound();
});

test('starting a section draws the configured number of questions and keeps them on refresh', function () {
    [$agent, $track, $exam] = examSetup();

    $attempt = startSection($agent, $track, 'product');

    expect($attempt->question_ids)->toHaveCount(5)
        ->and($exam->questionsById('product')->keys()->all())->toContain(...$attempt->question_ids);

    // Starting again resumes the same attempt instead of drawing new questions.
    startSection($agent, $track, 'product');
    expect($agent->courseExamAttempts()->count())->toBe(1);

    $this->actingAs($agent)
        ->get(route('training.exam.take', [$track, 'product']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training/exam-section')
            ->has('questions', 5)
            ->where('questions.0.id', $attempt->question_ids[0])
            ->missing('questions.0.answer_index')
            ->missing('questions.0.explanation'));
});

test('a section is graded against its pass percentage with a topic breakdown', function (int $correctCount, int $expectedScore, bool $expectedPassed) {
    [$agent, $track] = examSetup();
    $attempt = startSection($agent, $track, 'product');

    $this->actingAs($agent)
        ->post(route('training.exam.submit', [$track, 'product']), ['answers' => examAnswers($attempt, $correctCount)])
        ->assertRedirect(route('training.exam.show', $track));

    $attempt->refresh();
    expect($attempt->isSubmitted())->toBeTrue()
        ->and($attempt->score_pct)->toBe($expectedScore)
        ->and($attempt->passed)->toBe($expectedPassed)
        ->and($attempt->tag_breakdown)->toBe(['product' => ['correct' => $correctCount, 'total' => 5]]);
})->with([
    'all correct' => [5, 100, true],
    'exactly the pass mark' => [4, 80, true],
    'below the pass mark' => [3, 60, false],
]);

test('every exam question must be answered', function () {
    [$agent, $track] = examSetup();
    $attempt = startSection($agent, $track, 'product');

    $answers = examAnswers($attempt, 5);
    array_pop($answers);

    $this->actingAs($agent)
        ->post(route('training.exam.submit', [$track, 'product']), ['answers' => $answers])
        ->assertSessionHasErrors('answers.'.last($attempt->question_ids));

    expect($attempt->fresh()->isSubmitted())->toBeFalse();
});

test('the exam page shows scores but never the correct answers', function () {
    [$agent, $track] = examSetup();
    $attempt = startSection($agent, $track, 'product');
    $this->actingAs($agent)->post(route('training.exam.submit', [$track, 'product']), ['answers' => examAnswers($attempt, 3)]);

    $this->actingAs($agent)
        ->get(route('training.exam.show', $track))
        ->assertInertia(fn (Assert $page) => $page
            ->component('training/exam')
            ->where('exam.sections.0.key', 'product')
            ->where('exam.sections.0.attempts.0.score_pct', 60)
            ->where('exam.sections.0.attempts_used', 1)
            ->where('exam.sections.0.can_start', true)
            ->missing('exam.sections.0.attempts.0.answers')
            ->missing('exam.sections.0.pool'));
});

test('each section allows two attempts and locks after the second fail', function () {
    [$agent, $track] = examSetup();

    foreach (range(1, 2) as $attemptNumber) {
        $attempt = startSection($agent, $track, 'script');
        $this->actingAs($agent)->post(route('training.exam.submit', [$track, 'script']), ['answers' => examAnswers($attempt, 0)]);
    }

    $this->actingAs($agent)->post(route('training.exam.start', [$track, 'script']))->assertForbidden();

    $this->actingAs($agent)
        ->get(route('training.exam.show', $track))
        ->assertInertia(fn (Assert $page) => $page
            ->where('exam.status', 'failed')
            ->where('exam.sections.1.is_out_of_attempts', true)
            ->where('exam.sections.1.can_start', false)
            // The other section is retaken separately and is still available.
            ->where('exam.sections.0.can_start', true));
});

test('a granted retake allows one more attempt', function () {
    [$agent, $track, $exam] = examSetup();
    CourseExamAttempt::factory()->failed()->count(2)->for($agent)->for($exam, 'exam')->create(['section' => 'script']);
    CourseExamRetakeGrant::factory()->for($agent)->for($exam, 'exam')->create(['section' => 'script']);

    startSection($agent, $track, 'script');
});

test('a passed section can not be retaken', function () {
    [$agent, $track, $exam] = examSetup();
    CourseExamAttempt::factory()->for($agent)->for($exam, 'exam')->create(['section' => 'product']);

    $this->actingAs($agent)->post(route('training.exam.start', [$track, 'product']))->assertForbidden();
});

test('passing every section certifies the user', function () {
    [$agent, $track, $exam] = examSetup();
    CourseExamAttempt::factory()->for($agent)->for($exam, 'exam')->create(['section' => 'product']);
    CourseExamAttempt::factory()->failed()->for($agent)->for($exam, 'exam')->create(['section' => 'script']);
    CourseExamAttempt::factory()->for($agent)->for($exam, 'exam')->create(['section' => 'script', 'score_pct' => 85]);

    $this->actingAs($agent)
        ->get(route('training.exam.show', $track))
        ->assertInertia(fn (Assert $page) => $page
            ->where('exam.status', 'passed')
            ->where('exam.is_passed', true));

    $this->actingAs($agent)
        ->get(route('training.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tracks.0.progress.has_exam', true)
            ->where('tracks.0.progress.is_certified', true));
});

test('an unknown section returns not found', function () {
    [$agent, $track] = examSetup();

    $this->actingAs($agent)->post(route('training.exam.start', [$track, 'nope']))->assertNotFound();
});
