<?php

use App\Enums\Outcome;
use App\Jobs\GradeRoleplayDelivery;
use App\Jobs\JudgeRoleplayScene;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\RoleplaySession;
use App\Models\User;
use App\Services\RoleplayConsumerPrompt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A track whose first module ends with a roleplay, followed by a second
 * module, and an agent assigned to it who passed the first module's quiz.
 *
 * @param  array<string, mixed>  $roleplay
 * @return array{0: CourseTrack, 1: CourseModule, 2: CourseModule, 3: User}
 */
function trackWithRoleplayModule(array $roleplay = []): array
{
    $track = CourseTrack::factory()->create();
    $first = CourseModule::factory()->for($track, 'track')->withRoleplay($roleplay)->create(['position' => 0]);
    $second = CourseModule::factory()->for($track, 'track')->create(['position' => 1]);
    $agent = User::factory()->create();
    $agent->courseTracks()->attach($track);
    CourseQuizAttempt::factory()->for($agent)->for($first, 'module')->create();

    return [$track, $first, $second, $agent];
}

/**
 * A minimal valid WAV so the content-sniffing `mimes` rule accepts it.
 */
function moduleRoleplayRecording(): UploadedFile
{
    $wav = 'RIFF'.pack('V', 36).'WAVE'
        .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1).pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
        .'data'.pack('V', 0);

    return UploadedFile::fake()->createWithContent('call.wav', $wav);
}

/**
 * An attempt at the module's scene, already minted, with no objections.
 */
function startedScene(User $agent, CourseModule $module): RoleplaySession
{
    return RoleplaySession::factory()->for($agent)->started()->create([
        'course_module_id' => $module->id,
        'persona' => [...RoleplaySession::factory()->raw()['persona'], 'objections' => [], 'scene' => $module->roleplayScene()],
    ]);
}

/**
 * The opening of the call: the recorded line, Parts A and B and the card,
 * with no work or VA question and no disposition.
 *
 * @return array<string, mixed>
 */
function openingScenePayload(array $overrides = []): array
{
    return [
        'transcript' => implode("\n", [
            '[0:02] consumer: Hello?',
            "[0:05] agent: Hey Dorothy, I'm with America's Health on a recorded line. You DO still have your Medicare Parts A and B, correct?",
            '[0:10] consumer: Yes.',
            '[0:12] agent: And JUST to double confirm, that IS the red, white and blue card, correct?',
            '[0:15] consumer: Okay, what\'s this about?',
        ]),
        'recording' => moduleRoleplayRecording(),
        'end_reason' => 'agent',
        ...$overrides,
    ];
}

/**
 * A Responses API reply with the given judged checks as its structured output.
 *
 * @param  array<string, array{passed: bool, feedback: string}>  $judged
 * @return array<string, mixed>
 */
function sceneJudgement(array $judged): array
{
    return ['output' => [['content' => [['type' => 'output_text', 'text' => json_encode($judged)]]]]];
}

test('the next module stays locked after the quiz until the module roleplay is passed', function () {
    [$track, $first, $second, $agent] = trackWithRoleplayModule();

    $this->actingAs($agent)->get(route('training.modules.show', [$track, $second]))->assertForbidden();

    RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id, 'passed' => false]);
    $this->actingAs($agent)->get(route('training.modules.show', [$track, $second]))->assertForbidden();

    $this->actingAs($agent)
        ->get(route('training.tracks.show', $track))
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.0.progress.is_quiz_passed', true)
            ->where('modules.0.progress.is_passed', false)
            ->where('modules.0.progress.roleplay.status', 'failed')
            ->where('modules.0.progress.roleplay.attempts_count', 1));

    RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id, 'passed' => true]);
    $this->actingAs($agent)->get(route('training.modules.show', [$track, $second]))->assertOk();
});

test('a call from the free roleplay page does not count toward a module', function () {
    [$track, , $second, $agent] = trackWithRoleplayModule();
    RoleplaySession::factory()->for($agent)->ended()->create();

    $this->actingAs($agent)->get(route('training.modules.show', [$track, $second]))->assertForbidden();
});

