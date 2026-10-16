<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Models\RoleplaySession;

/**
 * Deterministic checks over a finished ACA call, one per required step in
 * `ai-roleplay/aca/script.md` plus the warm handoff, the instant fails and
 * the forbidden words.
 *
 * Coverage is the disqualifier here, the reverse of Medicare: the agent
 * confirms no Medicare, Medicaid or work insurance, twice, as two separate
 * asks. The correct outcome is decided from what happened on the call
 * (a DNC request, a hang-up, a repeated call-back ask), not only from the
 * persona.
 *
 * @phpstan-import-type Line from RoleplayGrader
 * @phpstan-import-type Check from RoleplayGrader
 * @phpstan-import-type Event from RoleplayGrader
 */
class AcaRoleplayGrader extends RoleplayGrader
{
    protected const array BUSY_OBJECTIONS = ['busy_callback'];

    /** Who the consumer is handed to. */
    protected const string HANDOFF_PARTY = 'the licensed agent';

    /** "America's Health" or a DBA on the script. "American Health" is wrong. */
    private const string COMPANY = '/\bamericas?[\s-]*health\b|\bhealth benefits? guide\b|\bbenefits? link\b/';

    /** "ACA", "the Affordable Care Act", "Obamacare" and "Marketplace" all count. */
    private const string ACA_MENTION = '/affordable care act|\ba\.? ?c\.? ?a\b|obama ?care|market ?place/';

    /** A coverage check names all three: Medicare, Medicaid and work insurance. */
    private const string MEDICARE = '/\bmedicare\b/';

    private const string MEDICAID = '/\bmedicaid\b/';

    private const string WORK_INSURANCE = '/\b(work|employer|job|employment)\b/';

    private const string AGE_19 = '/\b19\b|\bnineteen\b/';

    /**
     * The agent's transfer ask, as the script words it or close to it
     * ("grab the senior representative", "connect you with a licensed rep").
     */
    protected const string WARM_TRANSFER_ASK = '/\b(senior|licensed)\s+(agent|representative|rep|specialist|advisor|adviser)|(get|bring|have) (them|someone|somebody|him|her|the agent|an agent) on|on the line|\b(grab|get|connect|transfer|bring) (you )?(to |with )?(a|an|the|my|our) (senior|licensed|specialist|supervisor|manager)/';

    /**
     * Disqualifiers the script never asks about directly, with how the
     * consumer brings each one up. Unless one comes up on the call, the
     * agent had no way to catch it.
     *
     * @var array<string, string>
     */
    public const array VOLUNTEERED_DISQUALIFIERS = [
        'spousal_work' => '/\b(husband|wife|spouse|partner|boyfriend|girlfriend)s?\b.*\b(job|work|employer|company)\b|\b(job|work|employer|company)\b.*\b(husband|wife|spouse|partner)/',
        'military_va' => '/\bva\b|\bveteran|\bmilitary\b|\btricare\b/',
        'private' => '/\bprivate\b|blue ?cross|\boscar\b|\bambetter\b|\banthem\b|united ?health/',
        'state_insurance' => '/\bstate\s+(health\s+)?(plan|insurance|program|coverage)\b|\b(through|from) the state\b/',
        'ssdi' => '/\bssdi?\b|\bdisability\b/',
        'non_citizen' => '/\bcitizen|\bgreen card\b|\bimmigra|\bvisa\b|\bpermanent resident\b/',
    ];

    /** The DQ line, or anything telling the consumer they don't qualify. */
    protected const string DQ_LINE = '/\bnot (be )?qualified\b|\bwouldnt (be )?qualif|\bdont qualify\b|\bnot eligible\b/';

    /** The DNC disclaimer, or anything promising no more calls. */
    protected const string DNC_DISCLAIMER = '/do not call|dont call|\bdnc\b|further calls|remove you|take you off|no more calls/';

    /** A DNC request with one of these is conditional ("if this is a scam, don't call me"). */
    protected const string CONDITIONAL = '/\b(if|unless|otherwise|or else)\b/';

