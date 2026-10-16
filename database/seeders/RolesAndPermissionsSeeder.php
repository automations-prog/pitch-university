<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionName;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every role and permission. Safe to run again: a role only gets
 * its default permissions when it's first created, so edits made on the
 * Roles & Permissions page are kept. Super admins always hold everything.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (UserRole::cases() as $userRole) {
            $role = Role::firstOrCreate(['name' => $userRole->value, 'guard_name' => 'web']);

            if ($userRole === UserRole::SuperAdmin || $role->wasRecentlyCreated) {
                $role->syncPermissions(array_map(
                    fn (PermissionName $permission) => $permission->value,
                    PermissionName::defaultsFor($userRole),
                ));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
