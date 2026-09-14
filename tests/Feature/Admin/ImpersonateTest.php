<?php

use App\Models\User;

test('admins can impersonate an active agent', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $agent));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($agent);
});

test('agents can not impersonate other users', function () {
    $agent = User::factory()->create();
    $other = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('admin.users.impersonate', $other));

    $response->assertForbidden();
    $this->assertAuthenticatedAs($agent);
});

test('admins can not impersonate another admin', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $otherAdmin));

    $response->assertForbidden();
    $this->assertAuthenticatedAs($admin);
});

test('admins can not impersonate an inactive agent', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->inactive()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $agent));

    $response->assertForbidden();
    $this->assertAuthenticatedAs($admin);
});

test('admins can leave impersonation and return to their own session', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.impersonate', $agent));
    $this->assertAuthenticatedAs($agent);

    $response = $this->delete(route('impersonate.leave'));

    $response->assertRedirect(route('admin.users.index'));
    $this->assertAuthenticatedAs($admin);
});

test('after leaving impersonation, subsequent pages reflect the admin session and are not cacheable', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();

    // Visit a page as the impersonated agent, then leave.
    $this->actingAs($admin)->post(route('admin.users.impersonate', $agent));
    $this->get(route('dashboard'));
    $this->delete(route('impersonate.leave'));
    $this->assertAuthenticatedAs($admin);

    // Navigating afterwards must show the real admin session, not a stale
    // impersonated view, and must not be browser-cacheable.
    $response = $this->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
});
