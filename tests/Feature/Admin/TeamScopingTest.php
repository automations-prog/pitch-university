<?php

use App\Models\CourseExam;
use App\Models\CourseExamAttempt;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\RoleplaySession;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Two teams with one agent each; the given admin runs only the first.
 *
 * @return array{0: User, 1: User}
 */
function agentsOnOwnAndOtherTeam(User $admin): array
{
    $ownTeam = Team::factory()->managedBy($admin)->create();

    return [
        User::factory()->forTeam($ownTeam)->create(['name' => 'Own Agent']),
        User::factory()->forTeam(Team::factory()->create())->create(['name' => 'Other Agent']),
    ];
}

test('admins only see themselves and their team\'s members on the users page', function () {
    $admin = User::factory()->admin()->create();
    [$ownAgent] = agentsOnOwnAndOtherTeam($admin);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 2)
            ->where('users.data', fn ($users) => collect($users)->pluck('id')->sort()->values()->all() === collect([$admin->id, $ownAgent->id])->sort()->values()->all()));
});

test('admins without a team only see themselves', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->forTeam(Team::factory()->create())->create();
    User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $admin->id));
});

test('super admins see every user', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    agentsOnOwnAndOtherTeam(User::factory()->admin()->create());

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 4));
});

test('admins only see their team\'s agents on reports, training tracks and the dashboard', function () {
    $admin = User::factory()->admin()->create();
    [$ownAgent] = agentsOnOwnAndOtherTeam($admin);

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('agents.data', 1)
            ->where('agents.data.0.id', $ownAgent->id));

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $ownAgent->id));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', true)
            ->where('stats.total_agents', 1)
            ->where('screenings', null));
});

test('admins can not view, edit, impersonate or delete users outside their team', function () {
    $admin = User::factory()->admin()->create();
    [, $otherAgent] = agentsOnOwnAndOtherTeam($admin);

    $this->actingAs($admin)->get(route('admin.users.show', $otherAgent))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.users.edit', $otherAgent))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.training-tracks.show', $otherAgent))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.users.impersonate', $otherAgent))->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.users.destroy', $otherAgent))->assertForbidden();

    expect($otherAgent->fresh())->not->toBeNull();
});

test('admins can not bulk delete users outside their team', function () {
    $admin = User::factory()->admin()->create();
    [$ownAgent, $otherAgent] = agentsOnOwnAndOtherTeam($admin);

    $this->actingAs($admin)
        ->delete(route('admin.users.bulk-destroy'), ['ids' => [$ownAgent->id, $otherAgent->id]])
        ->assertForbidden();

    expect(User::whereKey([$ownAgent->id, $otherAgent->id])->count())->toBe(2);
});

test('admins can edit and impersonate agents on their team', function () {
    $admin = User::factory()->admin()->create();
    [$ownAgent] = agentsOnOwnAndOtherTeam($admin);

    $this->actingAs($admin)->get(route('admin.users.edit', $ownAgent))->assertOk();
    $this->actingAs($admin)->post(route('admin.users.impersonate', $ownAgent))->assertRedirect();
});

test('admins can only give the agent role', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->managedBy($admin)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Promoted',
            'email' => 'promoted@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'status' => 'active',
            'work_location' => 'onshore',
            'team_id' => $team->id,
        ])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'promoted@example.com']);
});

test('admins can only put users on their own team', function () {
    $admin = User::factory()->admin()->create();
    Team::factory()->managedBy($admin)->create();
    $otherTeam = Team::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Misplaced',
            'email' => 'misplaced@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'agent',
            'status' => 'active',
            'work_location' => 'onshore',
            'team_id' => $otherTeam->id,
        ])
        ->assertSessionHasErrors('team_id');

    $this->assertDatabaseMissing('users', ['email' => 'misplaced@example.com']);
});

