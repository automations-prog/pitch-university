<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

test('existing admins become super admins and every user gets their Spatie role', function () {
    DB::table('users')->insert([
        ['name' => 'Old Admin', 'email' => 'old-admin@example.com', 'password' => 'x', 'role' => 'admin', 'status' => 'active'],
        ['name' => 'Old Agent', 'email' => 'old-agent@example.com', 'password' => 'x', 'role' => 'agent', 'status' => 'active'],
    ]);

    (require database_path('migrations/2026_10_16_183833_seed_roles_and_permissions.php'))->up();

    $admin = User::where('email', 'old-admin@example.com')->sole();
    $agent = User::where('email', 'old-agent@example.com')->sole();

    expect($admin->role->value)->toBe('super_admin')
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and($agent->hasRole('agent'))->toBeTrue()
        ->and($agent->can('users.view'))->toBeFalse();
});

test('supervisors become agents and the supervisor role is removed', function () {
    $supervisorRole = Role::create(['name' => 'supervisor', 'guard_name' => 'web']);
    DB::table('users')->insert(['name' => 'Old Supervisor', 'email' => 'old-supervisor@example.com', 'password' => 'x', 'role' => 'supervisor', 'status' => 'active']);
    $supervisor = User::where('email', 'old-supervisor@example.com')->sole();
    DB::table('model_has_roles')->insert(['role_id' => $supervisorRole->id, 'model_type' => $supervisor->getMorphClass(), 'model_id' => $supervisor->id]);

    (require database_path('migrations/2026_10_16_191922_remove_supervisors_and_team_leaders.php'))->up();

    $agent = $supervisor->fresh();

    expect($agent->role->value)->toBe('agent')
        ->and($agent->hasRole('agent'))->toBeTrue()
        ->and(Role::where('name', 'supervisor')->exists())->toBeFalse();
});
