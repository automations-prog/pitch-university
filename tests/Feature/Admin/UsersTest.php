<?php

use App\Models\License;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the users index', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->has('users.data', 4)
        ->has('users.links.0.url')
        ->has('users.links.0.label')
        ->has('users.links.0.active')
        ->has('users.meta.current_page')
        ->missing('users.meta.links'),
    );
});

test('the users index exposes each user\'s assigned licenses', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create(['name' => 'Zzyzx Licensed Agent']);
    $license = License::factory()->create();
    $agent->licenses()->attach($license);

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Zzyzx']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->has('users.data', 1)
        ->has('users.data.0.licenses', 1)
        ->where('users.data.0.licenses.0.id', $license->id),
    );
});

test('the edit page exposes the user as a flat, unwrapped object', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.edit', $user));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/edit')
        ->where('user.id', $user->id)
        ->missing('user.data'),
    );
});

test('users index can be paginated with a selectable page size', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(24)->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/index')
        ->has('users.data', 10)
        ->where('users.meta.per_page', 10)
        ->where('users.meta.total', 25)
        ->where('filters.per_page', '10')
        ->where('perPageOptions', [10, 25, 50, 100]),
    );

    $response = $this->actingAs($admin)->get(route('admin.users.index', ['per_page' => 999]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('users.meta.per_page', 10),
    );
});

test('agents can not view the users index', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->get(route('admin.users.index'));

    $response->assertForbidden();
});

test('guests are redirected to login', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('login'));
});

test('admins can create a user', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Agent',
        'email' => 'new-agent@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'agent',
        'status' => 'active',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', [
        'email' => 'new-agent@example.com',
        'role' => 'agent',
        'status' => 'active',
    ]);
});

test('admins can assign licenses while creating a user', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Agent',
        'email' => 'licensed-agent@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'agent',
        'status' => 'active',
        'license_ids' => [$license->id],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $user = User::where('email', 'licensed-agent@example.com')->firstOrFail();
    $this->assertDatabaseHas('license_user', [
        'user_id' => $user->id,
        'license_id' => $license->id,
    ]);
});

test('an inactive license can not be assigned while creating a user', function () {
    $admin = User::factory()->admin()->create();
    $license = License::factory()->inactive()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Agent',
        'email' => 'blocked-license@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'agent',
        'status' => 'active',
        'license_ids' => [$license->id],
    ]);

    $response->assertSessionHasErrors('license_ids.0');
    $this->assertDatabaseMissing('users', ['email' => 'blocked-license@example.com']);
});

test('inactive licenses are excluded from the create page and bulk-assign picker', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->create();
    License::factory()->inactive()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertInertia(fn (Assert $page) => $page->has('licenses', 1));

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('licenses', 1));
});

test('an inactive license can not be bulk assigned', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();
    $license = License::factory()->inactive()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.bulk-assign-license'), [
        'user_ids' => [$target->id],
        'license_id' => $license->id,
    ]);

    $response->assertSessionHasErrors('license_id');
    $this->assertDatabaseMissing('license_user', [
        'user_id' => $target->id,
        'license_id' => $license->id,
    ]);
});

test('the create page exposes the available licenses', function () {
    $admin = User::factory()->admin()->create();
    License::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.users.create'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/create')
        ->has('licenses', 1),
    );
});

test('agents can not create a user', function () {
    $agent = User::factory()->create();

    $response = $this->actingAs($agent)->post(route('admin.users.store'), [
        'name' => 'New Agent',
        'email' => 'blocked@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'agent',
        'status' => 'active',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
});

test('admins can update a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'password' => '',
        'password_confirmation' => '',
        'role' => 'agent',
        'status' => 'inactive',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'status' => 'inactive',
    ]);
});

test('admins can delete a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('admins can not delete themselves', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

    $response->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admins can bulk delete users', function () {
    $admin = User::factory()->admin()->create();
    $users = User::factory()->count(3)->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.bulk-destroy'), [
        'ids' => $users->pluck('id')->toArray(),
    ]);

    $response->assertRedirect(route('admin.users.index'));
    foreach ($users as $user) {
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
});

test('bulk delete is blocked if it includes the acting admin', function () {
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.bulk-destroy'), [
        'ids' => [$admin->id, $agent->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
    $this->assertDatabaseHas('users', ['id' => $agent->id]);
});

test('agents can not bulk delete users', function () {
    $agent = User::factory()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($agent)->delete(route('admin.users.bulk-destroy'), [
        'ids' => [$target->id],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('bulk delete requires at least one id', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete(route('admin.users.bulk-destroy'), [
        'ids' => [],
    ]);

    $response->assertSessionHasErrors('ids');
});

test('admins can bulk assign a license to multiple users', function () {
    $admin = User::factory()->admin()->create();
    $users = User::factory()->count(3)->create();
    $license = License::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.bulk-assign-license'), [
        'user_ids' => $users->pluck('id')->toArray(),
        'license_id' => $license->id,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    foreach ($users as $user) {
        $this->assertDatabaseHas('license_user', [
            'user_id' => $user->id,
            'license_id' => $license->id,
        ]);
    }
});

test('bulk assigning a license already held does not duplicate the row', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $license = License::factory()->create();
    $user->licenses()->attach($license);

    $this->actingAs($admin)->post(route('admin.users.bulk-assign-license'), [
        'user_ids' => [$user->id],
        'license_id' => $license->id,
    ]);

    expect($user->licenses()->count())->toBe(1);
});

test('agents can not bulk assign licenses', function () {
    $agent = User::factory()->create();
    $target = User::factory()->create();
    $license = License::factory()->create();

    $response = $this->actingAs($agent)->post(route('admin.users.bulk-assign-license'), [
        'user_ids' => [$target->id],
        'license_id' => $license->id,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('license_user', [
        'user_id' => $target->id,
        'license_id' => $license->id,
    ]);
});

test('bulk assign requires at least one user and a valid license', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.bulk-assign-license'), [
        'user_ids' => [],
        'license_id' => null,
    ])->assertSessionHasErrors(['user_ids', 'license_id']);
});

test('admins can update a user via method-spoofed post (matches frontend Form)', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.update', $user).'?_method=PUT', [
        'name' => 'Updated Name',
        'email' => $user->email,
        'password' => '',
        'password_confirmation' => '',
        'role' => 'agent',
        'status' => 'inactive',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'status' => 'inactive',
    ]);
});
