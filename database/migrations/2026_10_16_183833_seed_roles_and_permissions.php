<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Today's admins see everything, so they become super admins. Every
     * user is then given the Spatie role matching their `role` column.
     */
    public function up(): void
    {
        (new RolesAndPermissionsSeeder)->run();

        DB::table('users')->where('role', 'admin')->update(['role' => UserRole::SuperAdmin->value]);

        $roleIds = Role::query()->pluck('id', 'name');

        DB::table('users')->select(['id', 'role'])->orderBy('id')->chunk(500, function ($users) use ($roleIds) {
            DB::table(config('permission.table_names.model_has_roles'))->insertOrIgnore(
                $users->map(fn (object $user) => [
                    'role_id' => $roleIds[$user->role] ?? $roleIds[UserRole::Agent->value],
                    'model_type' => (new User)->getMorphClass(),
                    'model_id' => $user->id,
                ])->all(),
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table(config('permission.table_names.model_has_roles'))->delete();

        DB::table('users')->where('role', UserRole::SuperAdmin->value)->update(['role' => 'admin']);
    }
};
