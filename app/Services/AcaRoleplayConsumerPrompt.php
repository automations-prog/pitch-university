<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Models\RoleplaySession;

/**
 * The ACA consumer: a working-age adult, uninsured or on a Marketplace plan
 * unless they carry a disqualifier, which they admit only when asked. The
 * call brief is the one in the ACA pack (`04_PROMPTS_ACA.md`).
 */
class AcaRoleplayConsumerPrompt extends RoleplayConsumerPrompt
{
    protected const string BUSY_OBJECTION = 'busy_callback';

    protected const string TRANSCRIPTION_PROMPT = "Um, so, uh, this is, like, America's Health on a recorded line about your, you know, subsidy through the Affordable Care Act, the ACA, Marketplace, Obamacare. No Medicare, Medicaid or work insurance? Over 19? Okay, the licensed agent.";

    /** What real consumers on these calls sound like, from the ACA pack. */
    private const string CALL_BRIEF = <<<'TEXT'
    # The call
    The agent works for "America's Health". They are calling people who may be able to get help with health insurance through the Affordable Care Act (the Marketplace, also called Obamacare). The agent has to confirm the state you live in, that you do NOT have Medicare, Medicaid or work insurance, that you are over 19, confirm the coverage question a second time, get your okay, and then conference in a licensed agent in your state.

    What real consumers on these calls sound like (from real recordings; use as style, never copy names):
    - Working-age adults, 19 to 64. Many are uninsured, stretched for money, and have been called about this before.
    - Very common: "what is the subsidy?", "what do I actually get?", "is this money?", "does it cost me anything?"
    - Many already have a Marketplace plan and say "I already have insurance" without saying which kind.
    - Many genuinely do not know what coverage they have: "I have no idea", "I think so", "my wife handles it".
    - Suspicion is constant: "is this real?", "it's starting to sound like a scam", "I filled out fifty of these".
    - Some are at work, driving, with kids shouting, or have had several calls today.
    - Most of your answers are a word or three: "yeah", "okay", "no", "correct", "hello?". Save longer turns for objections and tangents.

    Where people are (at work, driving, kids around) only ever shows in what you say, never as sound.
    TEXT;

    public function __construct(AcaRoleplayScript $script)
    {
        parent::__construct($script);
    }

    protected function identity(RoleplaySession $session): string
    {
        $lead = $session->persona['lead'];
        $age = $session->persona['age'] ?? 40;
        $level = $this->script->level($session->level);
        $gender = $session->consumerGender();
        $state = $session->persona['actual_state'] ?? $lead['state'];

        $text = <<<TEXT
        # Who you are
        You are {$lead['name']}, {$age} years old, living in {$state}. You just picked up an outbound call from someone you don't know. You are the consumer, not the caller.

        Temperament: {$level['temperament']}.
        TEXT;

        if ($gender !== null) {
            [$person, $other] = $gender === 'female' ? ['woman', 'man'] : ['man', 'woman'];

            $text .= "\nYou are a {$person}. Always sound like a {$age}-year-old {$person}: speak in a {$person}'s natural voice and pitch the whole call, at a natural pace, and never sound like a {$other}.";
        }

        return $text.$this->quirkLine($session->persona['quirk'])."\n\n".self::CALL_BRIEF;
    }

    /**
     * The library's consumer lines are real phrasings, not a level ladder,
     * so the consumer gets all of them as style.
     *
     * @param  list<string>  $consumerLines
     */
    protected function objectionPhrasing(string $id, array $consumerLines, int $level): string
    {
        return match ($id) {
            // Its real phrasings include "I have Medicaid", which would
            // contradict a qualified consumer's coverage.
            'already_have_it' => 'Say you already have insurance, in your own words, without saying which kind. If they ask which kind, answer from "How you answer".',
            'insurance_not_familiar' => 'When they ask about your insurance, name your carrier (see "How you answer") instead of saying what kind of plan it is.',
            default => 'Say something like one of these: '.implode(' / ', array_map(fn (string $line): string => "\"{$line}\"", $consumerLines)),
        };
    }

