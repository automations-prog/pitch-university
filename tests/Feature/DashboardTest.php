<?php

use App\Enums\CallRating;
use App\Models\CourseExam;
use App\Models\CourseExamAttempt;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\License;
use App\Models\ScreeningResponse;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('agents see their own progress instead of admin stats', function () {
    $agent = User::factory()->create();
    $agent->courseTracks()->attach(CourseTrack::factory()->count(2)->create());
    CourseTrack::factory()->create();

    $response = $this->actingAs($agent)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('isAdmin', false)
        ->where('progress.trainings_completed', 0)
        ->where('progress.total_trainings', 2)
        ->has('trainingScores', 2)
        ->missing('stats')
        ->missing('agents')
        ->missing('charts'),
    );
});

test('an agent\'s training scores show their status and average quiz score in each assigned track', function () {
    $agent = User::factory()->create();

    $inProgressTrack = CourseTrack::factory()->create(['name' => 'Cold Calling', 'position' => 0]);
    $passedModule = CourseModule::factory()->for($inProgressTrack, 'track')->create(['position' => 0]);
    CourseModule::factory()->for($inProgressTrack, 'track')->create(['position' => 1]);
    CourseQuizAttempt::factory()->for($agent)->for($passedModule, 'module')->create(['score_pct' => 85]);

    $examNextTrack = CourseTrack::factory()->create(['position' => 1]);
    $examNextModule = CourseModule::factory()->for($examNextTrack, 'track')->create();
    CourseQuizAttempt::factory()->for($agent)->for($examNextModule, 'module')->create();
    CourseExam::factory()->for($examNextTrack, 'track')->create();

    $lessonOnlyTrack = CourseTrack::factory()->create(['position' => 2]);
    $lessonOnlyModule = CourseModule::factory()->for($lessonOnlyTrack, 'track')->create();
    $agent->completedCourseLessons()->attach(CourseLesson::factory()->for($lessonOnlyModule, 'module')->create());

    $notStartedTrack = CourseTrack::factory()->create(['position' => 3]);
    CourseModule::factory()->for($notStartedTrack, 'track')->create();

    CourseTrack::factory()->create(['position' => 4]);

    $agent->courseTracks()->attach([$inProgressTrack->id, $examNextTrack->id, $lessonOnlyTrack->id, $notStartedTrack->id]);

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('trainingScores', 4)
        ->where('trainingScores.0.name', 'Cold Calling')
        ->where('trainingScores.0.status', 'in_progress')
        ->where('trainingScores.0.average_score', 85)
        ->where('trainingScores.1.status', 'exam_next')
        ->where('trainingScores.2.status', 'in_progress')
        ->where('trainingScores.2.average_score', null)
        ->where('trainingScores.3.status', 'not_started'),
    );
});

test('admins see progress stats and the agent table', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    User::factory()->inactive()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('isAdmin', true)
        ->has('stats.total_agents')
        ->has('stats.active_agents')
        ->has('stats.inactive_agents')
        ->has('stats.total_trainings')
        ->has('agents.data', 3)
        ->has('agents.links')
        ->has('agents.meta.current_page')
        ->missing('agents.meta.links')
        ->has('licenses')
        ->has('trainings')
        ->has('charts.status_split', 2)
        ->has('charts.completion', 3)
        ->has('charts.score_bands', 4)
        ->has('charts.per_license'),
    );
});

test('the admin dashboard agent table can be filtered by status', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    User::factory()->inactive()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['status' => 'inactive']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 3)
        ->where('agents.data.0.status', 'inactive'),
    );
});

test('the admin dashboard agent table can be filtered by license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $licensedAgent = User::factory()->create();
    $licensedAgent->licenses()->attach($license);
    User::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['license' => $license->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 1)
        ->where('agents.data.0.id', $licensedAgent->id),
    );
});

test('the admin dashboard agent table shows each agent\'s live track progress', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $completedTrack = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($completedTrack, 'track')->create();
    CourseQuizAttempt::factory()->for($agent)->for($module, 'module')->create(['score_pct' => 90, 'created_at' => '2026-09-20 10:00:00']);
    $agent->courseTracks()->attach([$completedTrack->id, CourseTrack::factory()->create()->id]);

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('agents.data.0.id', $agent->id)
        ->where('agents.data.0.trainings_completed', 1)
        ->where('agents.data.0.total_trainings', 2)
        ->where('agents.data.0.average_score', 90)
        ->where('agents.data.0.last_activity', '2026-09-20'),
    );
});