test('the roleplay page and calls stay locked until the module quiz is passed', function () {
    $track = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($track, 'track')->withRoleplay()->create();
    $agent = User::factory()->create();
    $agent->courseTracks()->attach($track);

    $this->actingAs($agent)->get(route('training.roleplay.show', [$track, $module]))->assertForbidden();
    $this->actingAs($agent)->postJson(route('training.roleplay.store', [$track, $module]))->assertForbidden();

    CourseQuizAttempt::factory()->failed()->for($agent)->for($module, 'module')->create();
    $this->actingAs($agent)->get(route('training.roleplay.show', [$track, $module]))->assertForbidden();

    expect(RoleplaySession::count())->toBe(0);
});

test('the roleplay page shows the scene, what must pass, and only the agent\'s attempts at it', function () {
    [$track, $first, , $agent] = trackWithRoleplayModule([
        'required_checks' => ['recorded_line'],
        'judged' => [['key' => 'calm', 'label' => 'Stayed calm', 'rubric' => 'Pass unless rude.']],
    ]);
    $ownCall = RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id]);
    RoleplaySession::factory()->for($agent)->ended()->create();
    RoleplaySession::factory()->ended()->create(['course_module_id' => $first->id]);

    $this->actingAs($agent)
        ->get(route('training.roleplay.show', [$track, $first]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('training/roleplay')
            ->where('roleplay.title', 'Open the Call')
            ->where('roleplay.start_section', 'opening')
            ->where('roleplay.end_section', 'double_confirm_the_card')
            ->where('roleplay.max_seconds', 60)
            ->where('roleplay.code_call', false)
            ->where('roleplay.pass_criteria', ["Said you're on a recorded line with America's Health", 'Stayed calm'])
            ->where('vertical.id', 'medicare')
            ->has('content.scriptSections')
            ->has('recentSessions', 1)
            ->where('recentSessions.0.id', $ownCall->id)
            ->where('progress.roleplay.status', 'passed'));
});

test('a module without a roleplay has no roleplay page or calls', function () {
    $track = CourseTrack::factory()->create();
    $module = CourseModule::factory()->for($track, 'track')->create();
    $agent = User::factory()->create();
    $agent->courseTracks()->attach($track);
    CourseQuizAttempt::factory()->for($agent)->for($module, 'module')->create();

    $this->actingAs($agent)->get(route('training.roleplay.show', [$track, $module]))->assertNotFound();
    $this->actingAs($agent)->postJson(route('training.roleplay.store', [$track, $module]))->assertNotFound();
});

test('agents can not start a roleplay in a track that is not assigned to them', function () {
    [$track, $first] = trackWithRoleplayModule();

    $this->actingAs(User::factory()->create())
        ->postJson(route('training.roleplay.store', [$track, $first]))
        ->assertForbidden();
});

test('starting a module scene draws the persona from the module\'s setup, keeps the scene on it, and returns only its public part', function () {
    [$track, $first, , $agent] = trackWithRoleplayModule([
        'level' => 2,
        'outcomes' => ['dq' => 100],
        'objections' => ['scam'],
        'objection_count' => [1, 1],
        'dq_traps' => ['va'],
    ]);

    $response = $this->actingAs($agent)->postJson(route('training.roleplay.store', [$track, $first]));

    $response->assertCreated();
    expect($response->json())->toHaveKeys(['id', 'level', 'lead', 'quirk', 'patience'])
        ->not->toHaveKeys(['objections', 'expected_outcome', 'dq_trap', 'persona']);

    $session = RoleplaySession::sole();
    expect($session->user_id)->toBe($agent->id)
        ->and($session->course_module_id)->toBe($first->id)
        ->and($session->level)->toBe(2)
        ->and($session->expected_outcome)->toBe(Outcome::Dq)
        ->and($session->persona['objections'])->toBe(['scam'])
        ->and($session->persona['dq_trap']['id'])->toBe('va')
        ->and($session->persona['scene'])->toMatchArray(['title' => 'Open the Call', 'start_section' => 'opening', 'code_call' => false])
        ->and($session->isScene())->toBeTrue();
});

