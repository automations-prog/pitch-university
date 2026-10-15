<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Models\RoleplaySession;

/**
 * Deterministic checks over a finished roleplay call's transcript and event
 * log, one per compliance rule in `medicare_script.md` plus the cold
 * transfer, the disposition and patience.
 *
 * The transcript comes from the browser, so a trainee who edits the upload
 * can pass. Accepted for practice; grade from a server-side transcription
 * of the recording before results gate anything.
 *
 * @phpstan-type Line array{at: int, role: string, text: string}
 * @phpstan-type Check array{key: string, label: string, passed: bool|null}
 * @phpstan-type Event array{type: string, at?: int, start?: int, end?: int, id?: string}
 */
class RoleplayGrader
{
    /** Words a consumer uses to say yes to the transfer ask. */
    protected const string AFFIRMATIVE = '/\b(yes|yeah|yep|yup|ok|okay|sure|alright|all right|fine|go ahead|uh-huh|mm-hmm|i guess)\b/';

    protected const string NEGATIVE = '/\b(no|nope|nah|dont|cant|wont)\b/';

    /** "Parts A and B" as transcription writes it: "Part A and Part B", "A & B", "A&B", "Medicare A and B", "medical parts AMB". */
    protected const string PARTS_A_AND_B = '/\b(parts?|medicare|medical) (a,? ?(and|&|n) ?(part )?b|a[mn]b)\b/';

    /** "Red, white and blue", with or without commas or the "and". */
    protected const string RED_WHITE_BLUE = '/red,? white,?( and| &)? blue/';

    /** "America's Health", however it was transcribed ("Americas Health", "America Health"). */
    protected const string AMERICAS_HEALTH = '/\bamericas?[\s-]*health\b/';

    /** The agent's transfer ask, as the script words it. */
    protected const string TRANSFER_ASK = '/few minutes|specialist is coming|coming on the line/';

    /** Objections answered with the busy rebuttal. */
    protected const array BUSY_OBJECTIONS = ['busy', 'too_many_calls'];

    /**
     * A consumer saying they're busy, for when the tool call was missed.
     * Covers the scripted busy lines ("I'm at the doctor, I can't talk")
     * and the ways people repeat it ("I don't have time", "I gotta go").
     */
    protected const string BUSY = '/\b(busy|call (me )?back|cant talk|bad time|(not|isnt) a good time|(dont|do not) have (the )?time|(got|have) no time|driving|in the middle of|at (the )?(doctors?|hospital|work|store)|(gotta|got to|have to|need to) go|in a (hurry|rush))\b/';

    /**
     * A consumer asking not to be called, or threatening, per the DNC
     * disposition. Swearing or complaining about calls alone doesn't count,
     * and "don't call me back" is a busy, not a DNC.
     */
    protected const string DNC_REQUEST = '/\b(stop|quit) calling\b|\bnever call\b|\b(dont|do not) call (me|here|this number)\b(?! back)|\btake me off\b|\bremove me\b|\b(do not|dont) call list\b|\bdnc\b|\b(sue|lawyer|attorney)\b|\breport (you|this)\b/';

    /**
     * What each check grades, by key.
     *
     * @var array<string, string>
     */
    protected const array CHECK_LABELS = [
        'recorded_line' => "Said you're on a recorded line with America's Health",
        'double_confirm' => 'Asked about Parts A and B and double-confirmed the red, white and blue card',
        'state_zip' => 'Confirmed state and ZIP',
        'work_va' => 'Asked about insurance through work or the VA',
        'specialist' => "Told them they're being transferred to a specialist",
        'transfer_permission' => 'Got a yes after the transfer ask, before clicking Transfer',
        'fluffed' => 'Kept them talking while the transfer rang',
        'silent_on_connect' => 'Stayed quiet once the specialist joined',
        'completed_after_both_spoke' => 'Completed the transfer after the lead and the specialist both spoke',
        'may_maybe' => 'Used "may" or "maybe" on benefit claims',
        'disposition' => 'Coded the call correctly',
        'patience' => "Didn't run out of patience",
    ];

    /**
     * The checks a training module's roleplay may require, with what each
     * one grades.
     *
     * @return array<string, string>
     */
    public function checkLabels(): array
    {
        return static::CHECK_LABELS;
    }

    /**
     * The checks that cover qualifying the lead, for the delivery score's
     * qualifying accuracy.
     *
     * @return list<string>
     */
    public function qualifyingCheckKeys(): array
    {
        return ['double_confirm', 'state_zip', 'work_va'];
    }

