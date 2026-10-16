<?php

namespace App\Http\Requests\Admin;

use App\Models\TeamInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TeamInvitation::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. Links can only
     * be made for teams the user may put people on.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::in($this->user()->assignableTeams()->pluck('id')->all())],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