test('admins must put new users on a team', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Teamless',
            'email' => 'teamless@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'agent',
            'status' => 'active',
            'work_location' => 'onshore',
        ])
        ->assertSessionHasErrors(['team_id' => 'The team id field is required.']);
});

test('admins can create agents on their team', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->managedBy($admin)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Team Agent',
            'email' => 'team-agent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'agent',
            'status' => 'active',
            'work_location' => 'offshore',
            'team_id' => $team->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $agent = User::where('email', 'team-agent@example.com')->sole();

    expect($agent)
        ->team_id->toBe($team->id)
        ->work_location->value->toBe('offshore')
        ->and($agent->hasRole('agent'))->toBeTrue();
});

test('a team can only have one admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $team = Team::factory()->managedBy(User::factory()->admin()->create())->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'status' => 'active',
            'work_location' => 'onshore',
            'team_id' => $team->id,
        ])
        ->assertSessionHasErrors(['team_id' => 'This team already has an admin.']);

    $this->assertDatabaseMissing('users', ['email' => 'second-admin@example.com']);
});

test('super admins can make an admin who runs a team from the user form', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $team = Team::factory()->create();
    $agent = User::factory()->forTeam($team)->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Team Admin',
            'email' => 'team-admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'status' => 'active',
            'work_location' => 'onshore',
            'team_id' => $team->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $admin = User::where('email', 'team-admin@example.com')->sole();

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $agent->id));
});

test('super admins can leave a user without a team', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Unassigned Admin',
            'email' => 'unassigned@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
            'status' => 'active',
            'work_location' => 'onshore',
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'unassigned@example.com')->sole())
        ->team_id->toBeNull()
        ->hasRole('admin')->toBeTrue();
});

test('changing a user\'s role changes their permissions', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $agent = User::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $agent), [
            'name' => $agent->name,
            'email' => $agent->email,
            'role' => 'admin',
            'status' => 'active',
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($agent->fresh())
        ->hasRole('admin')->toBeTrue()
        ->hasRole('agent')->toBeFalse()
        ->can('reports.view')->toBeTrue();
});

test('admins can review roleplay attempts of their team\'s agents only', function () {
    $admin = User::factory()->admin()->create();
    [$ownAgent, $otherAgent] = agentsOnOwnAndOtherTeam($admin);
    $ownSession = RoleplaySession::factory()->ended()->for($ownAgent)->create();
    $otherSession = RoleplaySession::factory()->ended()->for($otherAgent)->create();

    $this->actingAs($admin)
        ->get(route('medicare-roleplay.sessions.show', $ownSession))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('medicare-roleplay.sessions.show', $otherSession))
        ->assertNotFound();
});

test('admins can grant exam retakes to their team\'s agents only', function () {
    $admin = User::factory()->admin()->create();
    [$ownAgent, $otherAgent] = agentsOnOwnAndOtherTeam($admin);

    $track = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($track, 'track')->create();
    $exam = CourseExam::factory()->for($track, 'track')->create();

    foreach ([$ownAgent, $otherAgent] as $agent) {
        $agent->courseTracks()->attach($track);
        CourseQuizAttempt::factory()->for($agent)->for($module, 'module')->create();
        CourseExamAttempt::factory()->failed()->count(2)->for($agent)->for($exam, 'exam')->create(['section' => 'script']);
    }

    $this->actingAs($admin)
        ->post(route('admin.training-tracks.exam-retakes.store', $otherAgent), ['course_exam_id' => $exam->id, 'section' => 'script'])
        ->assertForbidden();

    $this->actingAs($admin)
        ->from(route('admin.training-tracks.show', $ownAgent))
        ->post(route('admin.training-tracks.exam-retakes.store', $ownAgent), ['course_exam_id' => $exam->id, 'section' => 'script'])
        ->assertRedirect(route('admin.training-tracks.show', $ownAgent));

    expect($exam->retakeGrants()->sole())
        ->user_id->toBe($ownAgent->id)
        ->granted_by->toBe($admin->id);
});
