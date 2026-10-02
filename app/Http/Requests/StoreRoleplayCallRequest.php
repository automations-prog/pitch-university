<?php

namespace App\Http\Requests;

use App\Services\RoleplayDeliveryMetrics;
use App\Services\RoleplayScript;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoleplayCallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Returns the
     * policy response so another user's session 404s instead of 403ing.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('roleplaySession'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transcript' => ['nullable', 'string', 'max:200000'],
            'recording' => ['required', 'file', 'mimes:webm,wav,ogg,mp3,m4a', 'max:51200'],
            'disposition' => ['required', 'string', Rule::in(app(RoleplayScript::class)->dispositionIds())],
            'end_reason' => ['required', Rule::in(['agent', 'hung_up'])],
            'transfer_clicked_at' => ['nullable', 'integer', 'min:0'],
            'events' => ['nullable', 'json', 'max:1000000'],
        ];
    }

    /**
     * The timed event log, with unknown event types and malformed events
     * dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function events(): array
    {
        $events = json_decode((string) $this->validated('events'), true);

        return is_array($events) ? RoleplayDeliveryMetrics::sanitize($events) : [];
    }
}
