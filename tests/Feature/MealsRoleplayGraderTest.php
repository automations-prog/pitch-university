<?php

use App\Models\RoleplaySession;
use App\Services\MealsRoleplayGrader;

/**
 * A clean warm handoff: transferred at 0:40, fluffed while it rang, the
 * screener joined at 1:10, the trainee introduced the lead, the lead
 * greeted the screener, then the trainee left.
 *
 * @return list<array<string, mixed>>
 */
function mealsHandoffEvents(): array
{
    return [
        ['type' => 'transfer_clicked', 'at' => 40_000],
        ['type' => 'agent_speech', 'start' => 42_000, 'end' => 50_000],
        ['type' => 'specialist_joined', 'at' => 70_000],
        ['type' => 'lead_answered_specialist', 'at' => 76_000],
        ['type' => 'transfer_completed', 'at' => 78_000],
    ];
}

/**
 * The Meals script read in order to Maria in the Bronx, who says okay to
 * the transfer.
 *
 * @return array<string, string>
 */
function mealsCleanTranscript(): array
{
    return [
        'hello' => '[0:02] consumer: Hello?',
        'person' => '[0:03] agent: Hey, good afternoon, Maria?',
        'person_yes' => '[0:05] consumer: Yes?',
        'intro' => "[0:06] agent: Hey Maria, this is Sam, I'm with America's Health on a recorded line. We were just giving you a quick call today about the Medicaid meal service for you and your family. I just need to confirm you ARE still receiving Medicaid, correct?",
        'medicaid_answer' => '[0:14] consumer: Yes.',
        'borough' => "[0:15] agent: Okay perfect, and you're still in the Bronx, with the zip code 10453, correct?",
        'borough_answer' => '[0:19] consumer: Yes.',
        'double_confirm' => '[0:20] agent: Okay LAST question here, JUST to double confirm, you ARE receiving Medicaid and you ARE still in the Bronx, correct?',
        'double_answer' => '[0:25] consumer: Yes.',
        'ask' => "[0:27] agent: Perfect, that's everything I need. The screener is coming on the line now to help you set that up, once that's finished, they'll have you on your way, okay?",
        'consent' => '[0:33] consumer: Okay.',
        'fluff' => '[0:42] agent: How is your day going so far?',
        'fluff_answer' => '[0:46] consumer: Not bad.',
        'handoff' => '[1:12] agent: Hey agent, I have Maria in the Bronx, the zip code for them is 10453. Can you take the call from here?',
        'greeting' => '[1:15] consumer: Hi, yes, this is Maria.',
    ];
}

/**
 * Grades a Meals call. Transcript entries can be replaced or dropped
 * (`null`) by key, and the persona overridden.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, string|null>  $transcript
 * @param  array<string, mixed>  $persona
 * @param  list<array<string, mixed>>|null  $events
 * @return array{passed: bool, checks: array<string, bool|null>, correct: string, reason: string|null}
 */
function mealsGradedCall(array $attributes = [], array $transcript = [], array $persona = [], ?int $transferClickedAt = 40, ?array $events = null): array
{
    $session = RoleplaySession::factory()->meals()->make([
        'disposition' => 'transfer',
        'end_reason' => 'agent',
        'events' => $events ?? mealsHandoffEvents(),
        'transcript' => implode("\n", array_filter([...mealsCleanTranscript(), ...$transcript])),
        ...$attributes,
    ]);
    $session->persona = [...$session->persona, ...$persona];

    $result = app(MealsRoleplayGrader::class)->grade($session, $transferClickedAt);

    return [
        'passed' => $result['passed'],
        'checks' => array_column($result['checks'], 'passed', 'key'),
        'correct' => $result['correct_disposition'],
        'reason' => $result['correct_reason'],
    ];
}

/**
 * The attributes of a Meals persona with the given disqualifier.
 *
 * @param  array<string, mixed>  $persona
 * @return array{expected_outcome: string, persona: array<string, mixed>}
 */
