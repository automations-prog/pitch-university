<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Models\RoleplaySession;

/**
 * Deterministic checks over a finished Medicaid meals call, one per
 * required step in `ai-roleplay/meals/script.md` plus the warm handoff to
 * the screener, the instant fails and the pause after a brush-off.
 *
 * The Medicaid question is the call: transferring without the word
 * "Medicaid" ever coming out of the agent is treated as fraud and fails
 * the call whatever else happened (`04_PROMPTS_MEALS.md`). The check is
 * deliberately conservative and fires only when the word never appears.
 *
 * @phpstan-import-type Line from RoleplayGrader
 * @phpstan-import-type Event from RoleplayGrader
 */
class MealsRoleplayGrader extends AcaRoleplayGrader
{
    protected const string HANDOFF_PARTY = 'the screener';

    /** The agent's transfer ask, as the script words it. */
    protected const string WARM_TRANSFER_ASK = '/screener|on the line|(get|bring|have) (them|someone|somebody|him|her) on/';

    /** The DQ line, or anything telling the consumer they don't qualify. */
    protected const string DQ_LINE = '/must have been a mistake|\bnot (be )?qualified\b|\bwouldnt (be )?qualif|\bdont qualify\b|\bnot eligible\b/';

    /** No written DNC line on this script: any plain removal statement counts. */
    protected const string DNC_DISCLAIMER = '/do not call|dont call|\bdnc\b|further calls|remove you|removed|take you off|no more calls|wont (call|contact) you/';

    /** Rules where a preceding negative rescues the line ("you don't have to pay anything"). */
    protected const array NEGATABLE_INSTANT_FAILS = ['guarantee', 'dollar_amount', 'you_pay'];

    /**
     * Instant fails from `04_PROMPTS_MEALS.md`, matched against the agent's
     * normalised line (lowercase, apostrophes removed). The approved
     * wording ("you're already approved", "at no cost", "no change to your
     * coverage") is never one.
     *
     * @var array<string, array{pattern: string, reason: string}>
     */
    protected const array INSTANT_FAILS = [
        'costs_money' => ['pattern' => '/\b(it|this|that|the meals?)\s+(will\s+)?(costs?|charges?)\s+(you\s+)?\$?\d/', 'reason' => 'Told the consumer the service costs money'],
        // Not the copay fallback question: "do you have to pay anything at the doctor?"
        'you_pay' => ['pattern' => '/(?<!\bdo )\byou(ll| will| have to| need to)\s+(have to\s+)?pay\b(?![^.?!]{0,20}\b(nothing|anything)\b)/', 'reason' => 'Told the consumer they have to pay'],
        'coverage_change' => ['pattern' => '/\b(your\s+)?(medicaid\s+)?coverage\s+(will|would|is going to)\s+change\b/', 'reason' => 'Said their coverage would change'],
        'government' => ['pattern' => '/\b(im|i am|were|we are|this is)\s+(\w+\s+)?(calling\s+)?(with|from)\s+(the\s+)?(government|state|city|medicaid|medicare|social security|new york state)\b/', 'reason' => 'Claimed to be Medicaid, the state or the government'],
        'delivery_date' => ['pattern' => '/\b(well|ill|theyll|we will|i will|they will)\s+(deliver|have it there|start)\b[^.?!]{0,30}\b(today|tomorrow|tonight|monday|tuesday|wednesday|thursday|friday|saturday|sunday|this week|next week|in \d+ days?)\b/', 'reason' => 'Promised a delivery date'],
        'dollar_amount' => ['pattern' => '/\byou(ll| will| are going to|re going to)\s+(get|receive)\s+(up to\s+)?(\$\s?\d|\d[\d,]*\s*dollars)/', 'reason' => 'Promised a dollar amount'],
        'guarantee' => ['pattern' => '/\bguarantee(d)?\b[^.?!]{0,40}\b(meals?|delivery|approv|qualif)/', 'reason' => 'Guaranteed meals or approval'],
        'id_numbers' => ['pattern' => '/\b(whats|what is|read|give me|can i (get|have)|provide|tell me)\b[^.?!]{0,40}\b(social security( number)?|ssn|medicaid (id|number|card number)|cin( number)?|bank|account number|routing number|card number)\b/', 'reason' => 'Asked for a Social Security, Medicaid or bank number'],
        'pitch_perfect' => ['pattern' => '/\bpitch\s*perfect\b/', 'reason' => 'Named Pitch Perfect on the call'],
    ];

    /** A sentence asking whether they receive Medicaid, not just naming the service. */
    private const string MEDICAID_ASK = '/\b(receiv\w*|have|get|getting|still|on)\b[^.?!]{0,30}\bmedicaid\b|\bmedicaid\b[^.?!]{0,30}\b(correct|right)\b/';

    /** The copay fallback: "when you go to the doctor, do you have to pay anything, or is it usually free?" */
    private const string COPAY_FALLBACK = '/\bdoctor\b[^.?!]*\b(pay|free|copays?)\b|\bcopays?\b/';

