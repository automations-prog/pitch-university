<?php

use App\Enums\RoleplayVertical;
use App\Models\CourseTrack;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('agents only open the roleplay verticals their tracks open', function () {
    $agent = User::factory()->withRoleplayTrack(RoleplayVertical::Medicare)->create();

    $this->actingAs($agent)->get(route('medicare-roleplay.index'))->assertOk();
    $this->actingAs($agent)->get(route('aca-roleplay.index'))->assertForbidden();
    $this->actingAs($agent)->get(route('meals-roleplay.index'))->assertForbidden();
});

test('agents can not start a call on a vertical none of their tracks open', function () {
    $agent = User::factory()->withRoleplayTrack(RoleplayVertical::Medicare)->create();

    $this->actingAs($agent)
        ->postJson(route('aca-roleplay.sessions.store'), ['level' => 1])
        ->assertForbidden();

    expect($agent->roleplaySessions()->count())->toBe(0);
});

test('agents without a track that opens a vertical can not practice any roleplay', function () {
    $agent = User::factory()->create();
    $agent->courseTracks()->attach(CourseTrack::factory()->create(['vertical' => null]));

    foreach (RoleplayVertical::cases() as $vertical) {
        $this->actingAs($agent)->get(route("{$vertical->routePrefix()}.index"))->assertForbidden();
    }
});

test('removing a track takes its roleplay vertical away', function () {
    $agent = User::factory()->withRoleplayTrack(RoleplayVertical::Aca)->create();

    $agent->courseTracks()->detach();

    $this->actingAs($agent)->get(route('aca-roleplay.index'))->assertForbidden();
});

test('agents are only shown the roleplay verticals their tracks open', function () {
    $agent = User::factory()->withRoleplayTrack(RoleplayVertical::Meals)->create();

    $this->actingAs($agent)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('roleplay.meals.view')
                && ! collect($permissions)->contains('roleplay.medicare.view')
                && ! collect($permissions)->contains('roleplay.aca.view')));
});

test('admins practice every roleplay vertical without tracks', function () {
    $admin = User::factory()->admin()->create();

    foreach (RoleplayVertical::cases() as $vertical) {
        $this->actingAs($admin)->get(route("{$vertical->routePrefix()}.index"))->assertOk();
    }

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('roleplay.medicare.view')
                && collect($permissions)->contains('roleplay.aca.view')
                && collect($permissions)->contains('roleplay.meals.view')));
});
