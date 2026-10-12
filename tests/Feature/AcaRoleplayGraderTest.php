<?php

use App\Models\RoleplaySession;
use App\Services\AcaRoleplayGrader;

/**
 * A clean warm handoff: conferenced at 0:40, fluffed while it rang, the
 * licensed agent joined at 1:10, the trainee introduced the lead, the lead
 * greeted the agent, then the trainee left.
 *
 * @return list<array<string, mixed>>
 */
function acaHandoffEvents(): array
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
 * The ACA script read in order to Ashley in Texas, who says okay to the
 * transfer.
 *
 * @return array<string, string>
 */
function acaCleanTranscript(): array
{
    return [
        'hello' => '[0:02] consumer: Hello?',
        'person' => '[0:04] agent: Hey, am I speaking with Ashley?',
        'person_yes' => '[0:06] consumer: Yeah.',
        'intro' => "[0:08] agent: This is Sam with America's Health on a recorded line. I am calling because it shows you didn't get your subsidy allowances from the ACA program.",
        'intro_ok' => '[0:14] consumer: Okay.',
        'state' => '[0:15] agent: Are you still living in Texas to receive it?',
        'state_yes' => '[0:17] consumer: Yes.',
        'first_check' => '[0:18] agent: And everything is still up to date, you have no Medicare, no Medicaid and no work insurance, correct?',
        'first_answer' => '[0:22] consumer: Correct.',
        'double_confirm' => '[0:24] agent: You did say no Medicare, Medicaid or work insurance, and confirming you are over the age of 19?',
        'double_answer' => '[0:28] consumer: Yes.',
        'ask' => "[0:30] agent: Okay, so you verified everything. I'll quickly get the senior agent on the line and see what assistance you may receive, okay?",
        'consent' => '[0:35] consumer: Okay.',
        'fluff' => "[0:42] agent: I'm sorry, I forgot to ask, have you been struggling this year?",
        'fluff_answer' => '[0:46] consumer: A little bit.',
        'handoff' => '[1:12] agent: Hi, I have Ashley from Texas, please take the call.',
        'greeting' => '[1:15] consumer: Hi, yes, this is Ashley.',
    ];
}

/**
 * Grades an ACA call. Transcript entries can be replaced or dropped
 * (`null`) by key, or the whole transcript given as a list.
 *
 * @return array{passed: bool, checks: array<string, bool|null>, correct: string}
 */
function acaGradedCall(array $attributes = [], array $transcript = [], ?int $transferClickedAt = 40, ?array $events = null): array
{
    $lines = array_is_list($transcript) && $transcript !== []
        ? $transcript
        : array_filter([...acaCleanTranscript(), ...$transcript]);

    $session = RoleplaySession::factory()->aca()->make([
        'disposition' => 'transfer',
        'end_reason' => 'agent',
        'events' => $events ?? acaHandoffEvents(),
        'transcript' => implode("\n", $lines),
        ...$attributes,
    ]);

    $result = app(AcaRoleplayGrader::class)->grade($session, $transferClickedAt);

    return [
        'passed' => $result['passed'],
        'checks' => array_column($result['checks'], 'passed', 'key'),
        'correct' => $result['correct_disposition'],
    ];
}

/**
 * An ACA persona with the given disqualifier.
 *
 * @return array<string, mixed>
 */
function acaDisqualifiedAttributes(string $trapId = 'medicaid', string $hiddenTruth = 'Has Medicaid.'): array
{
    $persona = RoleplaySession::factory()->aca()->make()->persona;

    return [
        'expected_outcome' => 'dq',
        'persona' => [...$persona, 'dq_trap' => ['id' => $trapId, 'hidden_truth' => $hiddenTruth]],
    ];
}

test('a compliant ACA call with a warm handoff passes every check', function () {
    $result = acaGradedCall();

    expect($result['passed'])->toBeTrue()
        ->and(array_filter($result['checks'], fn (?bool $check) => $check === false))->toBe([]);
});