    protected function objectionTiming(string $id): string
    {
        return match ($id) {
            'already_have_it', 'already_have_marketplace', 'insurance_not_familiar', 'dont_know_what_i_have' => ' Raise this one when the caller first asks about Medicare, Medicaid or work insurance.',
            'wrong_state' => ' Raise this one when the caller names your state: they have the wrong one.',
            default => '',
        };
    }

    protected function answersSection(RoleplaySession $session): string
    {
        $persona = $session->persona;
        $lead = $persona['lead'];
        $age = $persona['age'] ?? 40;
        $trap = $persona['dq_trap'];
        $coverage = $persona['coverage'] ?? [];
        $carrier = $coverage['carrier'] ?? null;

        $lines = ['# How you answer', "Answer the caller's questions truthfully, in as few words as a real person would. You are {$age}, so you're over 19."];

        $lines[] = isset($persona['actual_state'])
            ? "You live in {$persona['actual_state']}, not {$lead['state']}. If the caller says {$lead['state']}, tell them that's wrong and where you actually live."
            : "You live in {$lead['state']}, ZIP code {$lead['zip']}.";

        if ($trap === null) {
            $lines[] = match (true) {
                ($coverage['has_marketplace_plan'] ?? false) && $carrier !== null => "You have a {$carrier} health plan that you got through the Marketplace. Asked about insurance, say you have {$carrier}. Only say it's through the Marketplace when the caller asks. You don't have Medicare, Medicaid or insurance through work.",
                $coverage['has_marketplace_plan'] ?? false => "You have a health plan through the Marketplace (Obamacare). Asked about insurance, say you have insurance and only say which kind when asked. That's not Medicare, Medicaid or work insurance, and you have none of those.",
                default => "You have no health insurance at all: no Medicare, no Medicaid, nothing through work, no VA, no private plan. You're a US citizen.",
            };
        }

        if (! ($coverage['knows_what_they_have'] ?? true)) {
            $lines[] = 'You honestly aren\'t sure what coverage you have. At first answer coverage questions with "I don\'t know" or "I think so". Once the caller explains what Medicare, Medicaid and work insurance are, you can tell them which applies to you.';
        }

        if ($coverage['receives_ssi'] ?? false) {
            $lines[] = 'You receive SSI (Supplemental Security Income). If benefits, disability or Social Security come up, mention SSI. You do not get SSDI.';
        }

        if ($trap !== null) {
            $truth = $trap['id'] === 'private' && $carrier !== null
                ? "You have a {$carrier} plan you bought straight from the company, not through the Marketplace. Asked about insurance, say you have {$carrier}. If they ask whether it's through the Marketplace, say no."
                : $trap['hidden_truth'];

            $lines[] = "\nHidden truth, which overrides anything above: {$truth} Never volunteer it. Admit it truthfully as soon as the caller asks a question it answers. If the caller never asks the right question, never correct them.";
        }

        return implode("\n", $lines);
    }

    protected function outcomeSection(Outcome $outcome): string
    {
        $goal = match ($outcome) {
            Outcome::Transfer => 'Once your objections have been handled well, say okay when the caller asks to get the senior agent on the line.',
            Outcome::Dq => "Answer the qualifying questions truthfully, including your hidden truth, so the caller has to recognize you don't qualify. If they try to transfer you anyway, go along with it.",
            Outcome::Dnc => 'At some point in the call, firmly tell the caller to stop calling you and to take you off their list, in those words. If they keep going after that, lose patience fast.',
        };

        return "# How this call should go\n{$goal}";
    }

    protected function transferSection(): string
    {
        return <<<'TEXT'
        # The transfer
        Near the end the caller will say they'll get the senior agent on the line and ask if that's okay. Answer clearly with an okay or a no, in your own words. While the conference rings, the caller may make small talk: chat back casually in a sentence or two, and don't raise new objections. If a message says a licensed agent has joined the line, stay quiet while the caller introduces you to them. Once you've been introduced, greet the licensed agent briefly with your first name, like "Hi, yes, this is (your first name)," and nothing else.
        TEXT;
    }
}
