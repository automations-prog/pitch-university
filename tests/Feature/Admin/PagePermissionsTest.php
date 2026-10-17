<?php

use App\Models\User;

dataset('pages each role is refused', [
    'admins can not open screening' => ['admin', 'admin.screening.index'],
    'admins can not open campaigns' => ['admin', 'admin.campaigns.index'],
    'admins can not open teams' => ['admin', 'admin.teams.index'],
    'admins can not open roles' => ['admin', 'admin.roles.index'],
    'agents can not open users' => ['agent', 'admin.users.index'],
    'agents can not open reports' => ['agent', 'admin.reports.index'],
    'agents can not open training tracks' => ['agent', 'admin.training-tracks.index'],
    'agents can not open training modules' => ['agent', 'training-modules.index'],
    'agents can not open a roleplay without its track' => ['agent', 'medicare-roleplay.index'],
]);

dataset('pages each role can open', [
    'admins open reports' => ['admin', 'admin.reports.index'],
    'admins open training tracks' => ['admin', 'admin.training-tracks.index'],
    'admins open invite links' => ['admin', 'admin.invitations.index'],
    'admins open licensing' => ['admin', 'admin.licensing.index'],
    'admins open vertical training' => ['admin', 'admin.vertical-training.index'],
    'admins open training modules' => ['admin', 'training-modules.index'],
    'super admins open screening' => ['super_admin', 'admin.screening.index'],
    'super admins open campaigns' => ['super_admin', 'admin.campaigns.index'],
    'super admins open teams' => ['super_admin', 'admin.teams.index'],
    'super admins open roles' => ['super_admin', 'admin.roles.index'],
    'agents open the dashboard' => ['agent', 'dashboard'],
]);

test('roles without a page\'s permission are refused', function (string $role, string $routeName) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route($routeName))->assertForbidden();
})->with('pages each role is refused');

test('roles holding a page\'s permission can open it', function (string $role, string $routeName) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with('pages each role can open');

test('the signed-in user\'s permissions are shared with every page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('reports.view')
                && ! collect($permissions)->contains('screening.view')));
});
