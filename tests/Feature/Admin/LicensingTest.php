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

test('admins can create a license with instruction steps', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.licensing.store'), [
        'name' => 'Life Insurance License',
        'status' => 'active',
        'steps' => [
            ['title' => 'Complete pre-licensing course', 'description' => 'Finish the required coursework.'],
            ['title' => 'Pass the state exam', 'description' => 'Schedule and pass the exam.'],
        ],
    ]);

    $response->assertRedirect(route('admin.licensing.index'));
    $this->assertDatabaseHas('licenses', [
        'name' => 'Life Insurance License',
        'status' => 'active',
    ]);
    $license = License::where('name', 'Life Insurance License')->firstOrFail();
    $this->assertDatabaseHas('license_steps', [
        'license_id' => $license->id,
        'title' => 'Complete pre-licensing course',
        'order' => 0,
    ]);
    $this->assertDatabaseHas('license_steps', [
        'license_id' => $license->id,
        'title' => 'Pass the state exam',
        'order' => 1,
    ]);
});

test('a license requires at least one instruction step', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.licensing.store'), [
        'name' => 'Life Insurance License',
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors('steps');
    $this->assertDatabaseMissing('licenses', ['name' => 'Life Insurance License']);
});

test('each instruction step requires a title and description', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.licensing.store'), [
        'name' => 'Life Insurance License',
        'status' => 'active',
        'steps' => [
            ['title' => '', 'description' => ''],
        ],
    ]);

    $response->assertSessionHasErrors(['steps.0.title', 'steps.0.description']);
    $this->assertDatabaseMissing('licenses', ['name' => 'Life Insurance License']);
});

test('license names must be unique', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->create(['name' => 'Life Insurance License']);

    $response = $this->actingAs($admin)->post(route('admin.licensing.store'), [
        'name' => 'Life Insurance License',
        'status' => 'active',
        'steps' => [
            ['title' => 'Complete pre-licensing course', 'description' => 'Finish the required coursework.'],
        ],
    ]);

    $response->assertSessionHasErrors('name');
});

test('agents can not create a license', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('admin.licensing.store'), [
        'name' => 'Blocked License',
        'status' => 'active',
        'steps' => [
            ['title' => 'Complete pre-licensing course', 'description' => 'Finish the required coursework.'],
        ],
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

test('admins can bulk delete licenses', function () {
    $admin = User::factory()->admin()->create();
    $licenses = License::factory()->count(3)->create();

    $response = $this->actingAs($admin)->delete(route('admin.licensing.bulk-destroy'), [
        'ids' => $licenses->pluck('id')->toArray(),
    ]);

    $response->assertRedirect(route('admin.licensing.index'));
    foreach ($licenses as $license) {
        $this->assertDatabaseMissing('licenses', ['id' => $license->id]);
    }
});

test('bulk deleting licenses requires at least one id', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('admin.licensing.bulk-destroy'), [
        'ids' => [],
    ]);

    $response->assertSessionHasErrors('ids');
});

test('agents can not bulk delete licenses', function () {
    $agent = User::factory()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($agent)->delete(route('admin.licensing.bulk-destroy'), [
        'ids' => [$license->id],
    ]);

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
