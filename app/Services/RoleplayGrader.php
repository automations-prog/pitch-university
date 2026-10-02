<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Models\RoleplaySession;

/**
 * Deterministic checks over a finished roleplay call's transcript, one per
 * compliance rule in `medicare_script.md` plus the persona's outcome and
 * patience.
 *
 * The transcript comes from the browser, so a trainee who edits the upload
 * can pass. Accepted for practice; grade from a server-side transcription
 * of the recording before results gate anything.
 *
 * @phpstan-type Line array{at: int, role: string, text: string}
 * @phpstan-type Check array{key: string, label: string, passed: bool|null}
 */
class RoleplayGrader
{
    /** Words a consumer uses to say yes to the transfer ask. */
    private const string AFFIRMATIVE = '/\b(yes|yeah|yep|yup|ok|okay|sure|alright|all right|fine|go ahead|uh-huh|mm-hmm|i guess)\b/';

    private const string NEGATIVE = '/\b(no|nope|nah|dont|cant|wont)\b/';

    /** The agent's transfer ask, as the script words it. */
    private const string TRANSFER_ASK = '/few minutes|specialist is coming|coming on the line/';

    /**
     * @return array{checks: list<Check>, passed: bool}
     */
    public function grade(RoleplaySession $session, ?int $transferClickedAt): array
    {
        $lines = $this->parseTranscript((string) $session->transcript);
        $agent = array_values(array_filter($lines, fn (array $line): bool => $line['role'] === 'agent'));
        $agentText = $this->normalize(implode("\n", array_column($agent, 'text')));
        $lead = $session->persona['lead'];
        $expectsTransfer = $session->expected_outcome === Outcome::Transfer;

        $checks = [
            $this->check('recorded_line', "Said you're on a recorded line with America's Health",
                str_contains($agentText, 'recorded line') && (bool) preg_match('/americas? health/', $agentText)),
            $this->check('double_confirm', 'Asked about Parts A and B and double-confirmed the red, white and blue card',
                (bool) preg_match('/parts? a (and|&) b/', $agentText) && (bool) preg_match('/red,? white,? (and|&) blue/', $agentText)),
            $this->check('state_zip', 'Confirmed state and ZIP',
                str_contains($agentText, strtolower($lead['state'])) && $this->mentionsZip($agent, $lead['zip'])),
            $this->check('work_va', 'Asked about insurance through work or the VA',
                (bool) preg_match('/\b(work|employer)\b/', $agentText) && (bool) preg_match('/\bv\.? ?a\b|veteran/', $agentText)),
            $this->check('specialist', "Told them they're being transferred to a specialist",
                str_contains($agentText, 'specialist')),
            $this->check('transfer_permission', 'Got a yes after the transfer ask, before clicking Transfer',
                $expectsTransfer ? $this->hasTransferPermission($lines, $transferClickedAt) : null),
            $this->check('may_maybe', 'Used "may" or "maybe" on benefit claims',
                $this->hedgesBenefitClaims($agent)),
            $this->check('disposition', 'Coded the call correctly',
                $session->disposition === $session->expected_outcome->value),
            $this->check('patience', "Didn't run out of patience",
                $session->end_reason !== 'hung_up'),
        ];

        $byKey = array_column($checks, 'passed', 'key');

        return [
            'checks' => $checks,
            'passed' => $byKey['disposition'] === true
                && $byKey['patience'] === true
                && $byKey['transfer_permission'] !== false,
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
    private function hasTransferPermission(array $lines, ?int $transferClickedAt): bool
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
    private function isAffirmative(string $text): bool
    {
        $opening = ltrim($text, " \t.,!?-");

        if (preg_match(str_replace('/\b(', '/^(', self::AFFIRMATIVE), $opening)) {
            return true;
        }

        return ! preg_match(self::NEGATIVE, $text) && (bool) preg_match(self::AFFIRMATIVE, $text);
    }

    /**
     * Every agent line that tells the lead they qualify or are entitled
     * must hedge with "may" or "maybe".
     *
     * @param  list<Line>  $agent
     */
    private function hedgesBenefitClaims(array $agent): bool
    {
        foreach ($agent as $line) {
            $text = $this->normalize($line['text']);

            if (preg_match('/\b(qualify|qualifies|entitled|eligible)\b/', $text) && ! preg_match('/\bmay(be)?\b/', $text)) {
                return false;
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
    private function mentionsZip(array $agent, string $zip): bool
    {
        foreach ($agent as $line) {
            if (str_contains((string) preg_replace('/\D/', '', $line['text']), $zip)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        return strtolower(str_replace(['’', "'"], '', $text));
    }

    /**
     * @return Check
     */
    private function check(string $key, string $label, ?bool $passed): array
    {
        return ['key' => $key, 'label' => $label, 'passed' => $passed];
    }
}