    /**
     * @return array{checks: list<Check>, passed: bool, correct_disposition: string, correct_reason: string|null}
     */
    public function grade(RoleplaySession $session, ?int $transferClickedAt): array
    {
        $lines = $this->parseTranscript((string) $session->transcript);
        $agent = array_values(array_filter($lines, fn (array $line): bool => $line['role'] === 'agent'));
        $agentText = $this->normalize(implode("\n", array_column($agent, 'text')));
        $lead = $session->persona['lead'];
        /** @var list<Event> $events */
        $events = $session->events ?? [];

        [$correctDisposition, $correctReason] = $this->correctDisposition($session, $lines, $events);
        $expectsTransfer = $correctDisposition === Outcome::Transfer->value;
        $handoff = $expectsTransfer ? $this->transferHandoff($events) : null;

        $checks = [
            $this->check('recorded_line', self::CHECK_LABELS['recorded_line'],
                str_contains($agentText, 'recorded line') && (bool) preg_match(self::AMERICAS_HEALTH, $agentText)),
            $this->check('double_confirm', self::CHECK_LABELS['double_confirm'],
                (bool) preg_match(self::PARTS_A_AND_B, $agentText) && (bool) preg_match(self::RED_WHITE_BLUE, $agentText)),
            $this->check('state_zip', self::CHECK_LABELS['state_zip'],
                str_contains($agentText, strtolower($lead['state'])) && $this->mentionsZip($agent, $lead['zip'])),
            $this->check('work_va', self::CHECK_LABELS['work_va'],
                (bool) preg_match('/\b(work|employer)\b/', $agentText) && (bool) preg_match('/\bv\.? ?a\b|veteran/', $agentText)),
            $this->check('specialist', self::CHECK_LABELS['specialist'],
                str_contains($agentText, 'specialist')),
            $this->check('transfer_permission', self::CHECK_LABELS['transfer_permission'],
                $expectsTransfer ? $this->hasTransferPermission($lines, $transferClickedAt) : null),
            $this->check('fluffed', self::CHECK_LABELS['fluffed'],
                $handoff['fluffed'] ?? null),
            $this->check('silent_on_connect', self::CHECK_LABELS['silent_on_connect'],
                $handoff['silent_on_connect'] ?? null),
            $this->check('completed_after_both_spoke', self::CHECK_LABELS['completed_after_both_spoke'],
                $handoff['completed_after_both_spoke'] ?? null),
            $this->check('may_maybe', self::CHECK_LABELS['may_maybe'],
                $this->hedgesBenefitClaims($agent)),
            $this->check('disposition', self::CHECK_LABELS['disposition'],
                $session->disposition === $correctDisposition),
            $this->check('patience', self::CHECK_LABELS['patience'],
                $session->end_reason !== 'hung_up'),
        ];

        $byKey = array_column($checks, 'passed', 'key');

        return [
            'checks' => $checks,
            'passed' => $byKey['disposition'] === true
                && $byKey['patience'] === true
                && $byKey['transfer_permission'] !== false
                && $byKey['completed_after_both_spoke'] !== false,
            'correct_disposition' => $correctDisposition,
            'correct_reason' => $correctReason,
        ];
    }

    /**
     * The disposition the call should be coded as. Usually the persona's
     * outcome, but what the consumer actually did wins: asking not to be
     * called is DNC, and a second busy after the rebuttal is Not Interested
     * (rebut once, then let the call go).
     *
     * @param  list<Line>  $lines
     * @param  list<Event>  $events
     * @return array{0: string, 1: string|null}
     */
    protected function correctDisposition(RoleplaySession $session, array $lines, array $events): array
    {
        $consumer = array_values(array_filter($lines, fn (array $line): bool => $line['role'] === 'consumer'));

        foreach ($consumer as $line) {
            if (preg_match(self::DNC_REQUEST, $this->normalize($line['text']))) {
                return [Outcome::Dnc->value, 'They asked not to be called, so this call is a DNC: read the DNC disclaimer and code it DNC.'];
            }
        }

        if ($this->saidBusyAfterRebuttal($lines, $events)) {
            return ['not_interested', 'They said they were busy again after your rebuttal, so the call should be let go and coded Not Interested.'];
        }

        return [$session->expected_outcome->value, null];
    }

    /**
     * A busy objection, the agent speaking, then another busy objection.
     * From the logged objections, or from the transcript when the model
     * didn't report the repeat.
     *
     * @param  list<Line>  $lines
     * @param  list<Event>  $events
     */
    protected function saidBusyAfterRebuttal(array $lines, array $events): bool
    {
        $busyAt = [];
        $agentSpokeAt = [];

        foreach ($events as $event) {
            if ($event['type'] === 'objection_raised' && in_array($event['id'] ?? null, static::BUSY_OBJECTIONS, true)) {
                $busyAt[] = $event['at'] ?? 0;
            }

            if ($event['type'] === 'agent_speech') {
                $agentSpokeAt[] = $event['start'] ?? 0;
            }
        }

        if ($this->repeatsAfterReply($busyAt, $agentSpokeAt)) {
            return true;
        }

        $busyAt = [];
        $agentSpokeAt = [];

        foreach ($lines as $index => $line) {
            if ($line['role'] === 'agent') {
                $agentSpokeAt[] = $index;
            } elseif (preg_match(self::BUSY, $this->normalize($line['text']))) {
                $busyAt[] = $index;
            }
        }

        return $this->repeatsAfterReply($busyAt, $agentSpokeAt);
    }

    /**
     * Whether two of `$times` have one of `$replies` between them.
     *
     * @param  list<int>  $times
     * @param  list<int>  $replies
     */
    protected function repeatsAfterReply(array $times, array $replies): bool
    {
        if (count($times) < 2) {
            return false;
        }

        sort($times);
        $first = $times[0];
        $last = $times[array_key_last($times)];

        foreach ($replies as $reply) {
            if ($reply > $first && $reply < $last) {
                return true;
            }
        }

        return false;
    }

