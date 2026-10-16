<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display the matrix of roles and the permissions each one holds.
     */
    public function index(): Response
    {
        $roles = Role::query()->with('permissions:id,name')->get()->keyBy('name');

        return Inertia::render('admin/roles/index', [
            'roles' => collect(UserRole::cases())
                ->filter(fn (UserRole $role) => $roles->has($role->value))
                ->map(fn (UserRole $role) => [
                    'name' => $role->value,
                    'label' => $role->label(),
                    'locked' => $role === UserRole::SuperAdmin,
                    'permissions' => $roles[$role->value]->permissions->pluck('name'),
                ])
                ->values(),
            'permissions' => collect(Permission::cases())->map(fn (Permission $permission) => [
                'name' => $permission->value,
                'label' => $permission->label(),
                'group' => $permission->group(),
            ]),
        ]);
    }

    /**
     * Replace the permissions the given role holds.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->syncPermissions($request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':role permissions updated.', [
            'role' => UserRole::from($role->name)->label(),
        ])]);

        return to_route('admin.roles.index');
    }
}
