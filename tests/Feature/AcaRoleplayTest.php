<?php

use App\Enums\RoleplayVertical;
use App\Jobs\GradeRoleplayDelivery;
use App\Models\RoleplaySession;
use App\Models\User;
use App\Services\RoleplayDeliveryGrader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.openai.key' => 'sk-test']);
});

/**
 * A minimal valid WAV so the content-sniffing `mimes` rule accepts it.
 */
function acaRoleplayRecording(): UploadedFile
{
    $wav = 'RIFF'.pack('V', 36).'WAVE'
        .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
        .'data'.pack('V', 0);

    return UploadedFile::fake()->createWithContent('call.wav', $wav);
}

test('a verified user can open the ACA roleplay page with the ACA script and only their ACA calls', function () {
    $user = User::factory()->create();
    $acaCall = RoleplaySession::factory()->aca()->for($user)->ended()->create();
    RoleplaySession::factory()->for($user)->ended()->create();

    $response = $this->actingAs($user)->get(route('aca-roleplay.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('roleplay/index')
        ->where('vertical', [
            'id' => 'aca', 'label' => 'ACA', 'handoff' => 'warm',
            'handoffParty' => 'licensed agent', 'handoffIntro' => 'Hi, I have (name) from (state), please take the call.',
        ])
        ->has('content.scriptSections', 6)
        ->has('content.objections', 22)
        ->where('content.objectionRiskLabel', 'Handled badly')
        ->has('recentSessions', 1)
        ->where('recentSessions.0.id', $acaCall->id));
});

test('starting an ACA session saves an ACA persona and keeps its coverage hidden', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('aca-roleplay.sessions.store'), ['level' => 3]);

    $response->assertCreated();
    expect($response->json())->toHaveKeys(['id', 'level', 'lead', 'quirk', 'patience'])
        ->not->toHaveKeys(['age', 'coverage', 'dq_trap', 'persona', 'actual_state']);

    $session = RoleplaySession::sole();
    expect($session->vertical)->toBe(RoleplayVertical::Aca)
        ->and($session->persona['age'])->toBeInt()
        ->and($session->persona['coverage'])->toHaveKeys(['has_marketplace_plan', 'carrier', 'knows_what_they_have', 'receives_ssi']);
});

test('minting an ACA call sends the ACA consumer prompt', function () {
    Http::fake(['https://api.openai.com/v1/realtime/client_secrets' => Http::response(['value' => 'ek_abc123'])]);
    $session = RoleplaySession::factory()->aca()->create();

    $response = $this->actingAs($session->user)->postJson(route('aca-roleplay.call.session', $session));

    $response->assertOk();
    Http::assertSent(fn ($request) => str_contains($request['session']['instructions'], 'Ashley Turner, 34 years old, living in Texas')
        && str_contains($request['session']['instructions'], 'through the Marketplace (Obamacare)')
        && str_contains($request['session']['instructions'], 'licensed agent')
        && str_contains($request['session']['instructions'], 'never add echo, reverb')
        && ! str_contains($request['session']['instructions'], 'Medicare member'));
});

test('a call is only reachable under its own vertical\'s routes', function (string $method, string $route) {
    $medicareCall = RoleplaySession::factory()->ended()->create(['recording_path' => 'roleplay-recordings/call.wav']);

    $response = $this->actingAs($medicareCall->user)->{$method}(route($route, $medicareCall));

    $response->assertNotFound();
})->with([
    ['getJson', 'aca-roleplay.sessions.show'],
    ['get', 'aca-roleplay.sessions.recording'],
    ['postJson', 'aca-roleplay.call.session'],
    ['postJson', 'aca-roleplay.call.complete'],
]);

test('completing an ACA call accepts ACA dispositions, grades it with the ACA rules and reveals the persona', function () {
    Storage::fake('local');
    Queue::fake([GradeRoleplayDelivery::class]);
    $session = RoleplaySession::factory()->aca()->started()->create();

    $response = $this->actingAs($session->user)->post(route('aca-roleplay.call.complete', $session), [
        'transcript' => implode("\n", [
            '[0:04] agent: Hey, am I speaking with Ashley?',
            "[0:06] consumer: I'm at work, call me back.",
        ]),
        'recording' => acaRoleplayRecording(),
        'disposition' => 'call_back',
        'end_reason' => 'agent',
    ]);

    $response->assertOk();
    $response->assertJson([
        'vertical' => 'aca',
        'disposition' => 'call_back',
        'passed' => false,
        'hidden_facts' => ['34 years old.', 'Has a Marketplace plan (not a disqualifier).'],
        'objections' => [['id' => 'what_is_subsidy']],
    ]);
    expect(collect($response->json('checks'))->pluck('key'))->toContain('double_confirm', 'forbidden_words_pitch', 'no_instant_fail')
        ->and($response->json('recording_url'))->toBe(route('aca-roleplay.sessions.recording', $session));
});

test('a disposition from another vertical is rejected', function () {
    $medicareCall = RoleplaySession::factory()->started()->create();

    $response = $this->actingAs($medicareCall->user)->postJson(route('medicare-roleplay.call.complete', $medicareCall), [
        'recording' => acaRoleplayRecording(),
        'disposition' => 'call_back',
        'end_reason' => 'agent',
    ]);

    $response->assertJsonValidationErrors(['disposition']);
});

test('an ACA call\'s qualifying accuracy is scored from the ACA qualifying checks', function () {
    $session = RoleplaySession::factory()->aca()->ended()->make(['score' => ['checks' => [
        ['key' => 'confirm_state', 'label' => '', 'passed' => true],
        ['key' => 'first_coverage_check', 'label' => '', 'passed' => true],
        ['key' => 'age_19', 'label' => '', 'passed' => true],
        ['key' => 'double_confirm', 'label' => '', 'passed' => false],
    ]]]);

    $criteria = collect(app(RoleplayDeliveryGrader::class)->measured($session)['criteria'])->keyBy('key');

    expect($criteria['qualifying_accuracy']['feedback'])->toBe('3 of 4 qualifying checks done.');
});
