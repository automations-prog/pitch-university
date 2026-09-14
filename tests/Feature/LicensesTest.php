<?php

use App\Models\License;
use App\Models\LicenseStep;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a user can view their assigned licenses', function () {
    $agent = User::factory()->create();
    $license = License::factory()->create();
    $agent->licenses()->attach($license);
    License::factory()->create(); // unassigned, should not appear

    $response = $this->actingAs($agent)->get(route('licenses.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('licenses/index')
        ->has('licenses', 1)
        ->where('licenses.0.id', $license->id),
    );
});

test('a user can view the steps of a license assigned to them', function () {
    $agent = User::factory()->create();
    $license = License::factory()->create();
    $agent->licenses()->attach($license);
    LicenseStep::factory()->for($license)->create(['title' => 'First', 'order' => 0]);
    LicenseStep::factory()->for($license)->create(['title' => 'Second', 'order' => 1]);

    $response = $this->actingAs($agent)->get(route('licenses.show', $license));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('licenses/show')
        ->where('license.id', $license->id)
        ->has('steps', 2)
        ->where('steps.0.title', 'First')
        ->where('steps.1.title', 'Second'),
    );
});

test('inactive licenses do not appear in a user\'s license list', function () {
    $agent = User::factory()->create();
    $active = License::factory()->create();
    $inactive = License::factory()->inactive()->create();
    $agent->licenses()->attach([$active->id, $inactive->id]);

    $response = $this->actingAs($agent)->get(route('licenses.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('licenses', 1)
        ->where('licenses.0.id', $active->id),
    );
});

test('a user can not view an inactive license even if it is assigned to them', function () {
    $agent = User::factory()->create();
    $license = License::factory()->inactive()->create();
    $agent->licenses()->attach($license);

    $response = $this->actingAs($agent)->get(route('licenses.show', $license));

    $response->assertForbidden();
});

test('admins can still view an inactive license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->inactive()->create();

    $response = $this->actingAs($admin)->get(route('licenses.show', $license));

    $response->assertOk();
});

test('the shared licenses_count only counts active assignments', function () {
    $agent = User::factory()->create();
    $inactive = License::factory()->inactive()->create();
    $agent->licenses()->attach($inactive);

    $this->actingAs($agent)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.licenses_count', 0));
});

test('a user can not view a license not assigned to them', function () {
    $agent = User::factory()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($agent)->get(route('licenses.show', $license));

    $response->assertForbidden();
});

test('admins can view any license regardless of assignment', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->get(route('licenses.show', $license));

    $response->assertOk();
});

test('guests are redirected to login', function () {
    $license = License::factory()->create();

    $this->get(route('licenses.index'))->assertRedirect(route('login'));
    $this->get(route('licenses.show', $license))->assertRedirect(route('login'));
});

test('the shared auth prop reflects whether the user has any licenses', function () {
    $agentWithLicense = User::factory()->create();
    License::factory()->create()->users()->attach($agentWithLicense);
    $agentWithoutLicense = User::factory()->create();

    $this->actingAs($agentWithLicense)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.licenses_count', 1));

    $this->actingAs($agentWithoutLicense)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.licenses_count', 0));
});
