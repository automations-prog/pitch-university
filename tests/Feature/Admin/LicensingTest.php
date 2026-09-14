<?php

use App\Models\License;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the licensing index', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('admin.licensing.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/licensing/index')
        ->has('licenses.data', 3)
        ->has('licenses.links.0.url')
        ->has('licenses.meta.current_page')
        ->missing('licenses.meta.links'),
    );
});

test('the licensing index can be searched and filtered by status', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->create(['name' => 'Life Insurance License']);
    License::factory()->inactive()->create(['name' => 'Health License']);

    $response = $this->actingAs($admin)->get(route('admin.licensing.index', ['search' => 'Life']));
    $response->assertInertia(fn (Assert $page) => $page->has('licenses.data', 1));

    $response = $this->actingAs($admin)->get(route('admin.licensing.index', ['status' => 'inactive']));
    $response->assertInertia(fn (Assert $page) => $page->has('licenses.data', 1));
});

test('the licensing index can be paginated with a selectable page size', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('admin.licensing.index', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/licensing/index')
        ->has('licenses.data', 10)
        ->where('licenses.meta.per_page', 10)
        ->where('licenses.meta.total', 24)
        ->where('filters.per_page', '10')
        ->where('perPageOptions', [10, 25, 50, 100]),
    );

    $response = $this->actingAs($admin)->get(route('admin.licensing.index', ['per_page' => 999]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('licenses.meta.per_page', 10),
    );
});

test('agents can not view the licensing index', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('admin.licensing.index'));

    $response->assertForbidden();
});

test('guests are redirected to login for licensing', function () {
    $response = $this->get(route('admin.licensing.index'));

    $response->assertRedirect(route('login'));
});

test('admins can create a license', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.licensing.store'), [
        'name' => 'Life Insurance License',
        'status' => 'active',
    ]);

    $response->assertRedirect(route('admin.licensing.index'));
    $this->assertDatabaseHas('licenses', [
        'name' => 'Life Insurance License',
        'status' => 'active',
    ]);
});

test('license names must be unique', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->create(['name' => 'Life Insurance License']);

    $response = $this->actingAs($admin)->post(route('admin.licensing.store'), [
        'name' => 'Life Insurance License',
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors('name');
});

test('agents can not create a license', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('admin.licensing.store'), [
        'name' => 'Blocked License',
        'status' => 'active',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('licenses', ['name' => 'Blocked License']);
});

test('admins can update a license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.licensing.update', $license), [
        'name' => $license->name,
        'status' => 'inactive',
    ]);

    $response->assertRedirect(route('admin.licensing.index'));
    $this->assertDatabaseHas('licenses', [
        'id' => $license->id,
        'status' => 'inactive',
    ]);
});

test('admins can delete a license', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.licensing.destroy', $license));

    $response->assertRedirect(route('admin.licensing.index'));
    $this->assertDatabaseMissing('licenses', ['id' => $license->id]);
});

test('agents can not delete a license', function () {
    $agent = User::factory()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($agent)->delete(route('admin.licensing.destroy', $license));

    $response->assertForbidden();
    $this->assertDatabaseHas('licenses', ['id' => $license->id]);
});

test('the edit page exposes the license as a flat, unwrapped object', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.licensing.edit', $license));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/licensing/edit')
        ->where('license.id', $license->id)
        ->missing('license.data'),
    );
});
