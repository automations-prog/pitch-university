<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Models\Campaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageCampaigns) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Campaign::class)->ignore($campaign)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
