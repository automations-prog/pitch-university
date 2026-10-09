<?php

use App\Services\RoleplayDeliveryMetrics;

function agentLine(int $at, string $text): array
{
    return ['at' => $at, 'role' => 'agent', 'text' => $text];
}

test('pace, fillers, pauses, barge-ins and the opener are measured from the event log', function () {
    $events = RoleplayDeliveryMetrics::sanitize([
        ['type' => 'consumer_speech', 'start' => 0, 'end' => 1000],
        ['type' => 'agent_speech', 'start' => 1500, 'end' => 13500],
        ['type' => 'consumer_speech', 'start' => 14000, 'end' => 16000],
        ['type' => 'objection_raised', 'id' => 'busy', 'at' => 15000],
        ['type' => 'agent_speech', 'start' => 20500, 'end' => 26500],
        ['type' => 'consumer_speech', 'start' => 27000, 'end' => 31000],
        ['type' => 'agent_speech', 'start' => 29000, 'end' => 32000],
        ['type' => 'objection_resolved', 'id' => 'busy', 'at' => 27000],
    ]);
    $transcript = [
        agentLine(1, 'Hey Dorothy, um, this is Sam with America\'s Health on a recorded line, uh, calling about your card.'),
        agentLine(20, 'Let me assure you this will be super brief, I just have three quick questions.'),
    ];

    $metrics = (new RoleplayDeliveryMetrics)->compute($events, $transcript);

    // 33 words over 21s of agent speech.
    expect($metrics['wpm'])->toBe(94)
        ->and($metrics['fillers_per_100'])->toBe(6.1)
        ->and($metrics['opener_delay_ms'])->toBe(500)
        ->and($metrics['longest_gap_ms'])->toBe(4500)
        ->and($metrics['gaps_over_threshold'])->toBe(1)
        ->and($metrics['gaps_after_objection'])->toBe(1)
        ->and($metrics['barge_ins'])->toBe(1)
        ->and($metrics['objection_seconds'])->toBe([12.0])
        ->and($metrics['unresolved_objections'])->toBe(0);
});

test('an empty event log leaves timing metrics unmeasured instead of perfect', function () {
    $metrics = (new RoleplayDeliveryMetrics)->compute([], []);

    expect($metrics)->toMatchArray([
        'wpm' => null,
        'fillers_per_100' => null,
        'longest_gap_ms' => null,
        'barge_ins' => null,
        'opener_delay_ms' => null,
        'unresolved_objections' => null,
        'loudness_variation' => null,
    ]);
});

test('an objection never resolved is counted', function () {
    $events = RoleplayDeliveryMetrics::sanitize([
        ['type' => 'objection_raised', 'id' => 'scam', 'at' => 5000],
    ]);

    expect((new RoleplayDeliveryMetrics)->compute($events, [])['unresolved_objections'])->toBe(1);
});

test('a monotone delivery has near-zero loudness variation', function () {
    $flat = array_map(fn (int $second) => ['type' => 'agent_loudness', 'at' => $second * 1000, 'rms' => 0.1], range(1, 10));
    $lively = array_map(fn (int $second) => ['type' => 'agent_loudness', 'at' => $second * 1000, 'rms' => $second % 2 ? 0.05 : 0.15], range(1, 10));

    $metrics = new RoleplayDeliveryMetrics;

    expect($metrics->compute(RoleplayDeliveryMetrics::sanitize($flat), [])['loudness_variation'])->toBe(0.0)
        ->and($metrics->compute(RoleplayDeliveryMetrics::sanitize($lively), [])['loudness_variation'])->toBe(0.5);
});

test('sanitizing drops unknown types and malformed events, and caps the count', function () {
    $events = RoleplayDeliveryMetrics::sanitize([
        ['type' => 'agent_speech', 'start' => 2000, 'end' => 3000],
        ['type' => 'shell', 'cmd' => 'rm -rf /'],
        ['type' => 'agent_speech', 'start' => 'soon', 'end' => 3000],
        ['type' => 'patience_changed', 'at' => 1000, 'patience' => 7, 'reason' => '<b>argued</b>'],
        'not an event',
    ]);

    expect($events)->toBe([
        ['type' => 'patience_changed', 'at' => 1000, 'patience' => 7, 'reason' => 'argued'],
        ['type' => 'agent_speech', 'start' => 2000, 'end' => 3000],
    ])
        ->and(RoleplayDeliveryMetrics::sanitize(array_fill(0, 6000, ['type' => 'transfer_clicked', 'at' => 1])))
        ->toHaveCount(RoleplayDeliveryMetrics::MAX_EVENTS);
});

test('greeting checks are kept so silent greetings can be diagnosed', function () {
    $events = RoleplayDeliveryMetrics::sanitize([
        ['type' => 'greeting_checked', 'at' => 900, 'attempt' => 1, 'heard' => 0, 'heard_ms' => 50, 'peak_rms' => 0.0213, 'extra' => 'dropped'],
    ]);

    expect($events)->toBe([
        ['type' => 'greeting_checked', 'at' => 900, 'attempt' => 1, 'heard' => 0, 'heard_ms' => 50, 'peak_rms' => 0.0213],
    ]);
});

test('echo diagnostics are kept so echo on a call can be diagnosed', function () {
    $events = RoleplayDeliveryMetrics::sanitize([
        ['type' => 'audio_checked', 'at' => 0, 'echo_cancellation' => 1, 'noise_suppression' => 1, 'auto_gain_control' => 0],
        ['type' => 'echo_discarded', 'at' => 5600],
        ['type' => 'audio_checked', 'at' => 0, 'echo_cancellation' => 'yes', 'noise_suppression' => 1, 'auto_gain_control' => 1],
    ]);

    expect($events)->toBe([
        ['type' => 'audio_checked', 'at' => 0, 'echo_cancellation' => 1, 'noise_suppression' => 1, 'auto_gain_control' => 0],
        ['type' => 'echo_discarded', 'at' => 5600],
    ]);
});

test('each time the consumer was cut off counts as a barge-in, without counting the same one twice', function () {
    $events = RoleplayDeliveryMetrics::sanitize([
        ['type' => 'consumer_speech', 'start' => 0, 'end' => 4000],
        ['type' => 'agent_speech', 'start' => 1000, 'end' => 3000],
        ['type' => 'consumer_interrupted', 'at' => 1400],
        ['type' => 'consumer_speech', 'start' => 10000, 'end' => 10500],
        ['type' => 'consumer_interrupted', 'at' => 10600],
    ]);

    $metrics = (new RoleplayDeliveryMetrics)->compute($events, []);

    expect($metrics['barge_ins'])->toBe(2);
});
