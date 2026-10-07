<?php

use App\Enums\RealtimeVoice;
use App\Jobs\GradeRoleplayDelivery;
use App\Models\RoleplaySession;
use App\Models\User;
use App\Services\RoleplayConsumerPrompt;
use App\Services\RoleplayGrader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

const CLIENT_SECRETS_URL = 'https://api.openai.com/v1/realtime/client_secrets';

beforeEach(function () {
    config(['services.openai.key' => 'sk-test']);
});

/**
 * A minimal valid WAV so the content-sniffing `mimes` rule accepts it.
 */
function fakeRoleplayRecording(): UploadedFile
{
    $wav = 'RIFF'.pack('V', 36).'WAVE'
        .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
        .'data'.pack('V', 0);

    return UploadedFile::fake()->createWithContent('call.wav', $wav);
}

function fakeClientSecrets(): void
{
    Http::fake([
        CLIENT_SECRETS_URL => Http::response(['value' => 'ek_abc123', 'session' => ['id' => 'sess_123']]),
    ]);
}

/**
 * Transfer clicked at 0:40, fluff, the specialist joins, the lead answers,
 * and the trainee leaves the conference.
 *
 * @return list<array<string, mixed>>
 */
function completedTransferEvents(): array
{
    return [
        ['type' => 'transfer_clicked', 'at' => 40000],
        ['type' => 'agent_speech', 'start' => 42000, 'end' => 50000],
        ['type' => 'specialist_joined', 'at' => 70000],
        ['type' => 'lead_answered_specialist', 'at' => 74000],
        ['type' => 'transfer_completed', 'at' => 76000],
    ];
}

/**
 * @return array<string, mixed>
 */
function completePayload(array $overrides = []): array
{
    return [
        'transcript' => implode("\n", [
            '[0:02] consumer: Hello?',
            "[0:05] agent: Hey Dorothy, I'm with America's Health on a recorded line. You DO still have your Medicare Parts A and B, correct?",
            '[0:10] consumer: Yes.',
            '[0:12] agent: And JUST to double confirm, that IS the red, white and blue card, correct?',
            '[0:15] consumer: Uh huh.',
            '[0:20] agent: It DOES look like you may be ENTITLED to some additional food benefits. I DO still have you out in Florida, with the zip code 33511, correct?',
            '[0:25] consumer: Right.',
            '[0:28] agent: Do you have insurance through your work or the VA?',
            '[0:30] consumer: No.',
            '[0:33] agent: A Medicare specialist is coming on the line now. Give them JUST a few minutes to go over them with you, okay?',
            '[0:38] consumer: Okay, sure.',
        ]),
        'recording' => fakeRoleplayRecording(),
        'disposition' => 'transfer',
        'end_reason' => 'agent',
        'transfer_clicked_at' => 40,
        'events' => json_encode([
            ['type' => 'agent_speech', 'start' => 5000, 'end' => 9000],
            ['type' => 'unknown', 'start' => 1],
            ...completedTransferEvents(),
        ]),
        ...$overrides,
    ];
}

test('guests are redirected to login on every roleplay endpoint', function (string $method, string $route) {
    $session = RoleplaySession::factory()->create();

    $response = $this->{$method}(route($route, $route === 'roleplay.sessions.store' ? [] : $session));

    $response->assertRedirect(route('login'));
})->with([
    ['post', 'roleplay.sessions.store'],
    ['post', 'roleplay.call.session'],
    ['post', 'roleplay.call.complete'],
    ['get', 'roleplay.sessions.recording'],
    ['get', 'roleplay.sessions.show'],
]);

test('starting a session saves a persona and returns only its public part', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('roleplay.sessions.store'), ['level' => 3]);

    $response->assertCreated();
    expect($response->json())->toHaveKeys(['id', 'level', 'lead', 'quirk', 'patience'])
        ->not->toHaveKeys(['objections', 'expected_outcome', 'outcome', 'dq_trap', 'persona']);

    $session = RoleplaySession::sole();
    expect($session->user_id)->toBe($user->id)
        ->and($session->level)->toBe(3)
        ->and($session->persona['patience'])->toBe(7)
        ->and($session->voice?->gender())->toBe($session->persona['lead']['gender']);
});

test('starting a session requires a level from 1 to 5', function (mixed $level) {
    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('roleplay.sessions.store'), ['level' => $level]);

    $response->assertJsonValidationErrors(['level']);
})->with([0, 6, 'hard']);

