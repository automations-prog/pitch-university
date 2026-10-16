<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Teams are now run by one admin, whose own team is the one they
     * manage. Supervisors become agents, and each team's first manager
     * from the old leaders table moves onto that team.
     */
    public function up(): void
    {
        if (Schema::hasTable('team_leaders')) {
            DB::table('team_leaders')
                ->join('users', 'users.id', '=', 'team_leaders.user_id')
                ->where('team_leaders.role', 'manager')
                ->where('users.role', UserRole::Admin->value)
                ->orderBy('team_leaders.id')
                ->get(['team_leaders.team_id', 'team_leaders.user_id'])
                ->unique('team_id')
                ->unique('user_id')
                ->each(fn (object $leader) => DB::table('users')
                    ->where('id', $leader->user_id)
                    ->update(['team_id' => $leader->team_id]));

            Schema::drop('team_leaders');
        }

        $supervisorIds = DB::table('users')->where('role', 'supervisor')->pluck('id');

        if ($supervisorIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $supervisorIds)->update(['role' => UserRole::Agent->value]);

            $agentRole = Role::findByName(UserRole::Agent->value, 'web');

            DB::table(config('permission.table_names.model_has_roles'))
                ->where('model_type', (new User)->getMorphClass())
                ->whereIn('model_id', $supervisorIds)
                ->delete();

            DB::table(config('permission.table_names.model_has_roles'))->insert(
                $supervisorIds->map(fn (int $id) => [
                    'role_id' => $agentRole->id,
                    'model_type' => (new User)->getMorphClass(),
                    'model_id' => $id,
                ])->all(),
            );
        }

        Role::where('name', 'supervisor')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The supervisor role and team leaders aren't restored; teams keep
     * their admin through the admin's own team.
     */
    public function down(): void
    {
        //
    }
};
