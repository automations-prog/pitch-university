<?php

use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the training tracks page with each user\'s toggles', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin']);
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
            ->has('users.data', 2)
            ->where('users.data.0.id', $admin->id)
            ->where('users.data.0.track_ids', [])
            ->where('users.data.1.id', $agent->id)
            ->where('users.data.1.track_ids', [$track->id]));
});

test('the training tracks page can be filtered by search and role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Jane Agent']);
    User::factory()->create(['name' => 'John Agent']);

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index', ['search' => 'jane']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Jane Agent'));

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index', ['role' => 'admin']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $admin->id));
});

test('admins can toggle a track on and off, and turning it off keeps progress', function () {
    $admin = User::factory()->admin()->create();
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
    $admin = User::factory()->admin()->create();
    User::factory()->count(11)->create();

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 10)
            ->where('users.meta.total', 12)
            ->where('users.meta.last_page', 2)
            ->where('filters.per_page', '10')
            ->where('perPageOptions', [10, 25, 50, 100]));

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index', ['per_page' => 25]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 12)->where('filters.per_page', '25'));

    $this->actingAs($admin)
        ->get(route('admin.training-tracks.index', ['per_page' => 7]))
        ->assertInertia(fn (Assert $page) => $page->where('filters.per_page', '10'));
});
