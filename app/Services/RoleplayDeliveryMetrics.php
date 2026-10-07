<?php

namespace App\Services;

/**
 * Turns a call's timed event log (recorded by the browser, see
 * `use-roleplay-realtime-call.ts`) and transcript into delivery metrics.
 * Pure computation: no I/O. A metric that can't be measured is `null`,
 * never zero, so it isn't mistaken for a perfect score.
 *
 * Event times are milliseconds from call start.
 *
 * @phpstan-type Segment array{start: int, end: int}
 * @phpstan-type Metrics array{wpm: int|null, fillers_per_100: float|null, longest_gap_ms: int|null, gaps_over_threshold: int|null, gaps_after_objection: int|null, barge_ins: int|null, opener_delay_ms: int|null, objection_seconds: list<float>, unresolved_objections: int|null, loudness_variation: float|null}
 */
class RoleplayDeliveryMetrics
{
    /** The event types the browser may send, and their numeric fields. */
    public const array EVENT_FIELDS = [
        'agent_speech' => ['start', 'end'],
        'consumer_speech' => ['start', 'end'],
        'objection_raised' => ['at'],
        'objection_resolved' => ['at'],
        'patience_changed' => ['at', 'patience'],
        'transfer_clicked' => ['at'],
        'specialist_joined' => ['at'],
        'lead_answered_specialist' => ['at'],
        'transfer_completed' => ['at'],
        'agent_talked_on_connect' => ['at'],
        'consumer_interrupted' => ['at'],
        'agent_loudness' => ['at', 'rms'],
        // Whether the consumer's "Hello?" was heard, to diagnose silent greetings.
        'greeting_checked' => ['at', 'attempt', 'heard', 'heard_ms', 'peak_rms'],
    ];

    public const int MAX_EVENTS = 5000;

    /** Agent speech shorter than this is too little to judge pace on. */
    private const int MIN_SPEAKING_MS = 10_000;

    /** An agent start this far inside the consumer's audio is a barge-in, not a hand-off. */
    private const int BARGE_IN_MARGIN_MS = 300;

    /** An overlap this close to a logged interruption is the same barge-in. */
    private const int INTERRUPTION_MATCH_MS = 1500;

    /** Fillers kept by the transcription prompt. "like" only counts set off by commas. */
    private const string FILLERS = '/\b(u+m+|u+h+|e+r+m*|you know)\b|,\s*like\s*,/i';