    /** Brush-offs, which are lost if the agent doesn't come straight back. */
    private const array BRUSH_OFFS = ['not_interested', 'dont_need_it', 'busy_callback'];

    public function __construct(MealsRoleplayScript $script)
    {
        parent::__construct($script);
    }

    public function qualifyingCheckKeys(): array
    {
        return ['medicaid_asked', 'confirm_borough', 'confirm_zip', 'double_confirm'];
    }

    public function grade(RoleplaySession $session, ?int $transferClickedAt): array
    {
        $lines = $this->parseTranscript((string) $session->transcript);
        $agent = array_values(array_filter($lines, fn (array $line): bool => $line['role'] === 'agent'));
        $agentText = $this->normalize(implode("\n", array_column($agent, 'text')));
        $persona = $session->persona;
        $lead = $persona['lead'];
        $borough = $lead['borough'] ?? $lead['state'];
        $medicaidStatus = $persona['medicaid']['status'] ?? 'yes';
        /** @var list<Event> $events */
        $events = $session->events ?? [];

        [$correctDisposition, $correctReason, $dncRequestIndex] = $this->correctAcaDisposition($session, $lines, $events, $transferClickedAt);
        $expectsTransfer = $correctDisposition === Outcome::Transfer->value;
        $isDisqualified = $persona['dq_trap'] !== null;
        $transferred = $transferClickedAt !== null || $session->disposition === Outcome::Transfer->value;

        $medicaidAsks = $this->medicaidAsks($lines);
        $handoff = $expectsTransfer ? $this->transferHandoff($events) : null;
        $instantFail = $this->instantFail($agent);
        $neverSaidMedicaid = ! preg_match('/\bmedicaid\b/', $agentText);

        $checks = [
            $this->check('correct_person', 'Confirmed you had the right person',
                $this->asksForLead($agent, $lead['name'])),
            $this->check('company_recorded_line', "Said you're with America's Health on a recorded line",
                str_contains($agentText, 'recorded line') && (bool) preg_match(self::AMERICAS_HEALTH, $agentText)),
            $this->check('meal_service', 'Said the call is about the Medicaid meal service',
                (bool) preg_match('/\bmeals?\b/', $agentText)),
            $this->check('medicaid_asked', 'Asked whether they still receive Medicaid',
                $medicaidAsks !== []),
            $this->check('copay_fallback', 'Asked the copay question when they didn\'t know ("do you have to pay anything at the doctor?")',
                $medicaidStatus === 'dont_know' && $medicaidAsks !== [] ? (bool) preg_match(self::COPAY_FALLBACK, $agentText) : null),
            $this->check('confirm_borough', "Confirmed the borough ({$borough})",
                str_contains($agentText, strtolower($borough))),
            $this->check('confirm_zip', "Confirmed the ZIP code ({$lead['zip']})",
                $this->mentionsZip($agent, $lead['zip']) || (bool) preg_match('/\bzip\b/', $agentText)),
            $this->check('double_confirm', 'Double-confirmed Medicaid and the borough as a separate last question',
                $expectsTransfer || $transferClickedAt !== null ? $this->hasSeparateDoubleConfirm($lines, $medicaidAsks) : null),
            $this->check('verbal_consent', 'Got their okay before bringing the screener on',
                $expectsTransfer ? $this->hasTransferConsent($lines, $transferClickedAt) : null),
            $this->check('fluffed', 'Kept them talking while the screener was coming on',
                $handoff['fluffed'] ?? null),
            $this->check('handoff_names_borough_zip', 'Introduced them to the screener with their name, borough and ZIP',
                $expectsTransfer ? $this->handoffNamesLead($lines, $events, $lead['name'], $borough, $lead['zip']) : null),
            $this->check('completed_after_both_spoke', 'Left the call after the consumer and the screener both spoke',
                $handoff['completed_after_both_spoke'] ?? null),
            $this->check('no_dq_transfer', 'Did not transfer a disqualified consumer',
                $isDisqualified ? $transferClickedAt === null : null),
            $this->check('dq_line', 'Read the DQ line',
                $correctDisposition === Outcome::Dq->value ? (bool) preg_match(static::DQ_LINE, $agentText) : null),
            $this->check('dnc_respected', 'Told them they will be removed and ended without a rebuttal',
                $dncRequestIndex === null ? null : $this->respectedDnc($lines, $dncRequestIndex)),
            $this->check('came_straight_back', 'Came straight back after a brush-off, without stopping or pausing',
                $this->cameStraightBack($events)),
            $this->check('no_instant_fail', $instantFail === null
                ? 'No instant-fail lines'
                : "No instant-fail lines (instant fail: {$instantFail})",
                $instantFail === null),
            $this->check('medicaid_before_transfer', $transferred && $neverSaidMedicaid
                ? 'Transferred without ever asking the Medicaid question (automatic fail: treated as fraud)'
                : 'Never transferred without asking the Medicaid question',
                $transferred ? ! $neverSaidMedicaid : null),
            $this->check('disposition', 'Coded the call correctly',
                $session->disposition === $correctDisposition),
            $this->check('patience', "Didn't run out of patience",
                $session->end_reason !== 'hung_up'),
        ];

        $byKey = array_column($checks, 'passed', 'key');

        return [
            'checks' => $checks,
            'passed' => $byKey['medicaid_before_transfer'] !== false
                && $byKey['disposition'] === true
                && $byKey['patience'] === true
                && $byKey['no_instant_fail'] === true
                && $byKey['no_dq_transfer'] !== false
                && $byKey['dnc_respected'] !== false
                && $byKey['verbal_consent'] !== false
                && ($expectsTransfer ? $byKey['double_confirm'] === true : true)
                && $byKey['completed_after_both_spoke'] !== false,
            'correct_disposition' => $correctDisposition,
            'correct_reason' => $transferred && $neverSaidMedicaid
                ? 'You transferred without ever asking whether they receive Medicaid. That is treated as fraud and fails the call, whatever else went well.'
                : $correctReason,
        ];
    }

