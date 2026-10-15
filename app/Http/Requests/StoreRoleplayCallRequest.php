<?php

namespace App\Http\Requests;

use App\Enums\RoleplayVertical;
use App\Models\RoleplaySession;
use App\Services\RoleplayDeliveryMetrics;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoleplayCallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Returns the
     * policy response so another user's session 404s instead of 403ing,
     * and a session 404s under another vertical's routes.
     */
    public function authorize(): Response
    {
        $session = $this->route('roleplaySession');
        $vertical = $this->route('vertical');

        if ($session->vertical !== ($vertical instanceof RoleplayVertical ? $vertical : RoleplayVertical::from($vertical))) {
            return Response::denyAsNotFound();
        }

        return Gate::inspect('update', $session);
    }

    /**
     * Browsers send multipart text fields with CRLF line breaks; store the
     * transcript with plain LF so every `[m:ss] role: text` line parses.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('transcript'))) {
            $this->merge([
                'transcript' => $this->withoutTranscriptionEchoes(str_replace(["\r\n", "\r"], "\n", $this->input('transcript'))),
            ]);
        }
    }

    /**
     * Drop trainee lines that are the transcription prompt read back
     * instead of what they said, so they're never graded as theirs.
     */
    private function withoutTranscriptionEchoes(string $transcript): string
    {
        $session = $this->route('roleplaySession');

        if (! $session instanceof RoleplaySession) {
            return $transcript;
        }

        $prompt = $session->vertical->consumerPrompt();

        return implode("\n", array_filter(
            explode("\n", $transcript),
            fn (string $line): bool => ! (preg_match('/^\[\d+:\d{2}\] agent: (.*)$/', $line, $matches) && $prompt->isTranscriptionEcho($matches[1])),
        ));
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
            // A training scene that stops before the end of the call isn't coded.
            'disposition' => [Rule::requiredIf($this->route('roleplaySession')->codesCall()), 'nullable', 'string', Rule::in($this->route('roleplaySession')->vertical->script()->dispositionIds())],
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