    /**
     * Keep only known event types with numeric fields, sorted by time, and
     * cap the count. Anything else from the browser is dropped.
     *
     * @param  array<mixed>  $events
     * @return list<array<string, mixed>>
     */
    public static function sanitize(array $events): array
    {
        $clean = [];

        foreach (array_slice($events, 0, self::MAX_EVENTS) as $event) {
            $type = is_array($event) ? ($event['type'] ?? null) : null;

            if (! is_string($type) || ! isset(self::EVENT_FIELDS[$type])) {
                continue;
            }

            $kept = ['type' => $type];

            foreach (self::EVENT_FIELDS[$type] as $field) {
                if (! is_numeric($event[$field] ?? null)) {
                    continue 2;
                }

                $kept[$field] = str_ends_with($field, 'rms') ? (float) $event[$field] : max(0, (int) $event[$field]);
            }

            if (in_array($type, ['objection_raised', 'objection_resolved'], true)) {
                $kept['id'] = substr((string) preg_replace('/[^a-z_]/', '', (string) ($event['id'] ?? '')), 0, 64);
            }

            if ($type === 'patience_changed') {
                $kept['reason'] = mb_substr(strip_tags((string) ($event['reason'] ?? '')), 0, 200);
            }

            $clean[] = $kept;
        }

        usort($clean, fn (array $a, array $b): int => ($a['start'] ?? $a['at']) <=> ($b['start'] ?? $b['at']));

        return $clean;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  list<array{at: int, role: string, text: string}>  $transcript
     * @return Metrics
     */
    public function compute(array $events, array $transcript): array
    {
        $agentSegments = $this->segments($events, 'agent_speech');
        $consumerSegments = $this->segments($events, 'consumer_speech');
        $agentText = implode(' ', array_column(array_filter($transcript, fn (array $line): bool => $line['role'] === 'agent'), 'text'));
        $agentWords = str_word_count($agentText);

        [$longestGap, $gapsOver, $gapsAfterObjection] = $this->gaps($agentSegments, $consumerSegments, $this->ofType($events, 'objection_raised'));
        [$objectionSeconds, $unresolved] = $this->objectionTiming($events);

        return [
            'wpm' => $this->wordsPerMinute($agentWords, $agentSegments),
            'fillers_per_100' => $agentWords === 0 ? null : round(preg_match_all(self::FILLERS, $agentText) / $agentWords * 100, 1),
            'longest_gap_ms' => $longestGap,
            'gaps_over_threshold' => $gapsOver,
            'gaps_after_objection' => $gapsAfterObjection,
            'barge_ins' => $this->bargeIns($agentSegments, $consumerSegments, array_column($this->ofType($events, 'consumer_interrupted'), 'at')),
            'opener_delay_ms' => $this->openerDelay($agentSegments, $consumerSegments),
            'objection_seconds' => $objectionSeconds,
            'unresolved_objections' => $unresolved,
            'loudness_variation' => $this->loudnessVariation($events),
        ];
    }

    /**
     * @param  list<Segment>  $agentSegments
     */
    private function wordsPerMinute(int $words, array $agentSegments): ?int
    {
        $speakingMs = array_sum(array_map(fn (array $segment): int => $segment['end'] - $segment['start'], $agentSegments));

        return $speakingMs < self::MIN_SPEAKING_MS || $words === 0
            ? null
            : (int) round($words / ($speakingMs / 60_000));
    }

    /**
     * Silence between the consumer finishing and the agent's next start,
     * when the agent is the next to speak.
     *
     * @param  list<Segment>  $agentSegments
     * @param  list<Segment>  $consumerSegments
     * @param  list<array<string, mixed>>  $objections
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function gaps(array $agentSegments, array $consumerSegments, array $objections): array
    {
        if ($agentSegments === [] || $consumerSegments === []) {
            return [null, null, null];
        }

        $threshold = (int) config('roleplay.delivery.dead_air_ms');
        $longest = 0;
        $over = 0;
        $afterObjection = 0;

        foreach ($consumerSegments as $index => $consumer) {
            $nextConsumerStart = $consumerSegments[$index + 1]['start'] ?? PHP_INT_MAX;
            $reply = $this->firstStartBetween($agentSegments, $consumer['end'], $nextConsumerStart);

            if ($reply === null) {
                continue;
            }

            $gap = $reply - $consumer['end'];
            $longest = max($longest, $gap);

            if ($gap <= $threshold) {
                continue;
            }

            $over++;

            foreach ($objections as $objection) {
                if ($objection['at'] >= $consumer['start'] - 500 && $objection['at'] <= $consumer['end'] + 2000) {
                    $afterObjection++;

                    break;
                }
            }
        }

        return [$longest, $over, $afterObjection];
    }

    /**
     * Times the agent talked over the consumer: each time the server cut
     * the consumer off, plus agent starts inside the consumer's audio that
     * didn't cut them off. A cut ends the consumer's audio almost at once,
     * so the overlap alone misses most real interruptions.
     *
     * @param  list<Segment>  $agentSegments
     * @param  list<Segment>  $consumerSegments
     * @param  list<int>  $interruptedAt
     */
    private function bargeIns(array $agentSegments, array $consumerSegments, array $interruptedAt): ?int
    {
        if ($interruptedAt === [] && ($agentSegments === [] || $consumerSegments === [])) {
            return null;
        }

        $count = count($interruptedAt);

        foreach ($agentSegments as $agent) {
            foreach ($interruptedAt as $at) {
                if (abs($agent['start'] - $at) <= self::INTERRUPTION_MATCH_MS) {
                    continue 2;
                }
            }

            foreach ($consumerSegments as $consumer) {
                if ($agent['start'] > $consumer['start'] + self::BARGE_IN_MARGIN_MS && $agent['start'] < $consumer['end'] - self::BARGE_IN_MARGIN_MS) {
                    $count++;

                    break;
                }
            }
        }

        return $count;
    }

    /**
     * From the consumer's "Hello?" ending to the agent's first words.
     *
     * @param  list<Segment>  $agentSegments
     * @param  list<Segment>  $consumerSegments
     */
    private function openerDelay(array $agentSegments, array $consumerSegments): ?int
    {
        if ($consumerSegments === []) {
            return null;
        }

        $hello = $consumerSegments[0];
        $first = $this->firstStartBetween($agentSegments, $hello['start'], PHP_INT_MAX);

        return $first === null ? null : max(0, $first - $hello['end']);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{0: list<float>, 1: int|null}
     */
    private function objectionTiming(array $events): array
    {
        $raised = $this->ofType($events, 'objection_raised');

        if ($raised === []) {
            return [[], null];
        }

        $resolved = $this->ofType($events, 'objection_resolved');
        $seconds = [];
        $unresolved = 0;

        foreach ($raised as $objection) {
            $match = null;

            foreach ($resolved as $candidate) {
                if ($candidate['id'] === $objection['id'] && $candidate['at'] >= $objection['at']) {
                    $match = $candidate;

                    break;
                }
            }

            if ($match === null) {
                $unresolved++;

                continue;
            }

            $seconds[] = round(($match['at'] - $objection['at']) / 1000, 1);
        }

        return [$seconds, $unresolved];
    }

    /**
     * Coefficient of variation of the mic level while the agent speaks: a
     * flat, monotone delivery stays near zero.
     *
     * @param  list<array<string, mixed>>  $events
     */
    private function loudnessVariation(array $events): ?float
    {
        $levels = array_column($this->ofType($events, 'agent_loudness'), 'rms');

        if (count($levels) < 5) {
            return null;
        }

        $mean = array_sum($levels) / count($levels);

        if ($mean <= 0.0) {
            return null;
        }

        $variance = array_sum(array_map(fn (float $level): float => ($level - $mean) ** 2, $levels)) / count($levels);

        return round(sqrt($variance) / $mean, 2);
    }

    /**
     * @param  list<Segment>  $segments
     */
    private function firstStartBetween(array $segments, int $after, int $before): ?int
    {
        foreach ($segments as $segment) {
            if ($segment['start'] >= $after && $segment['start'] < $before) {
                return $segment['start'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<Segment>
     */
    private function segments(array $events, string $type): array
    {
        return array_values(array_map(
            fn (array $event): array => ['start' => $event['start'], 'end' => max($event['start'], $event['end'])],
            $this->ofType($events, $type),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function ofType(array $events, string $type): array
    {
        return array_values(array_filter($events, fn (array $event): bool => $event['type'] === $type));
    }
}
