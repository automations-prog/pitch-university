<?php

use App\Models\RoleplaySession;
use App\Services\RoleplayGrader;

/**
 * A clean cold transfer: clicked at 0:40, fluffed while it rang, the
 * specialist joined at 1:10, the lead answered, then the trainee left.
 * Override an entry with `null` to drop it.
 *
 * @return list<array<string, mixed>>
 */
function cleanHandoffEvents(array $overrides = []): array
{
    return array_values(array_filter([
        'clicked' => ['type' => 'transfer_clicked', 'at' => 40_000],
        'fluff' => ['type' => 'agent_speech', 'start' => 42_000, 'end' => 50_000],
        'joined' => ['type' => 'specialist_joined', 'at' => 70_000],
        'answered' => ['type' => 'lead_answered_specialist', 'at' => 74_000],
        'completed' => ['type' => 'transfer_completed', 'at' => 76_000],
        ...$overrides,
    ]));
}

function gradedCall(array $attributes = [], ?array $transcript = null, ?int $transferClickedAt = 40, ?array $events = null): array
{
    $session = RoleplaySession::factory()->make([
        'disposition' => 'transfer',
        'end_reason' => 'agent',
        'events' => $events ?? cleanHandoffEvents(),
        'transcript' => implode("\n", $transcript ?? [
            "[0:05] agent: I'm with America's Health on a recorded line. You DO still have your Medicare Parts A and B, correct?",
            '[0:12] agent: And JUST to double confirm, that IS the red, white and blue card, correct?',
            '[0:20] agent: You may be ENTITLED to some additional food benefits. I DO still have you out in Florida, with the zip code 3 3 5 1 1, correct?',
            '[0:28] agent: Do you have insurance through your work or the VA?',
            '[0:33] agent: A Medicare specialist is coming on the line now. Give them JUST a few minutes to go over them with you, okay?',
            '[0:38] consumer: Sure, why not.',
        ]),
        ...$attributes,
    ]);

    $result = (new RoleplayGrader)->grade($session, $transferClickedAt);

    return [$result['passed'], array_column($result['checks'], 'passed', 'key'), $result['correct_disposition']];
}

test('a compliant call with the right disposition and a transfer yes passes every check', function () {
    [$passed, $checks] = gradedCall();

    expect($passed)->toBeTrue()
        ->and(array_filter($checks, fn (?bool $check) => $check !== true))->toBe([]);
});

test('the wrong disposition fails the call', function () {
    [$passed, $checks] = gradedCall(['disposition' => 'dq']);

    expect($passed)->toBeFalse()->and($checks['disposition'])->toBeFalse();
});

test('a hang-up fails the call', function () {
    [$passed, $checks] = gradedCall(['end_reason' => 'hung_up']);

    expect($passed)->toBeFalse()->and($checks['patience'])->toBeFalse();
});

test('clicking transfer without a yes fails the call', function (array $lines, ?int $clickedAt) {
    [$passed, $checks] = gradedCall(transcript: $lines, transferClickedAt: $clickedAt);

    expect($passed)->toBeFalse()->and($checks['transfer_permission'])->toBeFalse();
})->with([
    'consumer said no' => [[
        '[0:33] agent: Give them JUST a few minutes to go over them with you, okay?',
        "[0:38] consumer: No, I don't have a few minutes.",
    ], 40],
    'clicked before the yes' => [[
        '[0:33] agent: Give them JUST a few minutes to go over them with you, okay?',
        '[0:45] consumer: Okay.',
    ], 40],
    'never asked' => [[
        '[0:38] consumer: Okay.',
    ], 40],
    'never clicked' => [[
        '[0:33] agent: Give them JUST a few minutes to go over them with you, okay?',
        '[0:38] consumer: Okay.',
    ], null],
]);

test('the transfer permission check does not apply to DQ and DNC personas', function () {
    $session = RoleplaySession::factory()->disqualified()->make(['disposition' => 'dq', 'end_reason' => 'agent', 'transcript' => '']);

    $result = (new RoleplayGrader)->grade($session, null);

    expect($result['passed'])->toBeTrue()
        ->and(array_column($result['checks'], 'passed', 'key')['transfer_permission'])->toBeNull();
});

test('the opener checks accept how transcription writes the script', function (string $opener, string $card) {
    [, $checks] = gradedCall(transcript: ["[0:05] agent: {$opener}", "[0:12] agent: {$card}"]);

    expect($checks['recorded_line'])->toBeTrue()
        ->and($checks['double_confirm'])->toBeTrue();
})->with([
    ["I'm with Americas Health on a recorded line. You still have Medicare Part A and Part B?", 'That is the red white and blue card?'],
    ['This is America Health on a recorded line. You have your Medicare A & B?', 'The red, white, blue card, correct?'],
]);

test('the opener checks still fail when the script was skipped', function () {
    [, $checks] = gradedCall(transcript: ['[0:05] agent: Hi, you have Medicare, right?', '[0:12] agent: The blue card?']);

    expect($checks['recorded_line'])->toBeFalse()
        ->and($checks['double_confirm'])->toBeFalse();
});

