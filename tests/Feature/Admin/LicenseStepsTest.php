<?php

use App\Models\License;
use App\Models\LicenseStep;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the license edit page exposes steps in order', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $second = LicenseStep::factory()->for($license)->create(['title' => 'Second', 'order' => 1]);
    $first = LicenseStep::factory()->for($license)->create(['title' => 'First', 'order' => 0]);

    $response = $this->actingAs($admin)->get(route('admin.licensing.edit', $license));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/licensing/edit')
        ->where('steps.0.id', $first->id)
        ->where('steps.1.id', $second->id),
    );
});

test('admins can add a step to a license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.licensing.steps.store', $license), [
        'title' => 'Complete pre-licensing course',
        'description' => 'Finish the required coursework.',
    ]);

    $response->assertRedirect(route('admin.licensing.edit', $license));
    $this->assertDatabaseHas('license_steps', [
        'license_id' => $license->id,
        'title' => 'Complete pre-licensing course',
        'order' => 0,
    ]);
});

test('a step description is required', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.licensing.steps.store', $license), [
        'title' => 'Missing description',
    ]);

    $response->assertSessionHasErrors('description');
    $this->assertDatabaseMissing('license_steps', [
        'license_id' => $license->id,
        'title' => 'Missing description',
    ]);
});

test('a new step is appended after existing steps', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    LicenseStep::factory()->for($license)->create(['order' => 0]);
    LicenseStep::factory()->for($license)->create(['order' => 1]);

    $this->actingAs($admin)->post(route('admin.licensing.steps.store', $license), [
        'title' => 'New step',
        'description' => 'Details for the new step.',
    ]);

    $this->assertDatabaseHas('license_steps', [
        'license_id' => $license->id,
        'title' => 'New step',
        'order' => 2,
    ]);
});

test('admins can update a step', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $step = LicenseStep::factory()->for($license)->create();

    $response = $this->actingAs($admin)->put(route('admin.licensing.steps.update', [$license, $step]), [
        'title' => 'Updated title',
        'description' => 'Updated description.',
    ]);

    $response->assertRedirect(route('admin.licensing.edit', $license));
    $this->assertDatabaseHas('license_steps', [
        'id' => $step->id,
        'title' => 'Updated title',
    ]);
});

test('admins can delete a step', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $step = LicenseStep::factory()->for($license)->create();

    $response = $this->actingAs($admin)->delete(route('admin.licensing.steps.destroy', [$license, $step]));

    $response->assertRedirect(route('admin.licensing.edit', $license));
    $this->assertDatabaseMissing('license_steps', ['id' => $step->id]);
});

test('admins can reorder steps', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $first = LicenseStep::factory()->for($license)->create(['order' => 0]);
    $second = LicenseStep::factory()->for($license)->create(['order' => 1]);

    $response = $this->actingAs($admin)->patch(route('admin.licensing.steps.move', [$license, $second]), [
        'direction' => 'up',
    ]);

    $response->assertRedirect(route('admin.licensing.edit', $license));
    $this->assertDatabaseHas('license_steps', ['id' => $second->id, 'order' => 0]);
    $this->assertDatabaseHas('license_steps', ['id' => $first->id, 'order' => 1]);
});

test('moving the first step up is a no-op', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $first = LicenseStep::factory()->for($license)->create(['order' => 0]);

    $this->actingAs($admin)->patch(route('admin.licensing.steps.move', [$license, $first]), [
        'direction' => 'up',
    ]);

    $this->assertDatabaseHas('license_steps', ['id' => $first->id, 'order' => 0]);
});

test('agents can not manage license steps', function () {
    $agent = User::factory()->create();
    $license = License::factory()->create();
    $step = LicenseStep::factory()->for($license)->create();

    $this->actingAs($agent)
        ->post(route('admin.licensing.steps.store', $license), ['title' => 'Blocked'])
        ->assertForbidden();

    $this->actingAs($agent)
        ->put(route('admin.licensing.steps.update', [$license, $step]), ['title' => 'Blocked'])
        ->assertForbidden();

    $this->actingAs($agent)
        ->delete(route('admin.licensing.steps.destroy', [$license, $step]))
        ->assertForbidden();

    $this->actingAs($agent)
        ->patch(route('admin.licensing.steps.move', [$license, $step]), ['direction' => 'up'])
        ->assertForbidden();

    $this->assertDatabaseHas('license_steps', ['id' => $step->id]);
});

test('a step can not be managed through a mismatched license url', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();
    $otherLicense = License::factory()->create();
    $step = LicenseStep::factory()->for($license)->create();

    $response = $this->actingAs($admin)->put(route('admin.licensing.steps.update', [$otherLicense, $step]), [
        'title' => 'Should not work',
    ]);

    $response->assertNotFound();
});
