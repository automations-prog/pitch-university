<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Super
     * admins always hold every permission, so their role can't be edited.
     */
    public function authorize(): bool
    {
        /** @var Role $role */
        $role = $this->route('role');

        return ($this->user()?->can(Permission::ManageRoles) ?? false)
            && $role->name !== UserRole::SuperAdmin->value;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(Permission::class)],
        ];
    }
}
