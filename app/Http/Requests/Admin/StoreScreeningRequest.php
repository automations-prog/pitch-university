<?php

namespace App\Http\Requests\Admin;

use App\Enums\RealtimeVoice;
use App\Models\Screening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScreeningRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Screening::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voice' => ['nullable', 'string', Rule::enum(RealtimeVoice::class)],
        ];
    }
}