    /** Rules where a preceding negative rescues the line ("I can't guarantee that"). */
    protected const array NEGATABLE_INSTANT_FAILS = ['guarantee', 'dollar_amount'];

    protected const string NEGATIVE_BEFORE = '/\b(cant|cannot|can not|dont|do not|wont|will not|not|never|no)\b/';

    /**
     * Instant fails from `04_PROMPTS_ACA.md`, matched against the agent's
     * normalised line (lowercase, apostrophes removed).
     *
     * @var array<string, array{pattern: string, reason: string}>
     */
    protected const array INSTANT_FAILS = [
        'free_money' => ['pattern' => '/\bfree\s+(money|cash)\b/', 'reason' => 'Promised free money'],
        'dollar_amount' => ['pattern' => '/\byou(ll| will|re going to| are going to)\s+(get|receive)\s+(up to\s+)?(\$\s?\d|\d[\d,]*\s*dollars)/', 'reason' => 'Promised a dollar amount'],
        'send_money' => ['pattern' => '/\b(well|ill|theyll|we will|i will|they will)\s+(send|mail)\s+you\s+(a\s+)?(check|cheque|card|money)\b/', 'reason' => 'Promised to send money or a card'],
        'government' => ['pattern' => '/\b(im|i am|were|we are|this is)\s+(\w+\s+)?(calling\s+)?(with|from)\s+(the\s+)?(government|federal government|marketplace|healthcare\.? ?gov|medicaid|social security)\b/', 'reason' => 'Claimed to be the government or the Marketplace'],
        'approved' => ['pattern' => '/\byou(ve| have)\s+(been\s+)?(approved|pre-?approved|qualified)\b/', 'reason' => 'Told the consumer they are approved or qualified'],
        'guarantee' => ['pattern' => '/\bguarantee(d)?\b[^.?!]{0,40}\b(approv|qualif|benefit|subsid|money|coverage)/', 'reason' => 'Guaranteed benefits or approval'],
        'won' => ['pattern' => '/\byou(ve| have)\s+(won|been awarded)\b/', 'reason' => 'Told the consumer they won something'],
        'financial_details' => ['pattern' => '/\b(whats|what is|read|give me|can i (get|have)|provide|spell out|tell me)\b[^.?!]{0,40}\b(social security( number)?|ssn|bank|account number|routing number|card number|credit card|debit card)\b/', 'reason' => 'Asked for a Social Security number or financial details'],
        'pitch_perfect' => ['pattern' => '/\bpitch\s*perfect\b/', 'reason' => 'Named Pitch Perfect on the call'],
        'not_sales' => ['pattern' => '/\bthis\s+(is\s+|isnt\s+)?(not\s+)?(a\s+)?(sales|sale)\s+call\b/', 'reason' => "Claimed it's not a sales call"],
    ];

    /**
     * Forbidden words whose forms differ from the listed word. "Check" only
     * counts as a noun ("a check"), never "let me check".
     *
     * @var array<string, string>
     */
    private const array FORBIDDEN_WORD_FORMS = [
        'cheque' => 'cheques?|(a|the|your|that|any) checks?',
        'grocery' => 'grocer(y|ies)',
        'amount' => 'amounts?',
        'mortgage' => 'mortgages?',
        'total' => 'totals?',
    ];

    /** Lost trainee turns from which a call is too incomplete to grade, once they outnumber the transcribed ones. */
    private const int MAX_UNTRANSCRIBED_TURNS = 3;

    /**
     * An agent line this short, made of the consumer's last words, is their
     * echo that slipped past the browser's looser echo check.
     */
    private const int ECHO_MAX_WORDS = 8;

    private const float ECHO_MATCH_RATIO = 0.8;

    /** Words too common to tell one consumer question from another. */
    private const array QUESTION_STOPWORDS = ['what', 'whats', 'this', 'that', 'thats', 'does', 'have', 'with', 'your', 'about', 'just', 'like', 'wait', 'know', 'really', 'going', 'gonna', 'howd', 'there', 'they', 'then', 'from', 'will', 'would', 'could', 'doing', 'call', 'calling'];