test('starting module roleplays counts toward the daily call cap', function () {
    config(['services.openai.roleplay_daily_limit' => 1]);
    [$track, $first, , $agent] = trackWithRoleplayModule();

    $this->actingAs($agent)->postJson(route('training.roleplay.store', [$track, $first]))->assertCreated();
    $this->actingAs($agent)->postJson(route('training.roleplay.store', [$track, $first]))->assertTooManyRequests();
});

test('agents who passed a later quiz before a module had its roleplay skip it', function () {
    [$track, $first, $second, $agent] = trackWithRoleplayModule();
    CourseQuizAttempt::factory()->for($agent)->for($second, 'module')->create();

    $this->actingAs($agent)
        ->get(route('training.tracks.show', $track))
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.0.progress.is_passed', true)
            ->where('modules.0.progress.roleplay.status', 'skipped')
            ->where('modules.1.progress.is_unlocked', true));
});

test('admins can open an agent\'s roleplay call but not run it', function () {
    $session = RoleplaySession::factory()->ended()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('medicare-roleplay.sessions.show', $session))->assertOk();
    $this->actingAs($admin)->post(route('medicare-roleplay.call.session', $session))->assertNotFound();
});

test('the dashboard keeps a track in progress until its module roleplays are passed', function () {
    [$track, $first, $second, $agent] = trackWithRoleplayModule();
    $second->update(['position' => 1]);
    $first->update(['position' => 2]);

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('trainingScores.0.status', 'in_progress'));

    CourseQuizAttempt::factory()->for($agent)->for($second, 'module')->create();
    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('trainingScores.0.status', 'in_progress'));

    RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id]);
    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('trainingScores.0.status', 'complete'));
});

test('an uncoded scene is saved without a disposition and graded on its own checks right away', function () {
    Storage::fake('local');
    Queue::fake([GradeRoleplayDelivery::class, JudgeRoleplayScene::class]);
    [, $first, , $agent] = trackWithRoleplayModule(['required_checks' => ['recorded_line', 'double_confirm']]);
    $session = startedScene($agent, $first);

    $response = $this->actingAs($agent)->post(route('medicare-roleplay.call.complete', $session), openingScenePayload());

    $response->assertOk();
    $response->assertJson(['passed' => true, 'disposition' => null, 'scene' => ['title' => 'Open the Call', 'code_call' => false]]);
    expect(array_column($response->json('checks'), 'key'))->toBe(['recorded_line', 'double_confirm', 'patience'])
        ->and($session->refresh()->passed)->toBeTrue()
        ->and($session->delivery_status)->toBeNull();
    Queue::assertNothingPushed();
});

test('a scene fails when one of its required checks fails', function () {
    Storage::fake('local');
    Queue::fake([GradeRoleplayDelivery::class, JudgeRoleplayScene::class]);
    [, $first, , $agent] = trackWithRoleplayModule(['required_checks' => ['recorded_line', 'work_va']]);
    $session = startedScene($agent, $first);

    $this->actingAs($agent)->post(route('medicare-roleplay.call.complete', $session), openingScenePayload())->assertOk();

    expect($session->refresh()->passed)->toBeFalse();
});

test('a scene the trainee codes needs a disposition', function () {
    Storage::fake('local');
    [, $first, , $agent] = trackWithRoleplayModule(['code_call' => true]);
    $session = startedScene($agent, $first);

    $response = $this->actingAs($agent)->postJson(route('medicare-roleplay.call.complete', $session), openingScenePayload());

    $response->assertJsonValidationErrors(['disposition']);
    expect($session->refresh()->ended_at)->toBeNull();
});

test('a scene with judged checks waits for the AI and skips the delivery review', function () {
    Storage::fake('local');
    Queue::fake([GradeRoleplayDelivery::class, JudgeRoleplayScene::class]);
    [, $first, , $agent] = trackWithRoleplayModule([
        'required_checks' => ['recorded_line'],
        'judged' => [['key' => 'calm', 'label' => 'Stayed calm', 'rubric' => 'Pass unless rude.']],
    ]);
    $session = startedScene($agent, $first);

    $response = $this->actingAs($agent)->post(route('medicare-roleplay.call.complete', $session), openingScenePayload());

    $response->assertJson(['passed' => null]);
    Queue::assertPushed(JudgeRoleplayScene::class, fn (JudgeRoleplayScene $job) => $job->roleplaySession->is($session));
    Queue::assertNotPushed(GradeRoleplayDelivery::class);
});

