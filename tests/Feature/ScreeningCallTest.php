<?php

use App\Models\Screening;
use App\Models\ScreeningResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.openai.key' => 'sk-test']);
});

/**
 * Build a minimal, real, valid WAV file so Laravel's content-based
 * `mimes` validation (which sniffs the actual bytes, not the declared
 * upload mime type) recognizes it as audio.
 */
function fakeRecording(string $name = 'call.wav'): UploadedFile
{
    $wav = 'RIFF'.pack('V', 36).'WAVE'
        .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
        .'data'.pack('V', 0);

    return UploadedFile::fake()->createWithContent($name, $wav);
}

test('guests can mint a call session once the screening form is submitted', function () {
    Http::fake([
        'https://api.openai.com/v1/realtime/client_secrets' => Http::response([
            'value' => 'ek_abc123',
            'expires_at' => now()->addMinute()->timestamp,
            'session' => ['id' => 'sess_123'],
        ]),
    ]);

    $screeningResponse = ScreeningResponse::factory()->create();

    $response = $this->post(route('screening.call.session', $screeningResponse));

    $response->assertOk();
    $response->assertJson([
        'value' => 'ek_abc123',
        'session' => ['id' => 'sess_123'],
    ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/realtime/client_secrets');
});

test('a call session uses the screening\'s chosen voice', function () {
    Http::fake([
        'https://api.openai.com/v1/realtime/client_secrets' => Http::response([
            'value' => 'ek_abc123',
            'expires_at' => now()->addMinute()->timestamp,
            'session' => ['id' => 'sess_123'],
        ]),
    ]);

    $screening = Screening::factory()->create(['voice' => 'cedar']);
    $screeningResponse = ScreeningResponse::factory()->for($screening)->create();

    $this->post(route('screening.call.session', $screeningResponse));

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/realtime/client_secrets'
        && $request['session']['audio']['output']['voice'] === 'cedar');
});

test('a call session falls back to the configured default voice when none is set', function () {
    config(['services.openai.realtime_voice' => 'verse']);

    Http::fake([
        'https://api.openai.com/v1/realtime/client_secrets' => Http::response([
            'value' => 'ek_abc123',
            'expires_at' => now()->addMinute()->timestamp,
            'session' => ['id' => 'sess_123'],
        ]),
    ]);

    $screeningResponse = ScreeningResponse::factory()->create();

    $this->post(route('screening.call.session', $screeningResponse));

    Http::assertSent(fn ($request) => $request['session']['audio']['output']['voice'] === 'verse');
});

test('completing a call requires a recording', function () {
    $screeningResponse = ScreeningResponse::factory()->create();

    $response = $this->post(route('screening.call.complete', $screeningResponse), [
        'transcript' => 'Hello there.',
    ]);

    $response->assertSessionHasErrors('recording');
});

test('completing a call stores the transcript and recording on the call log', function () {
    Storage::fake('public');

    $screeningResponse = ScreeningResponse::factory()->create();

    $response = $this->post(route('screening.call.complete', $screeningResponse), [
        'transcript' => 'Hello there.',
        'recording' => fakeRecording(),
    ]);

    $response->assertOk();

    $callLog = $screeningResponse->callLog->fresh();

    expect($callLog->called_at)->not->toBeNull();
    expect($callLog->transcript)->toBe('Hello there.');
    expect($callLog->recording_path)->not->toBeNull();

    Storage::disk('public')->assertExists($callLog->recording_path);
});

test('a call can not be completed twice', function () {
    Storage::fake('public');

    $screeningResponse = ScreeningResponse::factory()->create();

    $this->post(route('screening.call.complete', $screeningResponse), [
        'transcript' => 'First call.',
        'recording' => fakeRecording(),
    ]);

    $response = $this->post(route('screening.call.complete', $screeningResponse), [
        'transcript' => 'Second call.',
        'recording' => fakeRecording(),
    ]);

    $response->assertStatus(409);
});

test('a call session can not be reminted after the call is completed', function () {
    Storage::fake('public');

    $screeningResponse = ScreeningResponse::factory()->create();

    $this->post(route('screening.call.complete', $screeningResponse), [
        'transcript' => 'First call.',
        'recording' => fakeRecording(),
    ]);

    $response = $this->post(route('screening.call.session', $screeningResponse));

    $response->assertStatus(409);
});

test('two candidates using the same screening link get independently scoped calls', function () {
    Storage::fake('public');

    Http::fake([
        'https://api.openai.com/v1/realtime/client_secrets' => Http::response([
            'value' => 'ek_abc123',
            'expires_at' => now()->addMinute()->timestamp,
            'session' => ['id' => 'sess_123'],
        ]),
    ]);

    $screening = Screening::factory()->create();
    $candidateA = ScreeningResponse::factory()->for($screening)->create();
    $candidateB = ScreeningResponse::factory()->for($screening)->create();

    $this->post(route('screening.call.session', $candidateA))->assertOk();

    $this->post(route('screening.call.complete', $candidateB), [
        'transcript' => 'Candidate B call.',
        'recording' => fakeRecording(),
    ])->assertOk();

    expect($candidateA->callLog->fresh()->called_at)->toBeNull();
    expect($candidateB->callLog->fresh()->called_at)->not->toBeNull();
});
