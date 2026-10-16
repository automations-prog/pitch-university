<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Enums\WorkLocation;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait UserAssignmentValidationRules
{
    /**
     * Get the rules for a user's role, team and work location, limited to
     * the roles and teams the acting user may assign. Only super admins may
     * leave a user without a team, and a team can have only one admin.
     *
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function assignmentRules(User $actor, bool $requiresWorkLocation, ?User $user = null): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)->only($actor->assignableRoles())],
            'team_id' => [
                $actor->isSuperAdmin() ? 'nullable' : 'required',
                'integer',
                Rule::in($actor->assignableTeams()->pluck('id')->all()),
                function (string $attribute, mixed $teamId, Closure $fail) use ($user) {
                    $hasOtherAdmin = $this->input('role') === UserRole::Admin->value
                        && User::query()
                            ->where('role', UserRole::Admin)
                            ->where('team_id', $teamId)
                            ->whereKeyNot($user?->id ?? 0)
                            ->exists();

                    if ($hasOtherAdmin) {
                        $fail(__('This team already has an admin.'));
                    }
                },
            ],
            'work_location' => [$requiresWorkLocation ? 'required' : 'nullable', Rule::enum(WorkLocation::class)],
        ];
    }
}