    /** The trainee line written for a turn whose transcription failed. */
    private const string INAUDIBLE_LINE = '(inaudible)';

    public function __construct(private readonly AcaRoleplayScript $script) {}

    /**
     * Training module roleplays aren't set up for ACA or Meals yet, so the
     * importer rejects any check a module would require.
     *
     * @return array<string, string>
     */
    public function checkLabels(): array
    {
        return [];
    }

    public function qualifyingCheckKeys(): array
    {
        return ['confirm_state', 'first_coverage_check', 'age_19', 'double_confirm'];
    }

    public function grade(RoleplaySession $session, ?int $transferClickedAt): array
    {
        $lines = $this->parseTranscript((string) $session->transcript);
        $agent = array_values(array_filter($lines, fn (array $line): bool => $line['role'] === 'agent'));
        $agentText = $this->normalize(implode("\n", array_column($agent, 'text')));
        $persona = $session->persona;
        $lead = $persona['lead'];
        $state = $persona['actual_state'] ?? $lead['state'];
        /** @var list<Event> $events */
        $events = $session->events ?? [];

        [$correctDisposition, $correctReason, $dncRequestIndex] = $this->correctAcaDisposition($session, $lines, $events, $transferClickedAt);
        $expectsTransfer = $correctDisposition === Outcome::Transfer->value;
        $isDisqualified = $persona['dq_trap'] !== null && $this->disqualifierCameUp($persona['dq_trap']['id'], $lines);

        $coverageAsks = $this->coverageAsks($lines);
        $handoff = $expectsTransfer ? $this->transferHandoff($events) : null;
        $instantFail = $this->instantFail($agent);
        [$pitchWords, $holdWords, $pitchQuote, $holdQuote] = $this->forbiddenWordsUsed($lines, $transferClickedAt);

        $checks = [
            $this->check('correct_person', 'Confirmed you had the right person',
                $this->asksForLead($agent, $lead['name'])),
            $this->check('company_recorded_line', "Said you're with America's Health on a recorded line",
                str_contains($agentText, 'recorded line') && (bool) preg_match(self::COMPANY, $agentText)),
            $this->check('aca_mention', 'Explained the subsidy through the Affordable Care Act',
                (bool) preg_match(self::ACA_MENTION, $agentText)),
            $this->check('confirm_state', "Confirmed the state ({$state})",
                str_contains($agentText, strtolower($state))),
            $this->check('first_coverage_check', 'Asked: no Medicare, Medicaid or work insurance',
                $coverageAsks !== []),
            $this->check('age_19', 'Confirmed they are 19 or older',
                (bool) preg_match(self::AGE_19, $agentText)),
            $this->check('double_confirm', 'Double-confirmed no Medicare, Medicaid or work insurance as a separate ask, after their first answer',
                $expectsTransfer || $transferClickedAt !== null ? $this->hasSeparateDoubleConfirm($lines, $coverageAsks) : null),
            $this->check('verbal_consent', 'Heard them say okay to the transfer before conferencing',
                $expectsTransfer ? $this->hasTransferConsent($lines, $transferClickedAt) : null),
            $this->check('fluffed', 'Kept them talking while the conference rang',
                $handoff['fluffed'] ?? null),
            $this->check('handoff_names_state', 'Introduced them to the licensed agent, naming their state',
                $expectsTransfer ? $this->handoffNamesState($lines, $events, $state) : null),
            $this->check('completed_after_both_spoke', 'Left the conference after the consumer and the licensed agent both spoke',
                $handoff['completed_after_both_spoke'] ?? null),
            $this->check('no_dq_transfer', 'Did not conference a disqualified consumer',
                $isDisqualified ? $transferClickedAt === null : null),
            $this->check('dq_line', 'Read the DQ line',
                $correctDisposition === Outcome::Dq->value ? (bool) preg_match(static::DQ_LINE, $agentText) : null),
            $this->check('dnc_respected', 'Read the DNC disclaimer and ended without a rebuttal',
                $dncRequestIndex === null ? null : $this->respectedDnc($lines, $dncRequestIndex)),
            $this->check('no_promises', 'Kept benefit talk hedged ("may", "might", "could"), with no promises',
                $this->hedgesPromises($agent)),
            $this->check('forbidden_words_pitch', $pitchWords === []
                ? 'Kept forbidden words out of the pitch'
                : 'Kept forbidden words out of the pitch (you said: '.implode(', ', $pitchWords)." in “{$pitchQuote}”)",
                $pitchWords === []),
            $this->check('forbidden_words_hold', $holdWords === []
                ? 'Kept forbidden words out of the small talk on hold (coaching note)'
                : 'Kept forbidden words out of the small talk on hold (coaching note, you said: '.implode(', ', $holdWords)." in “{$holdQuote}”)",
                $transferClickedAt === null ? null : $holdWords === []),
            $this->check('back_to_verification', 'Went back to the verification after each objection (coaching note)',
                $this->returnedToVerification($lines, $events, $state, $transferClickedAt)),
            $this->check('answered_stacked_questions', 'Answered every question when they asked several at once (coaching note)',
                $this->answeredStackedQuestions($lines, $transferClickedAt)),
            $this->check('waited_for_answers', 'Waited for the answer to every required question (coaching note)',
                $this->waitedForAnswers($lines, $state, $transferClickedAt)),
            $this->check('no_instant_fail', $instantFail === null
                ? 'No instant-fail lines'
                : "No instant-fail lines (instant fail: {$instantFail})",
                $instantFail === null),
            $this->check('disposition', 'Coded the call correctly',
                $session->disposition === $correctDisposition),
            $this->check('patience', "Didn't run out of patience",
                $session->end_reason !== 'hung_up' || $this->closedBeforeHangUp($lines, $dncRequestIndex)),
        ];

        $byKey = array_column($checks, 'passed', 'key');

        if (! $this->agentSpeechCaptured($agent, $events)) {
            return [
                'checks' => [],
                'passed' => false,
                'correct_disposition' => $correctDisposition,
                'correct_reason' => $correctReason,
                'not_graded_reason' => "What you said on this call wasn't captured (your microphone audio didn't come through as text), so it wasn't graded. Check your microphone and try the call again.",
            ];
        }

        return [
            'checks' => $checks,
            'passed' => $byKey['disposition'] === true
                && $byKey['patience'] === true
                && $byKey['no_instant_fail'] === true
                && $byKey['no_dq_transfer'] !== false
                && $byKey['dnc_respected'] !== false
                && $byKey['verbal_consent'] !== false
                && ($expectsTransfer ? $byKey['double_confirm'] === true : true)
                && $byKey['completed_after_both_spoke'] !== false,
            'correct_disposition' => $correctDisposition,
            'correct_reason' => $correctReason,
        ];
    }

