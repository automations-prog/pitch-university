<?php

namespace App\Http\Requests;

use App\Enums\RoleplayVertical;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleplaySessionRequest extends FormRequest
{
    /**
     * Agents may only start calls on a vertical one of their tracks opens.
     */
    public function authorize(): bool
    {
        /** @var RoleplayVertical $vertical */
        $vertical = $this->route('vertical');

        return $this->user()->canPracticeRoleplay($vertical);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
