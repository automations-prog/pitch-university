<?php

namespace App\Services;

use App\Models\RoleplaySession;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevenLabsClient
{
    private readonly ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.elevenlabs.key');
    }

    /**
     * The ElevenLabs voice the roleplay consumer speaks in, or null to keep
     * OpenAI's: when ElevenLabs is off, or has no voice of the consumer's
     * gender. The same session always gets the same voice.
     */
    public function voiceFor(RoleplaySession $session): ?string
    {
        $gender = $session->consumerGender();

        if (! config('services.elevenlabs.enabled') || $gender === null) {
            return null;
        }

        $voices = config("services.elevenlabs.voices.{$gender}", []);

        return $voices === [] ? null : $voices[$session->id % count($voices)];
    }

    /**
     * Mint a single-use token the browser can open the text-to-speech
     * WebSocket with, without ever exposing the real API key client-side.
     * It works once and expires after 15 minutes.
     */
    public function createTtsToken(): string
    {
        return $this->request()
            ->post('https://api.elevenlabs.io/v1/single-use-token/tts_websocket')
            ->throw()
            ->json('token');
    }

    private function request(): PendingRequest
    {
        if (! $this->apiKey) {
            throw new RuntimeException('The ElevenLabs API key must be configured.');
        }

        return Http::withHeader('xi-api-key', $this->apiKey);
    }
}
