<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('roleplay.index'));

    $response->assertRedirect(route('login'));
});

test('a verified user can open the roleplay page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('roleplay.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('roleplay/index'));
});