    /**
     * The disposition the call should be coded as, from what happened:
     * a real DNC request wins, then a hang-up (Hung-Up Transfer once the
     * conference was clicked, DQ when a disqualified consumer hung up after
     * the DQ line, Not Interested before), then a second
     * call-back ask after the rebuttal, then the persona's outcome.
     *
     * @param  list<Line>  $lines
     * @param  list<Event>  $events
     * @return array{0: string, 1: string|null, 2: int|null}
     */
    protected function correctAcaDisposition(RoleplaySession $session, array $lines, array $events, ?int $transferClickedAt): array
    {
        foreach ($lines as $index => $line) {
            if ($line['role'] === 'consumer' && $this->isDncRequest($line['text'])) {
                return [Outcome::Dnc->value, 'They asked not to be called, so this call is a DNC: read the DNC disclaimer, end the call and code it DNC. No rebuttal.', $index];
            }
        }

        $trap = $session->persona['dq_trap'];
        $isDisqualified = $trap !== null && $this->disqualifierCameUp($trap['id'], $lines);

        if ($session->end_reason === 'hung_up') {
            if ($transferClickedAt === null && $isDisqualified && $this->readDqLine($lines)) {
                return [Outcome::Dq->value, 'They hung up after you read the DQ line, so the call is still a DQ.', null];
            }

            return $transferClickedAt === null
                ? ['not_interested', 'They hung up before you conferenced '.static::HANDOFF_PARTY.', so the call is Not Interested.', null]
                : ['hung_up_transfer', 'They hung up after you clicked conference, so the call is a Hung-Up Transfer.', null];
        }

        if ($this->saidBusyAfterRebuttal($lines, $events)) {
            return ['call_back', 'They asked to be called back again after your rebuttal, so the call should be coded Call Back.', null];
        }

        if ($trap !== null && ! $isDisqualified) {
            return [Outcome::Transfer->value, "Their disqualifier ({$trap['hidden_truth']}) never came up on the call, so it's graded as a Transfer.", null];
        }

        return [$session->expected_outcome->value, null, null];
    }

