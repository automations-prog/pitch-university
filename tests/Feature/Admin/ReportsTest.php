<?php

use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\License;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the reports page', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/reports/index')
        ->has('agents.data', 3)
        ->has('agents.links.0.url')
        ->has('agents.meta.current_page')
        ->missing('agents.meta.links')
        ->has('statuses')
        ->has('licenses')
        ->has('trainings'),
    );
});

test('agents can not view the reports page', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('admin.reports.index'));

    $response->assertForbidden();
});

test('guests are redirected to login for reports', function () {
    $response = $this->get(route('admin.reports.index'));

    $response->assertRedirect(route('login'));
});

test('the reports page can be searched by agent name or email', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Zzyzx Reportable Agent']);
    User::factory()->create(['name' => 'Someone Else']);

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['search' => 'Zzyzx']));

    $response->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
});

test('the reports page can be filtered by status', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['status' => 'active']);
    User::factory()->inactive()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['status' => 'inactive']));

    $response->assertInertia(fn (Assert $page) => $page->has('agents.data', 1));
});

test('the reports page can be filtered by license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $licensedAgent = User::factory()->create();
    $licensedAgent->licenses()->attach($license);
    User::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['license' => $license->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 1)
        ->where('agents.data.0.id', $licensedAgent->id),
    );
});

test('the reports page exposes each agent\'s assigned licenses', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create(['name' => 'Zzyzx Reportable Agent']);
    $license = License::factory()->create();
    $agent->licenses()->attach($license);

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['search' => 'Zzyzx']));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 1)
        ->has('agents.data.0.licenses', 1)
        ->where('agents.data.0.licenses.0.id', $license->id),
    );
});

test('the reports page shows each agent\'s live track progress', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $completedTrack = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($completedTrack, 'track')->create();
    CourseQuizAttempt::factory()->for($agent)->for($module, 'module')->create(['score_pct' => 90, 'created_at' => '2026-09-20 10:00:00']);
    $agent->courseTracks()->attach([$completedTrack->id, CourseTrack::factory()->create()->id]);

    $response = $this->actingAs($admin)->get(route('admin.reports.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('agents.data.0.id', $agent->id)
        ->where('agents.data.0.trainings_completed', 1)
        ->where('agents.data.0.total_trainings', 2)
        ->where('agents.data.0.average_score', 90)
        ->where('agents.data.0.last_activity', '2026-09-20'),
    );
});

test('the reports page narrows to agents assigned to a single track when filtered', function () {
    $admin = User::factory()->admin()->create();
    $track = CourseTrack::factory()->create();
    $otherTrack = CourseTrack::factory()->create();
    $assignedAgent = User::factory()->create();
    $assignedAgent->courseTracks()->attach([$track->id, $otherTrack->id]);
    User::factory()->create()->courseTracks()->attach($otherTrack);

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['track' => $track->id]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 1)
        ->where('agents.data.0.id', $assignedAgent->id)
        ->where('agents.data.0.total_trainings', 1)
        ->where('filters.track', (string) $track->id),
    );
});

test('the reports page can be paginated with a selectable page size', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('agents.data', 10)
        ->where('agents.meta.per_page', 10)
        ->where('agents.meta.total', 24)
        ->where('filters.per_page', '10')
        ->where('perPageOptions', [10, 25, 50, 100]),
    );
});