test('the admin dashboard narrows to agents assigned to a single track when filtered', function () {
    $admin = User::factory()->admin()->create();
    $track = CourseTrack::factory()->create();
    $otherTrack = CourseTrack::factory()->create();
    $assignedAgent = User::factory()->create();
    $assignedAgent->courseTracks()->attach([$track->id, $otherTrack->id]);
    User::factory()->create()->courseTracks()->attach($otherTrack);

    $response = $this->actingAs($admin)->get(route('dashboard', ['track' => $track->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 1)
        ->where('agents.data.0.id', $assignedAgent->id)
        ->where('agents.data.0.total_trainings', 1)
        ->has('trackOverview', 1)
        ->where('trackOverview.0.slug', $track->slug)
        ->where('filters.track', (string) $track->id),
    );
});

test('the admin dashboard agent table can be paginated with a selectable page size', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 10)
        ->where('agents.meta.total', 24)
        ->where('agents.meta.last_page', 3)
        ->where('agents.meta.per_page', 10)
        ->where('filters.per_page', '10'),
    );

    $secondPage = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 10, 'page' => 2]));
    $secondPage->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 10)
        ->where('agents.meta.current_page', 2),
    );
});

test('the dashboard charts summarize every filtered agent, not just the current page', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 10)
        ->where('charts.status_split.0.value', 24),
    );
});

test('an invalid per_page value on the dashboard falls back to the default', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['per_page' => 999]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('agents.meta.per_page', 10)
        ->where('filters.per_page', '10'),
    );
});

test('inactive licenses are excluded from the dashboard filter options, which list every training track', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->inactive()->create();
    $track = CourseTrack::factory()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('licenses', 0)
        ->has('trainings', 1)
        ->where('trainings.0.id', $track->id),
    );
});

test('the per-license chart counts agents assigned to each active license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create(['name' => 'Sales Fundamentals']);
    License::factory()->inactive()->create();
    $agent = User::factory()->create();
    $agent->licenses()->attach($license);
    User::factory()->create();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('charts.per_license', 1)
        ->where('charts.per_license.0.name', 'Sales Fundamentals')
        ->where('charts.per_license.0.value', 1),
    );
});

test('the dashboard charts reflect the filtered agent set', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    User::factory()->inactive()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('dashboard', ['status' => 'inactive']));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('charts.status_split.0.value', 0)
        ->where('charts.status_split.1.value', 3),
    );
});

test('the agent dashboard stats count completed tracks and certifications', function () {
    $agent = User::factory()->create();

    $completedTrack = CourseTrack::factory()->create(['position' => 0]);
    $completedModule = CourseModule::factory()->for($completedTrack, 'track')->create();
    CourseQuizAttempt::factory()->for($agent)->for($completedModule, 'module')->create();

    $certifiedTrack = CourseTrack::factory()->create(['position' => 1]);
    $certifiedModule = CourseModule::factory()->for($certifiedTrack, 'track')->create();
    CourseQuizAttempt::factory()->for($agent)->for($certifiedModule, 'module')->create();
    $exam = CourseExam::factory()->for($certifiedTrack, 'track')->create();
    CourseExamAttempt::factory()->for($agent)->for($exam, 'exam')->create(['section' => 'product']);
    CourseExamAttempt::factory()->for($agent)->for($exam, 'exam')->create(['section' => 'script']);

    $notStartedTrack = CourseTrack::factory()->create(['position' => 2]);
    CourseModule::factory()->for($notStartedTrack, 'track')->create();

    CourseTrack::factory()->create();

    $agent->courseTracks()->attach([$completedTrack->id, $certifiedTrack->id, $notStartedTrack->id]);

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('progress.total_trainings', 3)
        ->where('progress.trainings_completed', 2)
        ->where('progress.certifications', 1),
    );
});

test('a track with a final exam does not count as completed on the agent dashboard until the agent is certified', function () {
    $agent = User::factory()->create();
    $track = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($track, 'track')->create();
    CourseQuizAttempt::factory()->for($agent)->for($module, 'module')->create();
    CourseExam::factory()->for($track, 'track')->create();
    $agent->courseTracks()->attach($track);

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('progress.trainings_completed', 0)
        ->where('progress.certifications', 0),
    );
});

