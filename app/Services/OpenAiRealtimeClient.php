<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiRealtimeClient
{
    private readonly ?string $apiKey;

    private readonly string $model;

    private readonly string $voice;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?string $voice = null,
    ) {
        $this->apiKey = $apiKey ?? config('services.openai.key');
        $this->model = $model ?? config('services.openai.realtime_model');
        $this->voice = $voice ?? config('services.openai.realtime_voice');
    }

    /**
     * Mint a short-lived ephemeral client secret the browser can use to
     * open a WebRTC connection to the Realtime API, without ever exposing
     * the real API key client-side.
     *
     * @return array<string, mixed>
     */
    public function createEphemeralSession(): array
    {
        return $this->request()
            ->post('https://api.openai.com/v1/realtime/client_secrets', [
                // Default expiry is 60s, which is too tight once you factor
                // in the candidate's mic-permission prompt between minting
                // this and actually using it for the SDP exchange.
                'expires_after' => [
                    'anchor' => 'created_at',
                    'seconds' => 300,
                ],
                'session' => [
                    'type' => 'realtime',
                    'model' => $this->model,
                    'audio' => [
                        'output' => [
                            'voice' => $this->voice,
                        ],
                    ],
                ],
            ])
            ->throw()
            ->json() ?? [];
    }

    private function request(): PendingRequest
    {
        if (! $this->apiKey) {
            throw new RuntimeException('The OpenAI API key must be configured.');
        }

        return Http::withToken($this->apiKey);
    }
}