test('the AI judge settles whether the scene passed', function (bool $calmPassed, bool $expectedPassed) {
    config(['services.openai.key' => 'sk-test']);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(sceneJudgement([
        'calm' => ['passed' => $calmPassed, 'feedback' => 'At 0:12 you kept it friendly.'],
    ]))]);
    [, $first, , $agent] = trackWithRoleplayModule(['judged' => [['key' => 'calm', 'label' => 'Stayed calm', 'rubric' => 'Pass unless rude.']]]);
    $session = startedScene($agent, $first);
    $session->update([
        'transcript' => openingScenePayload()['transcript'],
        'ended_at' => now(),
        'score' => ['checks' => [['key' => 'recorded_line', 'label' => 'Recorded line', 'passed' => true]]],
    ]);

    JudgeRoleplayScene::dispatchSync($session);

    $session->refresh();
    expect($session->passed)->toBe($expectedPassed)
        ->and($session->score['checks'][1])->toBe([
            'key' => 'calm',
            'label' => 'Stayed calm',
            'passed' => $calmPassed,
            'feedback' => 'At 0:12 you kept it friendly.',
        ]);
    Http::assertSent(fn ($request) => $request['text']['format']['name'] === 'scene_checks'
        && $request['text']['format']['schema']['required'] === ['calm']
        && str_contains($request['input'][0]['content'], 'Pass unless rude.'));
})->with([
    'the judged check passed' => [true, true],
    'the judged check failed' => [false, false],
]);

test('a judged check the AI leaves out fails', function () {
    config(['services.openai.key' => 'sk-test']);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(sceneJudgement([]))]);
    [, $first, , $agent] = trackWithRoleplayModule(['judged' => [['key' => 'calm', 'label' => 'Stayed calm', 'rubric' => 'Pass unless rude.']]]);
    $session = startedScene($agent, $first);
    $session->update(['ended_at' => now(), 'score' => ['checks' => []]]);

    JudgeRoleplayScene::dispatchSync($session);

    expect($session->refresh()->passed)->toBeFalse();
});

test('when the AI review fails, the scene fails so the trainee can try again', function () {
    [, $first, , $agent] = trackWithRoleplayModule(['judged' => [['key' => 'calm', 'label' => 'Stayed calm', 'rubric' => 'Pass unless rude.']]]);
    $session = startedScene($agent, $first);
    $session->update(['ended_at' => now(), 'score' => ['checks' => [['key' => 'recorded_line', 'label' => 'Recorded line', 'passed' => true]]]]);

    (new JudgeRoleplayScene($session))->failed(new RuntimeException('OpenAI is down.'));

    $session->refresh();
    expect($session->passed)->toBeFalse()
        ->and(array_column($session->score['checks'], 'key'))->toBe(['recorded_line', 'ai_review']);
});

test('the consumer is told a mid-call scene is already underway and to raise objections straight away', function () {
    [, $first, , $agent] = trackWithRoleplayModule(['start_section' => 'medicaid', 'consumer_brief' => 'Push for specifics.']);
    $session = startedScene($agent, $first);
    $session->update(['persona' => [...$session->persona, 'objections' => ['scam']]]);

    $instructions = app(RoleplayConsumerPrompt::class)->instructions($session);

    expect($instructions)->toContain('This is a short scene')
        ->toContain('The line went quiet for a moment')
        ->toContain('Push for specifics.')
        ->toContain('raise your first one right after the caller\'s first line');
});

test('the opening scene starts like a normal call, and full calls get no scene instructions', function () {
    [, $first, , $agent] = trackWithRoleplayModule();
    $prompt = app(RoleplayConsumerPrompt::class);

    expect($prompt->instructions(startedScene($agent, $first)))->toContain('This is a short scene')
        ->not->toContain('The line went quiet')
        ->and($prompt->instructions(RoleplaySession::factory()->create()))->not->toContain('This is a short scene');
});