test('starting sessions past the daily cap is rejected', function () {
    config(['services.openai.roleplay_daily_limit' => 2]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('roleplay.sessions.store'), ['level' => 1])->assertCreated();
    $this->actingAs($user)->postJson(route('roleplay.sessions.store'), ['level' => 1])->assertCreated();
    $response = $this->actingAs($user)->postJson(route('roleplay.sessions.store'), ['level' => 1]);

    $response->assertTooManyRequests();
    expect(RoleplaySession::count())->toBe(2);
});

test('minting a call session sends the persona prompt, tools and voice to OpenAI', function () {
    fakeClientSecrets();
    $session = RoleplaySession::factory()->disqualified()->create(['level' => 4]);

    $response = $this->actingAs($session->user)->postJson(route('roleplay.call.session', $session));

    $response->assertOk();
    $response->assertJson(['value' => 'ek_abc123']);
    expect($session->fresh()->started_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === CLIENT_SECRETS_URL
        && $request['session']['audio']['output']['voice'] === 'coral'
        && str_contains($request['session']['instructions'], 'Dorothy Miller')
        && str_contains($request['session']['instructions'], 'You are a woman.')
        && str_contains($request['session']['instructions'], 'Has VA health care')
        && collect($request['session']['tools'])->pluck('name')->all() === ['patience_changed', 'objection_raised', 'objection_resolved', 'hang_up']
        && array_key_exists('turn_detection', $request['session']['audio']['input'])
        && $request['session']['audio']['input']['turn_detection'] === null
        && $request['session']['audio']['input']['noise_reduction'] === ['type' => 'near_field']);
});

test('the consumer raises "I already got it" at the Parts A and B question with any carrier line', function () {
    $session = RoleplaySession::factory()->create([
        'level' => 1,
        'persona' => [...RoleplaySession::factory()->raw()['persona'], 'objections' => ['already_have_it', 'busy']],
    ]);

    $instructions = app(RoleplayConsumerPrompt::class)->instructions($session);

    expect($instructions)
        ->toContain('Raise this one right when the caller first asks if you still have Medicare Parts A and B.')
        ->toContain('"I get my OTC card with Humana." / "I already signed up with Aetna for 2026."')
        ->toContain('"I get the Flex Card with Humana."')
        ->toContain('- `busy`: I\'m busy / call me back. Say something like: "');
});

test('minting uses a voice of the lead\'s gender when the session has none', function () {
    config(['services.openai.realtime_voice' => 'verse']);
    fakeClientSecrets();
    $session = RoleplaySession::factory()->create(['voice' => null]);

    $this->actingAs($session->user)->postJson(route('roleplay.call.session', $session))->assertOk();

    Http::assertSent(fn ($request) => $request['session']['audio']['output']['voice'] === 'marin'
        && str_contains($request['session']['instructions'], 'You are a woman.'));
});

test('minting replaces a voice that could pass for either gender', function (RealtimeVoice $voice) {
    fakeClientSecrets();
    $session = RoleplaySession::factory()->create(['voice' => $voice]);

    $this->actingAs($session->user)->postJson(route('roleplay.call.session', $session))->assertOk();

    Http::assertSent(fn ($request) => $request['session']['audio']['output']['voice'] === 'marin');
})->with([RealtimeVoice::Sage, RealtimeVoice::Alloy]);

test('minting never gives a lead a voice of the other gender', function () {
    fakeClientSecrets();
    $session = RoleplaySession::factory()->create([
        'persona' => [
            'lead' => ['name' => 'Harold Jenkins', 'state' => 'Ohio', 'zip' => '43204', 'gender' => 'male'],
            'objections' => ['busy'],
            'quirk' => null,
            'patience' => 8,
            'dq_trap' => null,
        ],
        'voice' => RealtimeVoice::Coral,
    ]);

    $this->actingAs($session->user)->postJson(route('roleplay.call.session', $session))->assertOk();

    Http::assertSent(fn ($request) => $request['session']['audio']['output']['voice'] === 'cedar'
        && str_contains($request['session']['instructions'], 'You are a man.'));
});

test('a session can only be minted once', function () {
    fakeClientSecrets();
    $session = RoleplaySession::factory()->started()->create();

    $response = $this->actingAs($session->user)->postJson(route('roleplay.call.session', $session));

    $response->assertConflict();
    Http::assertNothingSent();
});

test('a user cannot mint, complete or listen to another user\'s session', function (string $method, string $route) {
    Storage::fake('local');
    fakeClientSecrets();
    $session = RoleplaySession::factory()->started()->create(['recording_path' => 'roleplay-recordings/call.wav']);

    $response = $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $session), $route === 'roleplay.call.complete' ? completePayload() : []);

    $response->assertNotFound();
    Http::assertNothingSent();
})->with([
    ['postJson', 'roleplay.call.session'],
    ['postJson', 'roleplay.call.complete'],
    ['getJson', 'roleplay.sessions.recording'],
    ['getJson', 'roleplay.sessions.show'],
]);

