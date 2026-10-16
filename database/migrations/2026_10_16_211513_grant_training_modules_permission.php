<?php

use App\Enums\Permission as PermissionName;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Roles created before the Training Modules page existed don't hold the new
     * permission yet; give it to the roles that have it by default.
     */
    public function up(): void
    {
        Permission::findOrCreate(PermissionName::ViewTrainingModules->value, 'web');

        foreach (UserRole::cases() as $userRole) {
            if (in_array(PermissionName::ViewTrainingModules, PermissionName::defaultsFor($userRole), true)) {
                Role::findByName($userRole->value, 'web')->givePermissionTo(PermissionName::ViewTrainingModules->value);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', PermissionName::ViewTrainingModules->value)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
