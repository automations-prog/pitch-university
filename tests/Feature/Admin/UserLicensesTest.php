<?php

use App\Models\License;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the user edit page exposes assigned and available licenses', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $assigned = License::factory()->create();
    $unassigned = License::factory()->create();
    $agent->licenses()->attach($assigned);

    $response = $this->actingAs($admin)->get(route('admin.users.edit', $agent));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/edit')
        ->has('licenses', 1)
        ->where('licenses.0.id', $assigned->id)
        ->has('availableLicenses', 2),
    );

    expect($unassigned)->not->toBeNull();
});

test('inactive licenses are excluded from the available licenses list', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    License::factory()->create();
    License::factory()->inactive()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.edit', $agent));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('availableLicenses', 1),
    );
});

test('an inactive license can not be assigned to a user', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $license = License::factory()->inactive()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.licenses.store', [$agent, $license]));

    $response->assertSessionHasErrors('license');
    $this->assertDatabaseMissing('license_user', [
        'user_id' => $agent->id,
        'license_id' => $license->id,
    ]);
});

test('admins can assign a license to a user', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.licenses.store', [$agent, $license]));

    $response->assertRedirect(route('admin.users.edit', $agent));
    $this->assertDatabaseHas('license_user', [
        'user_id' => $agent->id,
        'license_id' => $license->id,
    ]);
});

test('assigning the same license twice does not duplicate the row', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $license = License::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.licenses.store', [$agent, $license]));
    $this->actingAs($admin)->post(route('admin.users.licenses.store', [$agent, $license]));

    expect($agent->licenses()->count())->toBe(1);
});

test('admins can remove a license from a user', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();
    $license = License::factory()->create();
    $agent->licenses()->attach($license);

    $response = $this->actingAs($admin)->delete(route('admin.users.licenses.destroy', [$agent, $license]));

    $response->assertRedirect(route('admin.users.edit', $agent));
    $this->assertDatabaseMissing('license_user', [
        'user_id' => $agent->id,
        'license_id' => $license->id,
    ]);
});

test('agents can not assign or remove licenses', function () {
    $agent = User::factory()->create();
    $otherAgent = User::factory()->create();
    $license = License::factory()->create();

    $this->actingAs($agent)
        ->post(route('admin.users.licenses.store', [$otherAgent, $license]))
        ->assertForbidden();

    $otherAgent->licenses()->attach($license);

    $this->actingAs($agent)
        ->delete(route('admin.users.licenses.destroy', [$otherAgent, $license]))
        ->assertForbidden();

    $this->assertDatabaseHas('license_user', [
        'user_id' => $otherAgent->id,
        'license_id' => $license->id,
    ]);
});
