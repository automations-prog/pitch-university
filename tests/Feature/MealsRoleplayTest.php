<?php

use App\Enums\RoleplayVertical;
use App\Jobs\GradeRoleplayDelivery;
use App\Models\RoleplaySession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.openai.key' => 'sk-test', 'services.elevenlabs.enabled' => false]);
});

/**
 * A minimal valid WAV so the content-sniffing `mimes` rule accepts it.
 */
function mealsRoleplayRecording(): UploadedFile
{
    $wav = 'RIFF'.pack('V', 36).'WAVE'
        .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
        .'data'.pack('V', 0);

    return UploadedFile::fake()->createWithContent('call.wav', $wav);
}

test('a verified user can open the Meals roleplay page with the Meals script and only their Meals calls', function () {
    $user = User::factory()->create();
    $mealsCall = RoleplaySession::factory()->meals()->for($user)->ended()->create();
    RoleplaySession::factory()->aca()->for($user)->ended()->create();

    $response = $this->actingAs($user)->get(route('meals-roleplay.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('roleplay/index')
        ->where('vertical.id', 'meals')
        ->where('vertical.handoff', 'warm')
        ->where('vertical.handoffParty', 'screener')
        ->has('content.scriptSections', 6)
        ->has('content.objections', 12)
        ->where('content.objectionRiskLabel', 'Calls lost')
        ->has('recentSessions', 1)
        ->where('recentSessions.0.id', $mealsCall->id));
});

test('starting a Meals session saves a Meals persona and keeps its Medicaid answer hidden', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('meals-roleplay.sessions.store'), ['level' => 3]);

    $response->assertCreated();
    expect($response->json())->toHaveKeys(['id', 'level', 'lead.borough', 'lead.zip', 'quirk', 'patience'])
        ->not->toHaveKeys(['age', 'medicaid', 'says_borough', 'dq_trap', 'persona']);

    $session = RoleplaySession::sole();
    expect($session->vertical)->toBe(RoleplayVertical::Meals)
        ->and($session->persona['medicaid'])->toHaveKeys(['status', 'plan', 'pays_copays']);
});

test('minting a Meals call sends the Meals consumer prompt', function () {
    Http::fake(['https://api.openai.com/v1/realtime/client_secrets' => Http::response(['value' => 'ek_abc123'])]);
    $session = RoleplaySession::factory()->meals()->create();

    $response = $this->actingAs($session->user)->postJson(route('meals-roleplay.call.session', $session));

    $response->assertOk();
    Http::assertSent(fn ($request) => str_contains($request['session']['instructions'], 'Maria Rodriguez, 67 years old, living in Bronx, New York City')
        && str_contains($request['session']['instructions'], 'you have Medicaid')
        && str_contains($request['session']['instructions'], 'end the call. Do not wait politely')
        && ! str_contains($request['session']['instructions'], 'Marketplace'));
});

test('a Meals call is only reachable under the Meals routes', function (string $method, string $route) {
    $acaCall = RoleplaySession::factory()->aca()->ended()->create(['recording_path' => 'roleplay-recordings/call.wav']);

    $response = $this->actingAs($acaCall->user)->{$method}(route($route, $acaCall));

    $response->assertNotFound();
})->with([
    ['getJson', 'meals-roleplay.sessions.show'],
    ['get', 'meals-roleplay.sessions.recording'],
    ['postJson', 'meals-roleplay.call.session'],
    ['postJson', 'meals-roleplay.call.complete'],
]);

test('completing a Meals call grades it with the Meals rules and reveals the persona', function () {
    Storage::fake('local');
    Queue::fake([GradeRoleplayDelivery::class]);
    $session = RoleplaySession::factory()->meals()->started()->create();

    $response = $this->actingAs($session->user)->post(route('meals-roleplay.call.complete', $session), [
        'transcript' => implode("\n", [
            '[0:03] agent: Hey, good afternoon, Maria?',
            "[0:05] consumer: I'm busy, call me back.",
        ]),
        'recording' => mealsRoleplayRecording(),
        'disposition' => 'call_back',
        'end_reason' => 'agent',
    ]);

    $response->assertOk();
    $response->assertJson([
        'vertical' => 'meals',
        'disposition' => 'call_back',
        'passed' => false,
        'hidden_facts' => ['67 years old.', 'Has Medicaid.'],
        'objections' => [['id' => 'what_is_this']],
    ]);
    expect(collect($response->json('checks'))->pluck('key'))->toContain('medicaid_asked', 'came_straight_back', 'medicaid_before_transfer');
});