test('the free roleplay page lists full calls, not training scenes', function () {
    [, $first, , $agent] = trackWithRoleplayModule();
    $fullCall = RoleplaySession::factory()->for($agent)->ended()->create();
    RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id]);

    $this->actingAs($agent)
        ->get(route('medicare-roleplay.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentSessions', 1)
            ->where('recentSessions.0.id', $fullCall->id));
});

test('admins see each module\'s roleplay status, attempts and the checks each one missed', function () {
    [, $first, , $agent] = trackWithRoleplayModule();
    $attempt = RoleplaySession::factory()->for($agent)->ended()->create([
        'course_module_id' => $first->id,
        'passed' => false,
        'score' => ['checks' => [
            ['key' => 'recorded_line', 'label' => 'Recorded line', 'passed' => true],
            ['key' => 'calm', 'label' => 'Stayed calm', 'passed' => false],
        ]],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.training-tracks.show', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tracks.0.passed_modules', 0)
            ->where('tracks.0.modules.0.is_quiz_passed', true)
            ->where('tracks.0.modules.0.roleplay.status', 'failed')
            ->where('tracks.0.modules.0.roleplay.attempts_count', 1)
            ->where('tracks.0.modules.0.roleplay_calls.0.id', $attempt->id)
            ->where('tracks.0.modules.0.roleplay_calls.0.passed', false)
            ->where('tracks.0.modules.0.roleplay_calls.0.missed_checks', ['Stayed calm'])
            ->where('tracks.0.modules.0.roleplay_calls.0.url', route('medicare-roleplay.sessions.show', $attempt))
            ->where('tracks.0.modules.1.roleplay', null)
            ->where('tracks.0.modules.1.roleplay_calls', []));
});

test('an attempt at a scene opens under its training module, leading back to the roleplay page', function () {
    [$track, $first, , $agent] = trackWithRoleplayModule();
    $attempt = RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id]);

    $this->actingAs($agent)
        ->get(route('training.roleplay.attempts.show', [$track, $first, $attempt]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roleplay/sessions/show')
            ->where('session.id', $attempt->id)
            ->has('trail', 5)
            ->where('trail.4', ['title' => 'Roleplay', 'href' => route('training.roleplay.show', [$track, $first])]));
});

test('opening a scene from the roleplay route moves the trainee to its training URL', function () {
    [$track, $first, , $agent] = trackWithRoleplayModule();
    $attempt = RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id]);

    $this->actingAs($agent)
        ->get(route('medicare-roleplay.sessions.show', $attempt))
        ->assertRedirect(route('training.roleplay.attempts.show', [$track, $first, $attempt]));
});

test('an attempt only opens for its owner, under its own module, once it\'s finished', function (string $case) {
    [$track, $first, $second, $agent] = trackWithRoleplayModule();
    $attempt = RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id]);
    $viewer = $agent;
    $module = $first;

    match ($case) {
        'another agent' => $viewer = tap(User::factory()->create(), fn (User $other) => $other->courseTracks()->attach($track)),
        'another module' => $module = $second,
        'unfinished' => $attempt->update(['ended_at' => null]),
    };

    $this->actingAs($viewer)
        ->get(route('training.roleplay.attempts.show', [$track, $module, $attempt]))
        ->assertNotFound();
})->with(['another agent', 'another module', 'unfinished']);

test('a full call leads back to the roleplay page', function () {
    $call = RoleplaySession::factory()->ended()->create();

    $this->actingAs($call->user)
        ->get(route('medicare-roleplay.sessions.show', $call))
        ->assertInertia(fn (Assert $page) => $page->where('trail', null));
});

test('an admin reviewing an agent\'s attempt goes back to the agent\'s training', function () {
    [, $first, , $agent] = trackWithRoleplayModule();
    $attempt = RoleplaySession::factory()->for($agent)->ended()->create(['course_module_id' => $first->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('medicare-roleplay.sessions.show', $attempt))
        ->assertInertia(fn (Assert $page) => $page
            ->where('trail.2', ['title' => $agent->name, 'href' => route('admin.training-tracks.show', $agent)]));
});
