<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\WorkLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Anyone holding the link may sign up; the controller checks the
     * link is still usable.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'work_location' => ['required', Rule::enum(WorkLocation::class)],
        ];
    }
}