    /**
     * The cold transfer from the event log: fluff while it rings, silence
     * once the specialist joins, and leaving only after the lead answered
     * the specialist.
     *
     * @param  list<Event>  $events
     * @return array{fluffed: bool, silent_on_connect: bool|null, completed_after_both_spoke: bool}
     */
    protected function transferHandoff(array $events): array
    {
        $firstAt = function (string $type) use ($events): ?int {
            foreach ($events as $event) {
                if ($event['type'] === $type) {
                    return $event['at'] ?? null;
                }
            }

            return null;
        };

        $clickedAt = $firstAt('transfer_clicked');
        $joinedAt = $firstAt('specialist_joined');
        $answeredAt = $firstAt('lead_answered_specialist');
        $completedAt = $firstAt('transfer_completed');

        $fluffMs = 0;

        if ($clickedAt !== null && $joinedAt !== null) {
            foreach ($events as $event) {
                if ($event['type'] === 'agent_speech') {
                    $fluffMs += max(0, min($event['end'] ?? 0, $joinedAt) - max($event['start'] ?? 0, $clickedAt));
                }
            }
        }

        return [
            'fluffed' => $joinedAt !== null && $fluffMs >= (int) config('roleplay.transfer.fluff_min_ms'),
            'silent_on_connect' => $joinedAt === null ? null : $firstAt('agent_talked_on_connect') === null,
            'completed_after_both_spoke' => $answeredAt !== null && $completedAt !== null && $completedAt >= $answeredAt,
        ];
    }

    /**
     * Transcript lines look like `[1:05] agent: text`.
     *
     * @return list<Line>
     */
    public function parseTranscript(string $transcript): array
    {
        preg_match_all('/^\[(\d+):(\d{2})\] (agent|consumer): (.*)$/m', $transcript, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => [
            'at' => (int) $match[1] * 60 + (int) $match[2],
            'role' => $match[3],
            'text' => trim($match[4]),
        ], $matches);
    }

    /**
     * The trainee needs a consumer yes after their last transfer ask and
     * before they clicked Transfer. The script calls it the only
     * permission asked for in the call.
     *
     * @param  list<Line>  $lines
     */
    protected function hasTransferPermission(array $lines, ?int $transferClickedAt): bool
    {
        if ($transferClickedAt === null) {
            return false;
        }

        $askedAt = null;

        foreach ($lines as $index => $line) {
            if ($line['at'] > $transferClickedAt) {
                break;
            }

            if ($line['role'] === 'agent' && preg_match(self::TRANSFER_ASK, $this->normalize($line['text']))) {
                $askedAt = $index;
            }
        }

        if ($askedAt === null) {
            return false;
        }

        foreach (array_slice($lines, $askedAt + 1) as $line) {
            if ($line['at'] > $transferClickedAt) {
                break;
            }

            if ($line['role'] !== 'consumer') {
                continue;
            }

            return $this->isAffirmative($this->normalize($line['text']));
        }

        return false;
    }

    /**
     * A reply that opens with a yes counts ("Sure, why not"), otherwise any
     * no wins ("I guess not, no").
     */
    protected function isAffirmative(string $text): bool
    {
        $opening = ltrim($text, " \t.,!?-");

        if (preg_match(str_replace('/\b(', '/^(', self::AFFIRMATIVE), $opening)) {
            return true;
        }

        return ! preg_match(self::NEGATIVE, $text) && (bool) preg_match(self::AFFIRMATIVE, $text);
    }

    /**
     * Every sentence that tells the lead they qualify or are entitled must
     * hedge with "may" or "maybe". Claims about other people, like the
     * script's "MANY people ... ACTUALLY qualify" rebuttal, don't need to.
     *
     * @param  list<Line>  $agent
     */
    protected function hedgesBenefitClaims(array $agent): bool
    {
        foreach ($agent as $line) {
            foreach (preg_split('/[.!?]+/', $this->normalize($line['text'])) ?: [] as $sentence) {
                $isAboutTheLead = (bool) preg_match('/\b(you|youre|your)\b/', $sentence);

                if ($isAboutTheLead
                    && preg_match('/\b(qualify|qualifies|entitled|eligible)\b/', $sentence)
                    && ! preg_match('/\bmay(be)?\b/', $sentence)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Transcription may write a ZIP as "33511" or "3 3 5 1 1", so compare
     * digits only, one line at a time.
     *
     * @param  list<Line>  $agent
     */
    protected function mentionsZip(array $agent, string $zip): bool
    {
        foreach ($agent as $line) {
            if (str_contains((string) preg_replace('/\D/', '', $line['text']), $zip)) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(string $text): string
    {
        return strtolower(str_replace(['’', "'"], '', $text));
    }

    /**
     * @return Check
     */
    protected function check(string $key, string $label, ?bool $passed): array
    {
        return ['key' => $key, 'label' => $label, 'passed' => $passed];
    }
}