test('the admin dashboard summarizes how assigned agents are progressing in each track', function () {
    $admin = User::factory()->admin()->create();

    $track = CourseTrack::factory()->create(['position' => 0]);
    $module = CourseModule::factory()->for($track, 'track')->create();
    $lesson = CourseLesson::factory()->for($module, 'module')->create();
    $exam = CourseExam::factory()->for($track, 'track')->create();

    $notStarted = User::factory()->create();
    $startedLessons = User::factory()->create();
    $startedLessons->completedCourseLessons()->attach($lesson);
    $awaitingExam = User::factory()->create();
    CourseQuizAttempt::factory()->for($awaitingExam)->for($module, 'module')->create();
    CourseExamAttempt::factory()->for($awaitingExam)->for($exam, 'exam')->create(['section' => 'product']);
    $certified = User::factory()->create();
    CourseQuizAttempt::factory()->for($certified)->for($module, 'module')->create();
    CourseExamAttempt::factory()->for($certified)->for($exam, 'exam')->create(['section' => 'product']);
    CourseExamAttempt::factory()->for($certified)->for($exam, 'exam')->create(['section' => 'script']);
    $unassigned = User::factory()->create();
    CourseQuizAttempt::factory()->for($unassigned)->for($module, 'module')->create();

    $track->users()->attach([$notStarted->id, $startedLessons->id, $awaitingExam->id, $certified->id, $admin->id]);

    $trackWithoutExam = CourseTrack::factory()->create(['position' => 1]);
    $firstModule = CourseModule::factory()->for($trackWithoutExam, 'track')->create();
    $secondModule = CourseModule::factory()->for($trackWithoutExam, 'track')->create();
    CourseQuizAttempt::factory()->for($notStarted)->for($firstModule, 'module')->create();
    CourseQuizAttempt::factory()->for($notStarted)->for($secondModule, 'module')->create();
    CourseQuizAttempt::factory()->for($certified)->for($firstModule, 'module')->create();
    CourseQuizAttempt::factory()->failed()->for($certified)->for($secondModule, 'module')->create();
    $trackWithoutExam->users()->attach([$notStarted->id, $certified->id]);

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('trackOverview', 2)
        ->where('trackOverview.0.slug', $track->slug)
        ->where('trackOverview.0.has_exam', true)
        ->where('trackOverview.0.assigned', 4)
        ->where('trackOverview.0.not_started', 1)
        ->where('trackOverview.0.in_progress', 2)
        ->where('trackOverview.0.completed', 1)
        ->where('trackOverview.1.has_exam', false)
        ->where('trackOverview.1.assigned', 2)
        ->where('trackOverview.1.not_started', 0)
        ->where('trackOverview.1.in_progress', 1)
        ->where('trackOverview.1.completed', 1),
    );
});

test('the agent dashboard averages the agent\'s best quiz score per attempted module across tracks', function () {
    $agent = User::factory()->create();

    $track = CourseTrack::factory()->create(['position' => 0]);
    $retakenModule = CourseModule::factory()->for($track, 'track')->create(['position' => 0]);
    $passedModule = CourseModule::factory()->for($track, 'track')->create(['position' => 1]);
    CourseModule::factory()->for($track, 'track')->create(['position' => 2]);
    CourseQuizAttempt::factory()->failed()->for($agent)->for($retakenModule, 'module')->create(['score_pct' => 40]);
    CourseQuizAttempt::factory()->for($agent)->for($retakenModule, 'module')->create(['score_pct' => 90]);
    CourseQuizAttempt::factory()->for($agent)->for($passedModule, 'module')->create(['score_pct' => 70]);

    $otherTrack = CourseTrack::factory()->create(['position' => 1]);
    $otherModule = CourseModule::factory()->for($otherTrack, 'track')->create();
    CourseQuizAttempt::factory()->failed()->for($agent)->for($otherModule, 'module')->create(['score_pct' => 50]);

    $notStartedTrack = CourseTrack::factory()->create(['position' => 2]);
    CourseModule::factory()->for($notStartedTrack, 'track')->create();

    $agent->courseTracks()->attach([$track->id, $otherTrack->id, $notStartedTrack->id]);

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('progress.average_score', 70),
    );
});

test('the admin track overview averages assigned agents\' best quiz scores', function () {
    $admin = User::factory()->admin()->create();
    $track = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($track, 'track')->create();
    CourseTrack::factory()->create(['position' => 1]);

    $retook = User::factory()->create();
    CourseQuizAttempt::factory()->failed()->for($retook)->for($module, 'module')->create(['score_pct' => 30]);
    CourseQuizAttempt::factory()->failed()->for($retook)->for($module, 'module')->create(['score_pct' => 60]);
    $passed = User::factory()->create();
    CourseQuizAttempt::factory()->for($passed)->for($module, 'module')->create(['score_pct' => 90]);
    $unassigned = User::factory()->create();
    CourseQuizAttempt::factory()->failed()->for($unassigned)->for($module, 'module')->create(['score_pct' => 10]);

    $track->users()->attach([$retook->id, $passed->id]);

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('trackOverview.0.average_score', 75)
        ->where('trackOverview.1.average_score', null),
    );
});

