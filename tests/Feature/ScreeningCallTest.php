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

test('a call session can not be started before the screening form is submitted', function () {
    $screening = Screening::factory()->create();

    $response = $this->post(route('screening.call.session', $screening));

    $response->assertStatus(409);
});

test('guests can mint a call session once the screening form is submitted', function () {
    Http::fake([
        'https://api.openai.com/v1/realtime/client_secrets' => Http::response([
            'value' => 'ek_abc123',
            'expires_at' => now()->addMinute()->timestamp,
            'session' => ['id' => 'sess_123'],
        ]),
    ]);

    $screening = Screening::factory()->has(ScreeningResponse::factory(), 'responses')->create();

    $response = $this->post(route('screening.call.session', $screening));

    $response->assertOk();
    $response->assertJson([
        'value' => 'ek_abc123',
        'session' => ['id' => 'sess_123'],
    ]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/realtime/client_secrets');
});

test('completing a call requires a recording', function () {
    $screening = Screening::factory()->has(ScreeningResponse::factory(), 'responses')->create();

    $response = $this->post(route('screening.call.complete', $screening), [
        'transcript' => 'Hello there.',
    ]);

    $response->assertSessionHasErrors('recording');
});

test('completing a call stores the transcript and recording on the call log', function () {
    Storage::fake('public');

    $screening = Screening::factory()->has(ScreeningResponse::factory(), 'responses')->create();
    $screeningResponse = $screening->responses()->first();

    $response = $this->post(route('screening.call.complete', $screening), [
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

    $screening = Screening::factory()->has(ScreeningResponse::factory(), 'responses')->create();

    $this->post(route('screening.call.complete', $screening), [
        'transcript' => 'First call.',
        'recording' => fakeRecording(),
    ]);

    $response = $this->post(route('screening.call.complete', $screening), [
        'transcript' => 'Second call.',
        'recording' => fakeRecording(),
    ]);

    $response->assertStatus(409);
});
