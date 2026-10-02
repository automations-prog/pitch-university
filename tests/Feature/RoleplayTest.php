<?php

use App\Models\RoleplaySession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('roleplay.index'));

    $response->assertRedirect(route('login'));
});

test('a verified user can open the roleplay page with the script content', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('roleplay.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('roleplay/index')
        ->has('content.scriptSections', 7)
        ->has('content.objections', 24)
        ->has('content.levels', 5)
        ->has('recentSessions', 0));
});

test('the roleplay page lists only the user\'s own finished calls', function () {
    $user = User::factory()->create();
    $finished = RoleplaySession::factory()->for($user)->ended()->create();
    RoleplaySession::factory()->for($user)->started()->create();
    RoleplaySession::factory()->ended()->create();

    $response = $this->actingAs($user)->get(route('roleplay.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('recentSessions', 1)
        ->where('recentSessions.0.id', $finished->id));
});
