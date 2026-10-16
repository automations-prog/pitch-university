<?php

use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('super admins can list campaigns with their team and agent counts', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $team = Team::factory()->create();
    User::factory()->count(2)->forTeam($team)->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.campaigns.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaigns/index')
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.teams_count', 1)
            ->where('campaigns.data.0.agents_count', 2));
});

test('super admins can create a campaign', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.campaigns.store'), ['name' => 'Medicare AEP', 'is_active' => '1'])
        ->assertRedirect(route('admin.campaigns.index'));

    $this->assertDatabaseHas('campaigns', ['name' => 'Medicare AEP', 'is_active' => true]);
});

test('campaign names must be unique', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Campaign::factory()->create(['name' => 'Medicare AEP']);

    $this->actingAs($superAdmin)
        ->post(route('admin.campaigns.store'), ['name' => 'Medicare AEP', 'is_active' => '1'])
        ->assertSessionHasErrors(['name' => 'The name has already been taken.']);
});

test('super admins can update a campaign', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $campaign = Campaign::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.campaigns.update', $campaign), ['name' => 'Renamed', 'is_active' => '0'])
        ->assertRedirect(route('admin.campaigns.index'));

    expect($campaign->fresh())->name->toBe('Renamed')->is_active->toBeFalse();
});

test('deleting a campaign deletes its teams but keeps their members', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $team = Team::factory()->create();
    $agent = User::factory()->forTeam($team)->create();

    $this->actingAs($superAdmin)
        ->delete(route('admin.campaigns.destroy', $team->campaign))
        ->assertRedirect(route('admin.campaigns.index'));

    $this->assertModelMissing($team);
    expect($agent->fresh())->not->toBeNull()->team_id->toBeNull();
});

test('campaigns can be searched and filtered by status, a page at a time', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Campaign::factory()->create(['name' => 'Medicare AEP', 'is_active' => true]);
    Campaign::factory()->create(['name' => 'Medicare OEP', 'is_active' => false]);
    Campaign::factory()->count(11)->create(['is_active' => true]);

    $this->actingAs($superAdmin)
        ->get(route('admin.campaigns.index', ['search' => 'medicare', 'status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.name', 'Medicare OEP'));

    $this->actingAs($superAdmin)
        ->get(route('admin.campaigns.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('campaigns.data', 10)
            ->where('campaigns.meta.total', 13)
            ->where('campaigns.meta.last_page', 2));
});
