<?php

namespace App\Http\Requests\Admin;

use App\Enums\CallRating;
use App\Models\CallLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCallLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var CallLog $callLog */
        $callLog = $this->route('callLog');

        return $this->user()?->can('update', $callLog) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'clarity' => ['nullable', Rule::enum(CallRating::class)],
            'energy_tone' => ['nullable', Rule::enum(CallRating::class)],
            'composure_on_pushback' => ['nullable', Rule::enum(CallRating::class)],
            'overall_gut_check' => ['nullable', Rule::enum(CallRating::class)],
        ];
    }
}