function mealsDisqualified(string $trapId, array $persona = []): array
{
    return [
        'expected_outcome' => 'dq',
        'persona' => [
            ...RoleplaySession::factory()->meals()->make()->persona,
            'dq_trap' => ['id' => $trapId, 'hidden_truth' => 'A disqualifier.'],
            ...$persona,
        ],
    ];
}

test('a compliant Meals call with a warm handoff passes every check', function () {
    $result = mealsGradedCall();

    expect($result['passed'])->toBeTrue()
        ->and(array_filter($result['checks'], fn (?bool $check) => $check === false))->toBe([]);
});

test('a transfer where the agent never says Medicaid fails as fraud, whatever else went well', function () {
    $result = mealsGradedCall(transcript: [
        'intro' => "[0:06] agent: Hey Maria, this is Sam, I'm with America's Health on a recorded line, calling about the meal service.",
        'double_confirm' => '[0:20] agent: Okay LAST question here, you ARE still in the Bronx, correct?',
    ]);

    expect($result['checks']['medicaid_before_transfer'])->toBeFalse()
        ->and($result['passed'])->toBeFalse()
        ->and($result['reason'])->toContain('fraud');
});

test('saying Medicaid anywhere clears the fraud auto-fail', function () {
    $result = mealsGradedCall(transcript: [
        'intro' => "[0:06] agent: Hey Maria, this is Sam, I'm with America's Health on a recorded line, calling about the Medicaid meal service.",
    ]);

    expect($result['checks']['medicaid_before_transfer'])->toBeTrue()
        ->and($result['reason'])->toBeNull();
});

test('approved script wording is never an instant fail', function (string $line) {
    $result = mealsGradedCall(transcript: ['fluff' => "[0:42] agent: {$line}"]);

    expect($result['checks']['no_instant_fail'])->toBeTrue();
})->with([
    "You're ALREADY approved for up to three meals at NO cost and this is INCLUDED in your Medicaid.",
    "You're already preapproved for up to 3 meals a day, delivered to your doorstep.",
    "There is no cost to you and there is no change to your coverage, you don't have to pay anything.",
    'When you go to the doctor, do you have to pay anything, or is it usually free?',
]);

