<?php

use App\Models\CourseExam;
use App\Models\CourseExamAttempt;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agent who passed the track's only module and used both attempts on the
 * script section.
 *
 * @return array{0: User, 1: CourseExam}
 */
function agentOutOfScriptAttempts(): array
{
    $track = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($track, 'track')->create();
    $exam = CourseExam::factory()->for($track, 'track')->create();
    $agent = User::factory()->create();
    $agent->courseTracks()->attach($track);
    CourseQuizAttempt::factory()->for($agent)->for($module, 'module')->create();
    CourseExamAttempt::factory()->failed()->count(2)->for($agent)->for($exam, 'exam')->create(['section' => 'script']);

    return [$agent, $exam];
}

test('admins can grant a retake to a user who is out of attempts', function () {
    $admin = User::factory()->admin()->create();
    [$agent, $exam] = agentOutOfScriptAttempts();

    $this->actingAs($admin)
        ->from(route('admin.training-tracks.show', $agent))
        ->post(route('admin.training-tracks.exam-retakes.store', $agent), ['course_exam_id' => $exam->id, 'section' => 'script'])
        ->assertRedirect(route('admin.training-tracks.show', $agent));

    expect($exam->retakeGrants()->sole())
        ->user_id->toBe($agent->id)
        ->section->toBe('script')
        ->granted_by->toBe($admin->id);

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.show', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tracks.0.exam.id', $exam->id)
            ->where('tracks.0.exam.sections.1.attempts_allowed', 3)
            ->where('tracks.0.exam.sections.1.is_out_of_attempts', false));
});

test('a retake can not be granted while the user still has attempts', function () {
    $admin = User::factory()->admin()->create();
    [$agent, $exam] = agentOutOfScriptAttempts();

    $this->actingAs($admin)
        ->post(route('admin.training-tracks.exam-retakes.store', $agent), ['course_exam_id' => $exam->id, 'section' => 'product'])
        ->assertSessionHasErrors('section');

    expect($exam->retakeGrants()->count())->toBe(0);
});

test('a retake requires a valid exam section', function () {
    $admin = User::factory()->admin()->create();
    [$agent, $exam] = agentOutOfScriptAttempts();

    $this->actingAs($admin)
        ->post(route('admin.training-tracks.exam-retakes.store', $agent), ['course_exam_id' => $exam->id, 'section' => 'nope'])
        ->assertSessionHasErrors('section');
});

test('agents can not grant retakes', function () {
    [$agent, $exam] = agentOutOfScriptAttempts();

    $this->actingAs($agent)
        ->post(route('admin.training-tracks.exam-retakes.store', $agent), ['course_exam_id' => $exam->id, 'section' => 'script'])
        ->assertForbidden();

    expect($exam->retakeGrants()->count())->toBe(0);
});

test('the progress report shows exam scores per section', function () {
    $admin = User::factory()->admin()->create();
    [$agent, $exam] = agentOutOfScriptAttempts();
    CourseExamAttempt::factory()->for($agent)->for($exam, 'exam')->create(['section' => 'product', 'score_pct' => 95]);

    $this->actingAs($admin)
        ->get(route('admin.training.progress', ['track' => $exam->track->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('examSections.0.key', 'product')
            ->where('users.data.0.exam.is_certified', false)
            ->where('users.data.0.exam.sections.product.best_score', 95)
            ->where('users.data.0.exam.sections.product.passed', true)
            ->where('users.data.0.exam.sections.script.passed', false)
            ->where('users.data.0.exam.sections.script.attempts_count', 2));
});
