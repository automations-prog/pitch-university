<?php

use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('super admins can list teams with their campaign, admin and agent count', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create(['name' => 'Morgan Admin']);
    $team = Team::factory()->managedBy($admin)->create();
    User::factory()->forTeam($team)->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.teams.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/teams/index')
            ->has('teams.data', 1)
            ->where('teams.data.0.campaign.id', $team->campaign_id)
            ->where('teams.data.0.manager', 'Morgan Admin')
            ->where('teams.data.0.agents_count', 1));
});

test('super admins can create a team run by an admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $campaign = Campaign::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.teams.store'), [
            'campaign_id' => $campaign->id,
            'name' => 'Team Alpha',
            'manager_id' => $admin->id,
        ])
        ->assertRedirect(route('admin.teams.index'));

    $team = Team::where('name', 'Team Alpha')->sole();

    expect($team->campaign_id)->toBe($campaign->id)
        ->and($team->manager->id)->toBe($admin->id)
        ->and($admin->fresh()->team_id)->toBe($team->id);
});

test('a team\'s admin must have the admin role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $agent = User::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.teams.store'), [
            'campaign_id' => Campaign::factory()->create()->id,
            'name' => 'Team Alpha',
            'manager_id' => $agent->id,
        ])
        ->assertSessionHasErrors('manager_id');

    $this->assertDatabaseMissing('teams', ['name' => 'Team Alpha']);
});

test('team names are unique within a campaign', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $team = Team::factory()->create(['name' => 'Team Alpha']);

    $this->actingAs($superAdmin)
        ->post(route('admin.teams.store'), ['campaign_id' => $team->campaign_id, 'name' => 'Team Alpha'])
        ->assertSessionHasErrors('name');

    $this->actingAs($superAdmin)
        ->post(route('admin.teams.store'), ['campaign_id' => Campaign::factory()->create()->id, 'name' => 'Team Alpha'])
        ->assertRedirect(route('admin.teams.index'));
});

test('giving a team a new admin takes the old one off it', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $oldAdmin = User::factory()->admin()->create();
    $newAdmin = User::factory()->admin()->create();
    $team = Team::factory()->managedBy($oldAdmin)->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.teams.update', $team), [
            'campaign_id' => $team->campaign_id,
            'name' => $team->name,
            'manager_id' => $newAdmin->id,
        ])
        ->assertRedirect(route('admin.teams.index'));

    expect($team->manager()->sole()->id)->toBe($newAdmin->id)
        ->and($oldAdmin->fresh()->team_id)->toBeNull();
});

test('an admin who runs another team can not be given a second one', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create(['name' => 'Morgan Admin']);
    $runTeam = Team::factory()->managedBy($admin)->create(['name' => 'Alpha']);
    $otherTeam = Team::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.teams.update', $otherTeam), [
            'campaign_id' => $otherTeam->campaign_id,
            'name' => $otherTeam->name,
            'manager_id' => $admin->id,
        ])
        ->assertSessionHasErrors(['manager_id' => 'Morgan Admin already runs the Alpha team. An admin can only run one team.']);

    $this->actingAs($superAdmin)
        ->post(route('admin.teams.store'), [
            'campaign_id' => $otherTeam->campaign_id,
            'name' => 'Brand New Team',
            'manager_id' => $admin->id,
        ])
        ->assertSessionHasErrors('manager_id');

    expect($admin->fresh()->team_id)->toBe($runTeam->id)
        ->and($otherTeam->manager()->exists())->toBeFalse();
    $this->assertDatabaseMissing('teams', ['name' => 'Brand New Team']);
});

test('saving a team keeps its own admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->managedBy($admin)->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.teams.update', $team), [
            'campaign_id' => $team->campaign_id,
            'name' => 'Renamed',
            'manager_id' => $admin->id,
        ])
        ->assertRedirect(route('admin.teams.index'));

    expect($admin->fresh()->team_id)->toBe($team->id);
});

test('deleting a team keeps its members, without a team', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $team = Team::factory()->create();
    $agent = User::factory()->forTeam($team)->create();

    $this->actingAs($superAdmin)
        ->delete(route('admin.teams.destroy', $team))
        ->assertRedirect(route('admin.teams.index'));

    $this->assertModelMissing($team);
    expect($agent->fresh()->team_id)->toBeNull();
});

test('teams can be filtered by campaign and by admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $runTeam = Team::factory()->managedBy($admin)->create();
    $otherTeam = Team::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.teams.index', ['campaign' => $otherTeam->campaign_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teams.data', 1)
            ->where('teams.data.0.id', $otherTeam->id));

    $this->actingAs($superAdmin)
        ->get(route('admin.teams.index', ['manager' => $admin->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teams.data', 1)
            ->where('teams.data.0.id', $runTeam->id));

    $this->actingAs($superAdmin)
        ->get(route('admin.teams.index', ['manager' => 'none']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('teams.data', 1)
            ->where('teams.data.0.id', $otherTeam->id));
});