test('rolling every confirmation into one sentence is not a double confirmation', function () {
    $result = acaGradedCall(transcript: [
        'first_check' => '[0:18] agent: No Medicare, Medicaid or work insurance and over 19, correct?',
        'double_confirm' => null,
        'double_answer' => null,
    ]);

    expect($result['checks']['first_coverage_check'])->toBeTrue()
        ->and($result['checks']['double_confirm'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
});

test('asking the coverage question twice without waiting for an answer is not a double confirmation', function () {
    $result = acaGradedCall(transcript: ['first_answer' => null]);

    expect($result['checks']['double_confirm'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
});

test('conferencing without hearing okay fails the call', function () {
    $result = acaGradedCall(transcript: ['consent' => '[0:35] consumer: No, not right now.']);

    expect($result['checks']['verbal_consent'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
});

test('a handoff that does not name the state fails that check', function () {
    $result = acaGradedCall(transcript: ['handoff' => '[1:12] agent: Hi, I have Ashley on the line, please take the call.']);

    expect($result['checks']['handoff_names_state'])->toBeFalse();
});

test('a disqualified consumer who gets conferenced fails, however smooth the call', function () {
    $result = acaGradedCall(acaDisqualifiedAttributes());

    expect($result['correct'])->toBe('dq')
        ->and($result['checks']['no_dq_transfer'])->toBeFalse()
        ->and($result['checks']['disposition'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
});

test('reading the DQ line to a disqualified consumer and coding DQ passes', function () {
    $result = acaGradedCall(
        ['disposition' => 'dq', ...acaDisqualifiedAttributes()],
        [
            '[0:04] agent: Hey, am I speaking with Ashley?',
            '[0:06] consumer: Yeah.',
            "[0:08] agent: This is Sam with America's Health on a recorded line, calling about your subsidy through the Affordable Care Act.",
            '[0:15] agent: Are you still living in Texas? And you have no Medicare, no Medicaid and no work insurance, correct?',
            '[0:20] consumer: I have Medicaid.',
            '[0:23] agent: Unfortunately at this time you would not be qualified. Have a wonderful day!',
        ],
        transferClickedAt: null,
        events: [],
    );

    expect($result['passed'])->toBeTrue()
        ->and($result['checks']['dq_line'])->toBeTrue()
        ->and($result['checks']['no_dq_transfer'])->toBeTrue()
        ->and($result['checks']['no_promises'])->toBeTrue()
        ->and($result['checks']['verbal_consent'])->toBeNull()
        ->and($result['checks']['double_confirm'])->toBeNull();
});

test('conditional lines and swearing are not do-not-call requests', function (string $line) {
    $result = acaGradedCall(transcript: ['intro_ok' => "[0:14] consumer: {$line}"]);

    expect($result['correct'])->toBe('transfer')
        ->and($result['checks']['dnc_respected'])->toBeNull()
        ->and($result['passed'])->toBeTrue();
})->with([
    "If this turns out to be a scam, don't call me again.",
    'If this is a scam, don’t call me again.',
    'Why the f*** are you calling me?',
]);

test('a direct do-not-call request makes the call a DNC, written with curly quotes or not', function (string $line) {
    $result = acaGradedCall(['disposition' => 'dnc'], [
        '[0:04] agent: Hey, am I speaking with Ashley?',
        "[0:06] consumer: {$line}",
        '[0:08] agent: I will add you to our Do Not Call list right away, you will not get any further calls from our company.',
    ], transferClickedAt: null, events: []);

    expect($result['correct'])->toBe('dnc')
        ->and($result['checks']['dnc_respected'])->toBeTrue()
        ->and($result['passed'])->toBeTrue();
})->with([
    'Don’t call me again, take me off your list.',
    "Stop calling me. Don't call me again.",
]);

test('rebutting past a do-not-call request fails the call', function () {
    $result = acaGradedCall(['disposition' => 'dnc'], [
        '[0:04] agent: Hey, am I speaking with Ashley?',
        '[0:06] consumer: Take me off your list.',
        "[0:08] agent: This isn't a matter of interest, are you still living in Texas?",
    ], transferClickedAt: null, events: []);

    expect($result['checks']['dnc_respected'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
});

test('a hang-up is Not Interested before the conference and a Hung-Up Transfer after it', function (?int $clickedAt, string $correct) {
    $result = acaGradedCall(['end_reason' => 'hung_up', 'disposition' => $correct], transferClickedAt: $clickedAt);

    expect($result['correct'])->toBe($correct)
        ->and($result['checks']['disposition'])->toBeTrue();
})->with([
    'before the conference' => [null, 'not_interested'],
    'after the conference' => [40, 'hung_up_transfer'],
]);

test('asking to be called back again after the rebuttal makes the call a Call Back', function () {
    $result = acaGradedCall(['disposition' => 'call_back'], events: [
        ['type' => 'objection_raised', 'id' => 'busy_callback', 'at' => 10_000],
        ['type' => 'agent_speech', 'start' => 12_000, 'end' => 16_000],
        ['type' => 'objection_raised', 'id' => 'busy_callback', 'at' => 18_000],
    ], transferClickedAt: null);

    expect($result['correct'])->toBe('call_back')
        ->and($result['checks']['disposition'])->toBeTrue();
});

test('every way of naming the ACA satisfies the ACA mention', function (string $name) {
    $result = acaGradedCall(transcript: [
        'intro' => "[0:08] agent: This is Sam with America's Health on a recorded line, calling about your subsidy through {$name}.",
    ]);

    expect($result['checks']['aca_mention'])->toBeTrue();
})->with(['the Affordable Care Act', 'the ACA', 'Obamacare', 'the Marketplace']);

test('the company name must be America\'s Health or a DBA on the script', function (string $company, bool $passes) {
    $result = acaGradedCall(transcript: [
        'intro' => "[0:08] agent: This is Sam with {$company} on a recorded line, calling about your ACA subsidy.",
    ]);

    expect($result['checks']['company_recorded_line'])->toBe($passes);
})->with([
    ["America's Health", true],
    ['Health Benefit Guide', true],
    ['Benefit Link', true],
    ['American Health', false],
]);

test('approved script lines are never instant fails or promises', function (string $line) {
    $result = acaGradedCall(transcript: ['fluff' => "[0:42] agent: {$line}"]);

    expect($result['checks']['no_instant_fail'])->toBeTrue()
        ->and($result['checks']['no_promises'])->toBeTrue()
        ->and($result['passed'])->toBeTrue();
})->with([
    'The Affordable Care Act is free health insurance.',
    "Now that I've updated this information, you may now begin to receive your assistance for 2026.",
    'That is your subsidy allowance through the Affordable Care Act.',
    "It's showing you haven't received it yet.",
    "I can't guarantee you'll be approved, the licensed agent will go over that.",
]);

test('an instant-fail line fails the call whatever else went right', function (string $line) {
    $result = acaGradedCall(transcript: ['fluff' => "[0:42] agent: {$line}"]);

    expect($result['checks']['no_instant_fail'])->toBeFalse()
        ->and($result['passed'])->toBeFalse();
})->with([
    'free money' => ["It's basically free money from the government."],
    'dollar amount' => ['You’ll get $500 a month.'],
    'sending a card' => ["We'll send you a card in the mail."],
    'the government' => ["I'm calling from the Marketplace."],
    'approved' => ["You've been approved."],
    'guarantee' => ['I guarantee you get the subsidy.'],
    'financial details' => ['Can I get your social security number?'],
    'pitch perfect' => ['I work for Pitch Perfect.'],
    'not a sales call' => ['This is not a sales call.'],
]);

test('an unhedged promise fails the promise check but a hedged one passes', function (string $line, bool $passes) {
    $result = acaGradedCall(transcript: ['fluff' => "[0:42] agent: {$line}"]);

    expect($result['checks']['no_promises'])->toBe($passes);
})->with([
    ['You qualify for the subsidy.', false],
    ["You're eligible for the subsidy.", false],
    ['You may qualify for the subsidy.', true],
    ['See what you might be eligible for.', true],
]);

test('forbidden words are graded by where the agent said them, and only the agent\'s words count', function () {
    $result = acaGradedCall(transcript: [
        'state_yes' => '[0:17] consumer: Is this money? Help with my bills?',
        'state' => '[0:15] agent: Are you still living in Texas to receive it? There is no total to calculate.',
        'fluff' => "[0:42] agent: Groceries are so expensive right now, aren't they?",
    ]);

    expect($result['checks']['forbidden_words_pitch'])->toBeFalse()
        ->and($result['checks']['forbidden_words_hold'])->toBeFalse()
        ->and($result['passed'])->toBeTrue();
});

test('"check" only counts as a forbidden word when it means a cheque', function () {
    $result = acaGradedCall(transcript: [
        'state' => '[0:15] agent: Let me check, are you still living in Texas to receive it?',
    ]);

    expect($result['checks']['forbidden_words_pitch'])->toBeTrue();
});

test('a lead who moved must be confirmed and introduced in the state they live in', function () {
    $persona = [...RoleplaySession::factory()->aca()->make()->persona, 'actual_state' => 'Arkansas'];

    $result = acaGradedCall(['persona' => $persona], [
        'state_yes' => "[0:17] consumer: No, I'm in Arkansas.",
        'first_check' => '[0:18] agent: Let me update that to Arkansas. And you have no Medicare, no Medicaid and no work insurance, correct?',
        'handoff' => '[1:12] agent: Hi, I have Ashley from Arkansas, please take the call.',
    ]);

    expect($result['checks']['confirm_state'])->toBeTrue()
        ->and($result['checks']['handoff_names_state'])->toBeTrue();
});