    /**
     * Whether the consumer said something that gives the disqualifier
     * away. Medicare, Medicaid and work insurance are asked about on every
     * call, so missing those is on the agent.
     *
     * @param  list<Line>  $lines
     */
    protected function disqualifierCameUp(string $trapId, array $lines): bool
    {
        $pattern = self::VOLUNTEERED_DISQUALIFIERS[$trapId] ?? null;

        if ($pattern === null) {
            return true;
        }

        foreach ($lines as $line) {
            if ($line['role'] === 'consumer' && preg_match($pattern, $this->normalize($line['text']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether enough of the trainee's speech made it into the transcript to
     * grade. Not when they spoke (the mic picked them up) but none of it
     * was transcribed, or when more of their turns were lost than kept.
     *
     * @param  list<Line>  $agent
     * @param  list<Event>  $events
     */
    private function agentSpeechCaptured(array $agent, array $events): bool
    {
        $transcribed = count(array_filter($agent, fn (array $line): bool => trim($line['text']) !== self::INAUDIBLE_LINE));
        $types = array_column($events, 'type');
        $untranscribed = count(array_keys($types, 'agent_turn_untranscribed', true));

        if ($transcribed === 0) {
            return ! in_array('agent_speech', $types, true) && $untranscribed === 0;
        }

        return $untranscribed < self::MAX_UNTRANSCRIBED_TURNS || $untranscribed < $transcribed;
    }

    /**
     * The agent told the consumer they don't qualify.
     *
     * @param  list<Line>  $lines
     */
    protected function readDqLine(array $lines): bool
    {
        foreach ($lines as $line) {
            if ($line['role'] === 'agent' && preg_match(static::DQ_LINE, $this->normalize($line['text']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The agent had already closed the call (the DQ line, or the DNC
     * disclaimer after a DNC request), so the consumer hanging up after it
     * isn't lost patience.
     *
     * @param  list<Line>  $lines
     */
    private function closedBeforeHangUp(array $lines, ?int $dncRequestIndex): bool
    {
        return $this->readDqLine($lines)
            || ($dncRequestIndex !== null && $this->respectedDnc($lines, $dncRequestIndex));
    }

    /**
     * A direct, unconditional request not to be called. "If this is a scam,
     * don't call me again" is not one, and neither is swearing.
     */
    protected function isDncRequest(string $text): bool
    {
        foreach (preg_split('/[.!?]+/', $this->normalize($text)) ?: [] as $sentence) {
            if (preg_match(self::DNC_REQUEST, $sentence) && ! preg_match(static::CONDITIONAL, $sentence)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The next thing the agent said after a DNC request has to be the
     * disclaimer, not a rebuttal.
     *
     * @param  list<Line>  $lines
     */
    protected function respectedDnc(array $lines, int $requestIndex): bool
    {
        foreach (array_slice($lines, $requestIndex + 1) as $line) {
            if ($line['role'] === 'agent') {
                return (bool) preg_match(static::DNC_DISCLAIMER, $this->normalize($line['text']));
            }
        }

        return false;
    }

    /**
     * After every objection before the conference, the agent's next turn
     * brings the call back to the verification (state, coverage, age),
     * the third beat of every approved rebuttal. Null without objections.
     *
     * @param  list<Line>  $lines
     * @param  list<Event>  $events
     */
    private function returnedToVerification(array $lines, array $events, string $state, ?int $transferClickedAt): ?bool
    {
        $verification = '/'.preg_quote(strtolower($state), '/').'|\bstate\b|'.trim(self::MEDICARE, '/').'|'.trim(self::MEDICAID, '/').'|work insurance|employer|'.trim(self::AGE_19, '/').'|market ?place/';
        $checked = false;

        foreach ($events as $event) {
            if ($event['type'] !== 'objection_raised') {
                continue;
            }

            $raisedAt = intdiv($event['at'] ?? 0, 1000);

            if ($transferClickedAt !== null && $raisedAt >= $transferClickedAt) {
                continue;
            }

            $turn = $this->agentTurnAfter($lines, $raisedAt);

            if ($turn === null) {
                continue;
            }

            if (! preg_match($verification, $this->normalize($turn))) {
                return false;
            }

            $checked = true;
        }

        return $checked ? true : null;
    }

    /**
     * The agent's first turn starting at or after `$at`, consecutive agent
     * lines joined, or null when they never spoke again.
     *
     * @param  list<Line>  $lines
     */
    private function agentTurnAfter(array $lines, int $at): ?string
    {
        $turn = [];

        foreach ($lines as $line) {
            if ($line['at'] < $at) {
                continue;
            }

            if ($line['role'] === 'agent') {
                $turn[] = $line['text'];
            } elseif ($turn !== []) {
                break;
            }
        }

        return $turn === [] ? null : implode(' ', $turn);
    }

    /**
     * Every required question (state, coverage, age, the transfer ask) that
     * ends an agent line is followed by the consumer, not by the agent
     * talking on.
     *
     * @param  list<Line>  $lines
     */
    private function waitedForAnswers(array $lines, string $state, ?int $transferClickedAt): bool
    {
        $required = '/'.preg_quote(strtolower($state), '/').'|'.trim(self::MEDICARE, '/').'|'.trim(self::MEDICAID, '/').'|'.trim(self::AGE_19, '/').'|'.trim(static::WARM_TRANSFER_ASK, '/').'/';

        foreach ($lines as $index => $line) {
            if ($line['role'] !== 'agent' || ($transferClickedAt !== null && $line['at'] >= $transferClickedAt)) {
                continue;
            }

            $sentences = preg_split('/(?<=[.!?])\s+/', trim($this->normalize($line['text']))) ?: [];
            $last = (string) end($sentences);
            $next = $lines[$index + 1] ?? null;

            if (str_ends_with($last, '?') && preg_match($required, $last) && $next !== null && $next['role'] === 'agent') {
                return false;
            }
        }

        return true;
    }

    /**
     * When the consumer asks two or more questions in one turn before the
     * conference, the agent's answer covers all of them: the consumer's
     * next line doesn't ask one of them again. Null without such a turn.
     *
     * @param  list<Line>  $lines
     */
    private function answeredStackedQuestions(array $lines, ?int $transferClickedAt): ?bool
    {
        $checked = false;

        foreach ($lines as $index => $line) {
            if ($line['role'] !== 'consumer' || ($transferClickedAt !== null && $line['at'] >= $transferClickedAt)) {
                continue;
            }

            $questions = $this->questionTopics($line['text']);

            if (count($questions) < 2) {
                continue;
            }

            $reply = $this->consumerLineAfterAgentTurn($lines, $index);

            if ($reply === null) {
                continue;
            }

            $checked = true;

            foreach ($this->questionTopics($reply) as $asked) {
                foreach ($questions as $question) {
                    if (array_intersect($asked, $question) !== []) {
                        return false;
                    }
                }
            }
        }

        return $checked ? true : null;
    }

    /**
     * The distinctive words of each question in a line ("does it cost me
     * anything?" is `cost, anything`). Questions with none ("what is this?")
     * are left out, since a repeat of them can't be told apart.
     *
     * @return list<list<string>>
     */
    private function questionTopics(string $text): array
    {
        $topics = [];

        foreach (preg_split('/(?<=[.!?])\s+/', $this->normalize($text)) ?: [] as $sentence) {
            if (! str_ends_with(trim($sentence), '?')) {
                continue;
            }

            $words = array_values(array_diff(
                array_filter(preg_split('/[^a-z0-9]+/', $sentence, -1, PREG_SPLIT_NO_EMPTY) ?: [], fn (string $word): bool => strlen($word) >= 4),
                self::QUESTION_STOPWORDS,
            ));

            if ($words !== []) {
                $topics[] = $words;
            }
        }

        return $topics;
    }

    /**
     * The consumer's first line after the agent's next turn, or null when
     * the agent never answered or the consumer never spoke again.
     *
     * @param  list<Line>  $lines
     */
    private function consumerLineAfterAgentTurn(array $lines, int $index): ?string
    {
        $agentAnswered = false;

        foreach (array_slice($lines, $index + 1) as $line) {
            if ($line['role'] === 'agent') {
                $agentAnswered = true;
            } elseif ($line['role'] === 'consumer' && $agentAnswered) {
                return $line['text'];
            }
        }

        return null;
    }

    /**
     * The agent turns that ask about all three coverages, by transcript
     * index.
     *
     * @param  list<Line>  $lines
     * @return list<int>
     */
    private function coverageAsks(array $lines): array
    {
        $asks = [];

        foreach ($lines as $index => $line) {
            $text = $this->normalize($line['text']);

            if ($line['role'] === 'agent'
                && preg_match(self::MEDICARE, $text)
                && preg_match(self::MEDICAID, $text)
                && preg_match(self::WORK_INSURANCE, $text)) {
                $asks[] = $index;
            }
        }

        return $asks;
    }

    /**
     * Two coverage asks in separate agent turns, with the consumer
     * answering in between. Rolling everything into one sentence asked
     * once doesn't count.
     *
     * @param  list<Line>  $lines
     * @param  list<int>  $asks
     */
    private function hasSeparateDoubleConfirm(array $lines, array $asks): bool
    {
        if (count($asks) < 2) {
            return false;
        }

        foreach ($asks as $position => $first) {
            foreach (array_slice($asks, $position + 1) as $second) {
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
     * The agent asked for the lead by first or last name.
     *
     * @param  list<Line>  $agent
     */
    protected function asksForLead(array $agent, string $name): bool
    {
        $text = $this->normalize(implode("\n", array_column($agent, 'text')));

        foreach (explode(' ', strtolower($name)) as $part) {
            if ($part !== '' && preg_match('/\b'.preg_quote($part, '/').'\b/', $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A consumer okay after the agent's last transfer ask and before the
     * conference click. A question back ("what's this agent gonna do?")
     * doesn't cancel an okay, but a later no does. Silence is not consent.
     *
     * @param  list<Line>  $lines
     */
    protected function hasTransferConsent(array $lines, ?int $transferClickedAt): bool
    {
        if ($transferClickedAt === null) {
            return false;
        }

        $askedAt = null;

        foreach ($lines as $index => $line) {
            if ($line['at'] > $transferClickedAt) {
                break;
            }

            if ($line['role'] === 'agent' && preg_match(static::WARM_TRANSFER_ASK, $this->normalize($line['text']))) {
                $askedAt = $index;
            }
        }

        if ($askedAt === null) {
            return false;
        }

        $consented = false;

        foreach (array_slice($lines, $askedAt + 1) as $line) {
            if ($line['at'] > $transferClickedAt) {
                break;
            }

            if ($line['role'] !== 'consumer') {
                continue;
            }

            $text = $this->normalize($line['text']);

            if ($this->isAffirmative($text)) {
                $consented = true;
            } elseif (preg_match('/^\W*(no|nope|nah|not now|not right now|dont)\b/', $text)) {
                $consented = false;
            }
        }

        return $consented;
    }

    /**
     * Once the licensed agent joined, the agent named the consumer's state
     * to them ("I have Ashley from Texas, please take the call").
     *
     * @param  list<Line>  $lines
     * @param  list<Event>  $events
     */
    private function handoffNamesState(array $lines, array $events, string $state): bool
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

        foreach ($lines as $line) {
            if ($line['role'] === 'agent' && $line['at'] >= $joinedAt && str_contains($this->normalize($line['text']), strtolower($state))) {
                return true;
            }
        }

        return false;
    }

    /**
     * No sentence tells the lead they qualify, are eligible or will get
     * something without a hedge. The DQ line and other negatives aren't
     * promises.
     *
     * @param  list<Line>  $agent
     */
    private function hedgesPromises(array $agent): bool
    {
        foreach ($agent as $line) {
            foreach (preg_split('/[.!?]+/', $this->normalize($line['text'])) ?: [] as $sentence) {
                $promises = preg_match('/\byou(re| are)? (qualify|eligible|entitled)\b|\byou(ll| will| are going to|re going to) (get|receive|be getting)\b|\byou qualify\b/', $sentence);

                if ($promises && ! preg_match('/\b(may|maybe|might|could)\b/', $sentence) && ! preg_match(static::NEGATIVE_BEFORE, $sentence)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The first instant-fail line the agent said, as "reason: “quote”".
     *
     * @param  list<Line>  $agent
     */
    protected function instantFail(array $agent): ?string
    {
        foreach ($agent as $line) {
            foreach (preg_split('/(?<=[.!?])\s+/', $this->normalize($line['text'])) ?: [] as $sentence) {
                foreach (static::INSTANT_FAILS as $key => $rule) {
                    if (! preg_match($rule['pattern'], $sentence, $match, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }

                    $before = substr($sentence, 0, $match[0][1]);

                    if (in_array($key, static::NEGATABLE_INSTANT_FAILS, true) && preg_match(static::NEGATIVE_BEFORE, $before)) {
                        continue;
                    }

                    return "{$rule['reason']}: “{$line['text']}”";
                }
            }
        }

        return null;
    }

    /**
     * Forbidden words the agent said, split by where: in the pitch (before
     * the conference click) and in the small talk while it rang, each with
     * the first line it was said in. Only the agent's own words count, so
     * a short line that is the consumer's last line heard back through the
     * mic is skipped.
     *
     * @param  list<Line>  $lines
     * @return array{0: list<string>, 1: list<string>, 2: string|null, 3: string|null}
     */
    private function forbiddenWordsUsed(array $lines, ?int $transferClickedAt): array
    {
        $pitch = [];
        $hold = [];
        $pitchQuote = null;
        $holdQuote = null;
        $lastConsumerLine = null;

        foreach ($lines as $line) {
            if ($line['role'] === 'consumer') {
                $lastConsumerLine = $line['text'];

                continue;
            }

            if ($line['role'] !== 'agent' || ($lastConsumerLine !== null && $this->echoesConsumer($line['text'], $lastConsumerLine))) {
                continue;
            }

            $text = $this->normalize($line['text']);
            $onHold = $transferClickedAt !== null && $line['at'] >= $transferClickedAt;

            foreach ($this->script->forbiddenWords() as $word) {
                $form = self::FORBIDDEN_WORD_FORMS[$word] ?? preg_quote($word, '/');

                if (! preg_match('/\b('.$form.')\b/', $text)) {
                    continue;
                }

                if ($onHold) {
                    $hold[$word] = $word;
                    $holdQuote ??= $line['text'];
                } else {
                    $pitch[$word] = $word;
                    $pitchQuote ??= $line['text'];
                }
            }
        }

        return [array_values($pitch), array_values($hold), $pitchQuote, $holdQuote];
    }

    /**
     * A short agent line made almost entirely of the consumer's last line's
     * words: their voice picked up by the mic, not the agent talking.
     */
    private function echoesConsumer(string $agentText, string $consumerText): bool
    {
        $words = fn (string $text): array => preg_split('/[^a-z0-9]+/', $this->normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $agentWords = $words($agentText);

        if ($agentWords === [] || count($agentWords) > self::ECHO_MAX_WORDS) {
            return false;
        }

        $matched = count(array_intersect($agentWords, $words($consumerText)));

        return $matched / count($agentWords) >= self::ECHO_MATCH_RATIO;
    }

    protected function normalize(string $text): string
    {
        return strtolower(str_replace(['’', '‘', "'", '`'], '', $text));
    }
}