    /**
     * The agent turns that ask whether the consumer receives Medicaid, by
     * transcript index.
     *
     * @param  list<Line>  $lines
     * @return list<int>
     */
    private function medicaidAsks(array $lines): array
    {
        $asks = [];

        foreach ($lines as $index => $line) {
            if ($line['role'] === 'agent' && preg_match(self::MEDICAID_ASK, $this->normalize($line['text']))) {
                $asks[] = $index;
            }
        }

        return $asks;
    }

    /**
     * A later Medicaid ask that also names the borough, in its own agent
     * turn after the consumer answered the first one. Rolling it into the
     * first ask doesn't count.
     *
     * @param  list<Line>  $lines
     * @param  list<int>  $asks
     */
    private function hasSeparateDoubleConfirm(array $lines, array $asks): bool
    {
        foreach ($asks as $position => $first) {
            foreach (array_slice($asks, $position + 1) as $second) {
                $text = $this->normalize($lines[$second]['text']);
                $namesBorough = (bool) preg_match('/\bborough\b|\b(bronx|brooklyn|manhattan|queens|staten island|long island city)\b/', $text);

                if (! $namesBorough) {
                    continue;
                }

                foreach (array_slice($lines, $first + 1, $second - $first - 1) as $between) {
                    if ($between['role'] === 'consumer') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Once the screener joined, the agent named the consumer, their borough
     * and their ZIP ("I have Maria in the Bronx, the zip code is 10453").
     *
     * @param  list<Line>  $lines
     * @param  list<Event>  $events
     */
    private function handoffNamesLead(array $lines, array $events, string $name, string $borough, string $zip): bool
    {
        $joinedAt = null;

        foreach ($events as $event) {
            if ($event['type'] === 'specialist_joined') {
                $joinedAt = intdiv($event['at'] ?? 0, 1000);

                break;
            }
        }

        if ($joinedAt === null) {
            return false;
        }

        $handoff = array_values(array_filter(
            $lines,
            fn (array $line): bool => $line['role'] === 'agent' && $line['at'] >= $joinedAt,
        ));

        return $this->asksForLead($handoff, $name)
            && str_contains($this->normalize(implode("\n", array_column($handoff, 'text'))), strtolower($borough))
            && $this->mentionsZip($handoff, $zip);
    }

    /**
     * After every brush-off the consumer voiced, the agent started talking
     * again within the allowed pause, before the consumer said anything
     * more. Null when there was no brush-off or no speech timing.
     *
     * @param  list<Event>  $events
     */
    private function cameStraightBack(array $events): ?bool
    {
        $ofType = fn (string $type): array => array_values(array_filter($events, fn (array $event): bool => $event['type'] === $type));
        $consumer = $ofType('consumer_speech');
        $agentStarts = array_map(fn (array $event): int => $event['start'] ?? 0, $ofType('agent_speech'));
        $brushOffs = array_filter($ofType('objection_raised'), fn (array $event): bool => in_array($event['id'] ?? null, self::BRUSH_OFFS, true));
        $maxPause = (int) config('roleplay.meals.brush_off_pause_max_ms');
        $judged = false;

        foreach ($brushOffs as $brushOff) {
            $at = $brushOff['at'] ?? 0;

            foreach ($consumer as $index => $segment) {
                if ($at < ($segment['start'] ?? 0) - 500 || $at > ($segment['end'] ?? 0) + 2000) {
                    continue;
                }

                $end = $segment['end'] ?? 0;
                $nextConsumerStart = $consumer[$index + 1]['start'] ?? PHP_INT_MAX;
                $replies = array_filter($agentStarts, fn (int $start): bool => $start >= $end && $start < $nextConsumerStart);

                if ($replies === [] || min($replies) - $end > $maxPause) {
                    return false;
                }

                $judged = true;

                break;
            }
        }

        return $judged ? true : null;
    }
}