test('telling the consumer it costs money or that their coverage changes is an instant fail', function (string $line) {
    $result = mealsGradedCall(transcript: ['fluff' => "[0:42] agent: {$line}"]);

    expect($result['checks']['no_instant_fail'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
})->with([
    'It costs $5 a meal.',
    "You'll have to pay a small fee.",
    'Your coverage will change a little.',
    "I'm calling from the state.",
    'We guarantee the meals.',
    "What's your Medicaid number?",
]);

test('Long Island City is Queens and qualifies', function () {
    $result = mealsGradedCall(
        transcript: [
            'borough' => "[0:15] agent: Okay perfect, and you're still in Queens, with the zip code 11101, correct?",
            'double_confirm' => '[0:20] agent: Okay LAST question here, JUST to double confirm, you ARE receiving Medicaid and you ARE still in Queens, correct?',
            'handoff' => '[1:12] agent: Hey agent, I have Maria in Queens, the zip code for them is 11101.',
        ],
        persona: [
            'lead' => ['name' => 'Maria Rodriguez', 'state' => 'New York', 'borough' => 'Queens', 'zip' => '11101', 'gender' => 'female'],
            'says_borough' => 'Long Island City',
        ],
    );

    expect($result['passed'])->toBeTrue();
});

test('transferring a consumer from Long Island fails, and reading the DQ line passes', function () {
    $outside = mealsDisqualified('outside_nyc', ['says_borough' => 'Long Island']);

    $transferred = mealsGradedCall($outside);

    expect($transferred['checks']['no_dq_transfer'])->toBeFalse()
        ->and($transferred['passed'])->toBeFalse();

    $disqualified = mealsGradedCall(['disposition' => 'dq', ...$outside], transcript: [
        'borough_answer' => "[0:19] consumer: No, I'm out on Long Island now.",
        'double_confirm' => '[0:20] agent: Oh there must have been a mistake, thank you for your time, have a great day.',
        'double_answer' => null, 'ask' => null, 'consent' => null, 'fluff' => null,
        'fluff_answer' => null, 'handoff' => null, 'greeting' => null,
    ], transferClickedAt: null, events: []);

    expect($disqualified['checks']['dq_line'])->toBeTrue()
        ->and($disqualified['passed'])->toBeTrue();
});

test('the copay question is required when the consumer does not know if they have Medicaid', function () {
    $dontKnow = ['medicaid' => ['status' => 'dont_know', 'plan' => null, 'pays_copays' => false]];

    $skipped = mealsGradedCall(persona: $dontKnow, transcript: ['medicaid_answer' => "[0:14] consumer: I don't know."]);
    $asked = mealsGradedCall(persona: $dontKnow, transcript: [
        'medicaid_answer' => "[0:14] consumer: I don't know. Uh, when you go to the doctor... no, it's free.",
        'borough' => "[0:15] agent: Okay, well when you go to the doctor, do you have to pay anything, or is it usually free? Okay perfect, and you're still in the Bronx, with the zip code 10453, correct?",
    ]);

    expect($skipped['checks']['copay_fallback'])->toBeFalse()
        ->and($asked['checks']['copay_fallback'])->toBeTrue()
        ->and(mealsGradedCall()['checks']['copay_fallback'])->toBeNull();
});

test('transferring without the consumer saying okay fails', function () {
    $result = mealsGradedCall(transcript: ['consent' => null]);

    expect($result['checks']['verbal_consent'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
});

test('rolling the double confirm into the first ask is not a double confirmation', function () {
    $result = mealsGradedCall(transcript: [
        'intro' => "[0:06] agent: Hey Maria, this is Sam, I'm with America's Health on a recorded line about the Medicaid meal service. You are receiving Medicaid and still in the Bronx, correct?",
        'double_confirm' => null,
        'double_answer' => null,
    ]);

    expect($result['checks']['double_confirm'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
});

test('a conditional request or swearing is not a do-not-call request', function (string $line) {
    $result = mealsGradedCall(transcript: ['medicaid_answer' => "[0:14] consumer: {$line} Yes."]);

    expect($result['correct'])->toBe('transfer')
        ->and($result['checks']['dnc_respected'])->toBeNull();
})->with([
    "If this is a scam don't call me again.",
    'What the hell is this, damn it.',
    'If this is a scam don’t call me again.',
]);

test('a real do-not-call request must get the removal line, not a rebuttal', function (string $reply, bool $respected) {
    $result = mealsGradedCall(['expected_outcome' => 'dnc', 'disposition' => 'dnc'], transcript: [
        'medicaid_answer' => '[0:14] consumer: Stop calling me and take me off your list.',
        'borough' => "[0:15] agent: {$reply}",
        'borough_answer' => null, 'double_confirm' => null, 'double_answer' => null, 'ask' => null,
        'consent' => null, 'fluff' => null, 'fluff_answer' => null, 'handoff' => null, 'greeting' => null,
    ], transferClickedAt: null, events: []);

    expect($result['correct'])->toBe('dnc')
        ->and($result['checks']['dnc_respected'])->toBe($respected);
})->with([
    'removal line' => ["Understood, we'll remove you from our list. Have a great day.", true],
    'rebuttal' => ['I understand, but groceries are NOT getting any cheaper.', false],
]);

test('pausing after a brush-off fails the came-straight-back check', function (int $replyAt, bool $passed) {
    $events = [
        ['type' => 'consumer_speech', 'start' => 10_000, 'end' => 11_500],
        ['type' => 'objection_raised', 'id' => 'not_interested', 'at' => 11_000],
        ['type' => 'agent_speech', 'start' => $replyAt, 'end' => $replyAt + 6_000],
        ...mealsHandoffEvents(),
    ];

    expect(mealsGradedCall(events: $events)['checks']['came_straight_back'])->toBe($passed);
})->with([
    'straight back' => [12_200, true],
    'after a pause' => [15_000, false],
]);