test('the admin dashboard summarizes the screening funnel and lists the latest responses', function () {
    $admin = User::factory()->admin()->create();

    ScreeningResponse::factory()->count(2)->create();
    $calledOnly = ScreeningResponse::factory()->create();
    $calledOnly->callLog->update(['called_at' => now()]);
    $recommended = ScreeningResponse::factory()->create();
    $recommended->callLog->update(['called_at' => now(), 'overall_gut_check' => CallRating::Yes]);
    $rejected = ScreeningResponse::factory()->create(['full_name' => 'Latest Candidate']);
    $rejected->callLog->update(['called_at' => now(), 'overall_gut_check' => CallRating::No]);

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('screenings.stats.responses', 5)
        ->where('screenings.stats.awaiting_call', 2)
        ->where('screenings.stats.called', 3)
        ->where('screenings.stats.awaiting_review', 1)
        ->where('screenings.stats.reviewed', 2)
        ->where('screenings.gut_check', [
            ['name' => 'Yes', 'value' => 1],
            ['name' => 'Somewhat', 'value' => 0],
            ['name' => 'No', 'value' => 1],
        ])
        ->has('screenings.recent', 3)
        ->where('screenings.recent.0.full_name', 'Latest Candidate')
        ->where('screenings.recent.0.overall_gut_check', 'no'),
    );
});

test('agents do not see screening data on their dashboard', function () {
    ScreeningResponse::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->missing('screenings'));
});

test('admins share the count of screening calls awaiting review for the sidebar badge', function () {
    $admin = User::factory()->admin()->create();

    ScreeningResponse::factory()->create();
    ScreeningResponse::factory()->count(2)->create()
        ->each(fn (ScreeningResponse $response) => $response->callLog->update(['called_at' => now()]));
    ScreeningResponse::factory()->create()->callLog->update(['called_at' => now(), 'overall_gut_check' => CallRating::Yes]);

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('screeningAwaitingReview', 2),
    );
});

test('agents do not receive the screening review count', function () {
    $agent = User::factory()->create();

    ScreeningResponse::factory()->create()->callLog->update(['called_at' => now()]);

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('screeningAwaitingReview', null),
    );
});

test('the admin dashboard lists to-dos derived from screenings and unstarted training', function () {
    $admin = User::factory()->admin()->create();

    $awaitingReview = ScreeningResponse::factory()->create(['full_name' => 'Wesley Vasquez']);
    $awaitingReview->callLog->update(['called_at' => now()]);
    $awaitingCall = ScreeningResponse::factory()->create(['full_name' => 'Mufutau Klein']);
    ScreeningResponse::factory()->create()->callLog->update(['called_at' => now(), 'overall_gut_check' => CallRating::Yes]);

    $track = CourseTrack::factory()->create(['name' => 'Medicare Fronting']);
    $track->users()->attach(User::factory()->count(2)->create());

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('todos.total', 3)
        ->where('todos.items', [
            [
                'key' => "review-{$awaitingReview->id}",
                'type' => 'review',
                'title' => 'Review Wesley Vasquez',
                'description' => 'AI screening call is done',
                'screening_response_id' => $awaitingReview->id,
            ],
            [
                'key' => "remind-{$awaitingCall->id}",
                'type' => 'remind',
                'title' => 'Remind Mufutau Klein',
                'description' => 'Still needs to take the AI call',
                'screening_response_id' => $awaitingCall->id,
            ],
            [
                'key' => "start-{$track->slug}",
                'type' => 'start',
                'title' => 'Get 2 agents started',
                'description' => 'Medicare Fronting not opened yet',
                'screening_response_id' => null,
            ],
        ])
        ->where('filters.tab', 'overview'),
    );
});

test('the overview tracks ignore the agent table filters', function () {
    $admin = User::factory()->admin()->create();
    $track = CourseTrack::factory()->create();
    $track->users()->attach(User::factory()->count(2)->create());
    $track->users()->attach(User::factory()->inactive()->create());

    $this->actingAs($admin)->get(route('dashboard', ['status' => 'active', 'tab' => 'training']))->assertInertia(fn (Assert $page) => $page
        ->where('trackOverview.0.assigned', 2)
        ->where('overviewTracks.0.assigned', 3)
        ->where('filters.tab', 'training'),
    );
});

test('agents do not receive the admin to-dos', function () {
    $agent = User::factory()->create();
    ScreeningResponse::factory()->create();

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->missing('todos')
        ->missing('overviewTracks'),
    );
});
