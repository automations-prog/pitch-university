<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageTeams) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. The team's
     * manager must be an admin who doesn't already run another team.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', Rule::exists(Campaign::class, 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Team::class)->where('campaign_id', $this->integer('campaign_id')),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id')->where('role', UserRole::Admin->value),
                function (string $attribute, mixed $managerId, Closure $fail) {
                    $manager = User::with('team:id,name')->find($managerId);

                    if ($manager?->team !== null && $manager->team->id !== null) {
                        $fail(__(':name already runs the :team team. An admin can only run one team.', [
                            'name' => $manager->name,
                            'team' => $manager->team->name,
                        ]));
                    }
                },
            ],
        ];
    }
}
