<?php

use App\Models\RoleplaySession;
use App\Services\RoleplayGrader;

function gradedCall(array $attributes = [], ?array $transcript = null, ?int $transferClickedAt = 40): array
{
    $session = RoleplaySession::factory()->make([
        'disposition' => 'transfer',
        'end_reason' => 'agent',
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

    return [$result['passed'], array_column($result['checks'], 'passed', 'key')];
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

test('a benefit claim without may or maybe fails the hedging check', function () {
    [, $checks] = gradedCall(transcript: ['[0:20] agent: You are ENTITLED to some additional food benefits and you qualify for more.']);

    expect($checks['may_maybe'])->toBeFalse();
});