test('a benefit claim without may or maybe fails the hedging check', function () {
    [, $checks] = gradedCall(transcript: ['[0:20] agent: You are ENTITLED to some additional food benefits and you qualify for more.']);

    expect($checks['may_maybe'])->toBeFalse();
});

test('completing the transfer before the lead answers the specialist fails the call', function (array $overrides) {
    [$passed, $checks] = gradedCall(events: cleanHandoffEvents($overrides));

    expect($passed)->toBeFalse()->and($checks['completed_after_both_spoke'])->toBeFalse();
})->with([
    'left before the answer' => [['completed' => ['type' => 'transfer_completed', 'at' => 72_000]]],
    'left during the ringing' => [['joined' => null, 'answered' => null, 'completed' => ['type' => 'transfer_completed', 'at' => 50_000]]],
    'never left the conference' => [['completed' => null]],
]);

test('the fluff check needs enough talking while the transfer rings', function () {
    [, $checks] = gradedCall(events: cleanHandoffEvents([
        'fluff' => ['type' => 'agent_speech', 'start' => 42_000, 'end' => 44_000],
    ]));

    expect($checks['fluffed'])->toBeFalse();
});

test('talking once the specialist joins fails the silence check', function () {
    [, $checks] = gradedCall(events: cleanHandoffEvents([
        'talked' => ['type' => 'agent_talked_on_connect', 'at' => 71_000],
    ]));

    expect($checks['silent_on_connect'])->toBeFalse();
});

test('a second busy after the rebuttal makes Not Interested the correct code', function (string $disposition, bool $shouldPass) {
    [$passed, $checks, $correct] = gradedCall(
        ['disposition' => $disposition],
        transferClickedAt: null,
        events: [
            ['type' => 'objection_raised', 'id' => 'busy', 'at' => 6_000],
            ['type' => 'agent_speech', 'start' => 8_000, 'end' => 12_000],
            ['type' => 'objection_raised', 'id' => 'busy', 'at' => 14_000],
        ],
    );

    expect($correct)->toBe('not_interested')
        ->and($passed)->toBe($shouldPass)
        ->and($checks['transfer_permission'])->toBeNull()
        ->and($checks['completed_after_both_spoke'])->toBeNull();
})->with([
    'coded not interested' => ['not_interested', true],
    'coded transfer' => ['transfer', false],
]);

test('a repeated busy is caught from the transcript when the objection was not logged', function (string $first, string $second) {
    [$passed, , $correct] = gradedCall(['disposition' => 'not_interested'], transcript: [
        "[0:03] consumer: {$first}",
        "[0:05] agent: Oh, let me assure you this will be SUPER brief. I just have 3 SUPER quick questions and I'll bring the agent on.",
        "[0:10] consumer: {$second}",
    ], transferClickedAt: null, events: []);

    expect($correct)->toBe('not_interested')->and($passed)->toBeTrue();
})->with([
    ["I'm busy right now, call me back later.", "I told you, I can't talk right now."],
    ["I'm at the doctor, I can't talk.", "Honey, I really don't have time for this."],
    ["I'm literally driving right now, man.", 'Look, I gotta go.'],
    ["This isn't a good time.", "I'm at work, I'm in a hurry."],
]);

test('a single busy line in the transcript keeps the persona outcome', function () {
    [, , $correct] = gradedCall(transcript: [
        "[0:03] consumer: I don't have time for a long call.",
        "[0:05] agent: Oh, let me assure you this will be SUPER brief. I just have 3 SUPER quick questions and I'll bring the agent on.",
        '[0:10] consumer: Okay, go ahead.',
    ], events: []);

    expect($correct)->toBe('transfer');
});

test('a busy objection said only once keeps the persona outcome', function () {
    [, , $correct] = gradedCall(events: [
        ['type' => 'objection_raised', 'id' => 'busy', 'at' => 6_000],
        ['type' => 'agent_speech', 'start' => 8_000, 'end' => 12_000],
    ]);

    expect($correct)->toBe('transfer');
});

test('asking not to be called makes DNC the correct code', function (string $line) {
    [, , $correct] = gradedCall(transcript: ["[0:10] consumer: {$line}"]);

    expect($correct)->toBe('dnc');
})->with([
    'Stop calling me.',
    "Take me off your list, I'm serious.",
    "Don't call me ever again or I'll get a lawyer.",
]);

test('swearing, complaining about calls or asking for a call back is not a DNC', function (string $line) {
    [, , $correct] = gradedCall(transcript: ["[0:10] consumer: {$line}"]);

    expect($correct)->toBe('transfer');
})->with([
    'Why the hell are you calling me?',
    "This is the 16th telemarketing call I've had today and I am so sick of it.",
    "Don't call me back later, just make it quick.",
]);

test('the script rebuttals about other people qualifying pass the hedging check', function () {
    [, $checks] = gradedCall(transcript: [
        "[0:20] agent: I understand, but MANY people are finding out RIGHT now that they ACTUALLY qualify for WAY more than they're receiving.",
        "[0:30] agent: Oh, you probably spoke with one of our small competitors. The difference is WE work with over 25 different health insurance providers, so people ARE finding that with US, they qualify for WAY more benefits than they're receiving.",
    ]);

    expect($checks['may_maybe'])->toBeTrue();
});
