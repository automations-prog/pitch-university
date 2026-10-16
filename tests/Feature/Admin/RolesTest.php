<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the roles page lists every role with its permissions', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.roles.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/roles/index')
            ->has('roles', 3)
            ->where('roles.0.name', 'super_admin')
            ->where('roles.0.locked', true)
            ->has('permissions'));
});

test('removing a permission from a role takes the page away from its users', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', 'admin'), [
            'permissions' => ['dashboard.view', 'users.view'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $this->actingAs($admin->fresh())->get(route('admin.reports.index'))->assertForbidden();
});

test('the super admin role can not be edited', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', 'super_admin'), ['permissions' => []])
        ->assertForbidden();

    expect($superAdmin->fresh()->can('roles.manage'))->toBeTrue();
});

test('only known permissions can be given to a role', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.roles.update', 'agent'), ['permissions' => ['everything.view']])
        ->assertSessionHasErrors('permissions.0');
});