test('completing requires a recording, a known disposition and an end reason', function () {
    $session = RoleplaySession::factory()->started()->create();

    $response = $this->actingAs($session->user)->postJson(route('roleplay.call.complete', $session), [
        'disposition' => 'transferred-ish',
        'end_reason' => 'bored',
    ]);

    $response->assertJsonValidationErrors(['recording', 'disposition', 'end_reason']);
});

test('completing stores the call, grades it and reveals the persona', function () {
    Storage::fake('local');
    Queue::fake([GradeRoleplayDelivery::class]);
    $session = RoleplaySession::factory()->started()->create();

    $response = $this->actingAs($session->user)->post(route('roleplay.call.complete', $session), completePayload());

    $response->assertOk();
    $response->assertJson([
        'id' => $session->id,
        'passed' => true,
        'expected_outcome' => 'transfer',
        'correct_disposition' => 'transfer',
        'correct_reason' => null,
        'disposition' => 'transfer',
    ]);
    expect($response->json('objections.0.id'))->toBe('busy');

    $session->refresh();
    expect($session->ended_at)->not->toBeNull()
        ->and($session->transcript)->toContain('recorded line')
        ->and($session->passed)->toBeTrue()
        ->and($session->score['transfer_clicked_at'])->toBe(40)
        ->and($session->events)->toBe([
            ['type' => 'agent_speech', 'start' => 5000, 'end' => 9000],
            ...completedTransferEvents(),
        ])
        ->and($session->delivery_status)->toBe('pending');
    Storage::disk('local')->assertExists($session->recording_path);
    Queue::assertPushed(GradeRoleplayDelivery::class, fn (GradeRoleplayDelivery $job) => $job->roleplaySession->is($session));
});

test('completing stores a CRLF transcript with plain line breaks so every line parses', function () {
    Storage::fake('local');
    Queue::fake([GradeRoleplayDelivery::class]);
    $session = RoleplaySession::factory()->started()->create();
    $transcript = str_replace("\n", "\r\n", completePayload()['transcript']);

    $response = $this->actingAs($session->user)
        ->post(route('roleplay.call.complete', $session), completePayload(['transcript' => $transcript]));

    $response->assertOk();
    $response->assertJson(['passed' => true]);

    $session->refresh();
    expect($session->transcript)->not->toContain("\r")
        ->and(app(RoleplayGrader::class)->parseTranscript($session->transcript))->toHaveCount(11);
});

test('completing rejects an event log that isn\'t JSON', function () {
    $session = RoleplaySession::factory()->started()->create();

    $response = $this->actingAs($session->user)
        ->postJson(route('roleplay.call.complete', $session), completePayload(['events' => 'not json']));

    $response->assertJsonValidationErrors(['events']);
});

test('the owner can open a finished call, as a page or as JSON for polling', function () {
    $session = RoleplaySession::factory()->ended()->create(['delivery_status' => 'done', 'delivery' => ['criteria' => [], 'metrics' => [], 'overall' => 4.2]]);

    $this->actingAs($session->user)->get(route('roleplay.sessions.show', $session))
        ->assertInertia(fn ($page) => $page->component('roleplay/sessions/show')->where('session.delivery.overall', 4.2));

    $this->actingAs($session->user)->getJson(route('roleplay.sessions.show', $session))
        ->assertOk()
        ->assertJsonPath('delivery_status', 'done');
});

test('a call still in progress has no results page', function () {
    $session = RoleplaySession::factory()->started()->create();

    $this->actingAs($session->user)->getJson(route('roleplay.sessions.show', $session))->assertNotFound();
});

test('a session cannot be completed twice or before it was minted', function (array $attributes) {
    Storage::fake('local');
    $session = RoleplaySession::factory()->create($attributes);

    $response = $this->actingAs($session->user)->post(route('roleplay.call.complete', $session), completePayload());

    $response->assertConflict();
})->with([
    'already completed' => [['started_at' => now(), 'ended_at' => now()]],
    'never minted' => [['started_at' => null]],
]);

test('the owner can play back their recording', function () {
    Storage::fake('local');
    Storage::disk('local')->put('roleplay-recordings/call.wav', 'audio');
    $session = RoleplaySession::factory()->ended()->create(['recording_path' => 'roleplay-recordings/call.wav']);

    $response = $this->actingAs($session->user)->get(route('roleplay.sessions.recording', $session));

    $response->assertOk();
    expect($response->streamedContent())->toBe('audio');
});
