<?php

namespace App\Http\Requests\Admin;

use App\Enums\LicenseStatus;
use App\Models\License;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLicenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('licensing')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var License $license */
        $license = $this->route('licensing');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(License::class)->ignore($license->id)],
            'status' => ['required', Rule::enum(LicenseStatus::class)],
        ];
    }
}
