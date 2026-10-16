<?php

use App\Models\CourseTrack;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the training tracks page with each agent\'s toggles', function () {
    $admin = User::factory()->superAdmin()->create(['name' => 'Admin']);
    $agent = User::factory()->create(['name' => 'Agent']);
    $track = CourseTrack::factory()->create();
    CourseTrack::factory()->create();
    $agent->courseTracks()->attach($track);

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/training-tracks/index')
            ->has('tracks', 2)
            ->where('tracks.0.users_count', 1)
            ->has('users.data', 1)
            ->where('users.data.0.id', $agent->id)
            ->where('users.data.0.track_ids', [$track->id]));
});

test('the training tracks page only lists agents', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $track = CourseTrack::factory()->create();
    $admin->courseTracks()->attach($track);
    $agent->courseTracks()->attach($track);

    $this->actingAs($superAdmin)
        ->get(route('admin.training-tracks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $agent->id)
            ->where('tracks.0.users_count', 1));
});

test('the training tracks page can be searched', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create(['name' => 'Jane Agent']);
    User::factory()->create(['name' => 'John Agent']);

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index', ['search' => 'jane']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Jane Agent'));
});

test('admins can toggle a track on and off, and turning it off keeps progress', function () {
    $admin = User::factory()->superAdmin()->create();
    $agent = User::factory()->create();
    $track = CourseTrack::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.training-tracks.index'))
        ->post(route('admin.users.tracks.store', [$agent, $track->id]))
        ->assertRedirect(route('admin.training-tracks.index'));

    expect($agent->courseTracks()->pluck('course_tracks.id')->all())->toBe([$track->id]);

    $this->actingAs($admin)
        ->from(route('admin.training-tracks.index'))
        ->delete(route('admin.users.tracks.destroy', [$agent, $track->id]))
        ->assertRedirect(route('admin.training-tracks.index'));

    expect($agent->courseTracks()->count())->toBe(0);
});

test('agents can not view the training tracks page or toggle tracks', function () {
    $agent = User::factory()->create();
    $track = CourseTrack::factory()->create();

    $this->actingAs($agent)->get(route('admin.training-tracks.index'))->assertForbidden();
    $this->actingAs($agent)
        ->post(route('admin.users.tracks.store', [$agent, $track->id]))
        ->assertForbidden();

    expect($agent->courseTracks()->count())->toBe(0);
});

test('the number of tracks toggled on is shared for the sidebar', function () {
    $agent = User::factory()->create();
    $agent->courseTracks()->attach(CourseTrack::factory()->create());

    $this->actingAs($agent)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.course_tracks_count', 1));
});

test('the training tracks page paginates users with a selectable page size', function () {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->count(11)->create();

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 10)
            ->where('users.meta.total', 11)
            ->where('users.meta.last_page', 2)
            ->where('filters.per_page', '10')
            ->where('perPageOptions', [10, 25, 50, 100]));

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index', ['per_page' => 25]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 11)->where('filters.per_page', '25'));

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index', ['per_page' => 7]))
        ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', '10'));
});

test('the training tracks page can be filtered by campaign, team, location and status', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $team = Team::factory()->create();
    $match = User::factory()->forTeam($team)->create(['work_location' => 'offshore']);
    User::factory()->forTeam($team)->inactive()->create(['work_location' => 'offshore']);
    User::factory()->forTeam($team)->create(['work_location' => 'onshore']);
    User::factory()->forTeam(Team::factory()->create())->create(['work_location' => 'offshore']);

    $this->actingAs($superAdmin)
        ->get(route('admin.training-tracks.index', [
            'campaign' => $team->campaign_id,
            'team' => $team->id,
            'work_location' => 'offshore',
            'status' => 'active',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $match->id));
});

test('the training tracks page can be filtered by assigned track', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $track = CourseTrack::factory()->create();
    $assigned = User::factory()->create();
    $assigned->courseTracks()->attach($track);
    $unassigned = User::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.training-tracks.index', ['track' => $track->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $assigned->id));

    $this->actingAs($superAdmin)
        ->get(route('admin.training-tracks.index', ['track' => 'none']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $unassigned->id));
});
