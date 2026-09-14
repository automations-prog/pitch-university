<?php

namespace App\Http\Requests\Admin;

use App\Enums\VerticalTrainingStatus;
use App\Models\VerticalTraining;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVerticalTrainingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vertical_training')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var VerticalTraining $training */
        $training = $this->route('vertical_training');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(VerticalTraining::class)->ignore($training->id)],
            'status' => ['required', Rule::enum(VerticalTrainingStatus::class)],
            'script_title' => ['nullable', 'string', 'max:255'],
            'script_scenario' => ['nullable', 'string'],
            'script_body' => ['nullable', 'string'],
        ];
    }
}
