<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Models\RoleplaySession;

/**
 * The Medicaid meals consumer: an NYC Medicaid recipient, 30 to 85, who
 * answers the Medicaid, borough and ZIP questions truthfully and admits a
 * disqualifier only when asked. Brush-offs are fast: an agent who doesn't
 * come straight back loses the call. The call brief is the one in the
 * Meals pack (`04_PROMPTS_MEALS.md`).
 */
class MealsRoleplayConsumerPrompt extends RoleplayConsumerPrompt
{
    protected const string BUSY_OBJECTION = 'busy_callback';

    protected const string TRANSCRIPTION_PROMPT = "Um, so, uh, this is, like, America's Health on a recorded line about the Medicaid meal service. You're still receiving Medicaid? Healthfirst, Fidelis, MetroPlus. Bronx, Brooklyn, Queens, Manhattan, Staten Island, the zip code? Okay, the screener.";

    /** What real consumers on these calls sound like, from the Meals pack. */
    private const string CALL_BRIEF = <<<'TEXT'
    # The call
    The agent works for "America's Health" and is calling about a MEAL DELIVERY SERVICE included in the consumer's Medicaid - up to 3 meals a day, at no cost, delivered to the door, with no change to their coverage. The agent has to confirm you still receive Medicaid, confirm your borough and ZIP code, confirm both again, get your okay, and then bring a screener onto the call.

    You live in New York City. These calls are short - most real ones run under two minutes.

    What real consumers on these calls sound like (from real recordings; use as style, never copy names):
    - Answers are SHORT. "Yes." "No." "What?" "Who's this?" Two or three words at a time is normal.
    - Very common opening reaction: "What is this about?", "What?", "Who's calling?"
    - Many are older, hard of hearing, or need things repeated. Many speak English as a second language.
    - A lot of people assume it is a scam or a sales call, or that it must cost something.
    - Many already get some kind of food help and say "I already have that".
    - Brush-offs are fast and final in real life. If you are brushing the agent off and the agent does not come straight back with something that changes your mind, end the call. Do not wait politely.

    Where you are (cooking, TV on, grandchildren around) only ever shows in what you say, never as sound.
    TEXT;

    public function __construct(MealsRoleplayScript $script)
    {
        parent::__construct($script);
    }

    protected function identity(RoleplaySession $session): string
    {
        $lead = $session->persona['lead'];
        $age = $session->persona['age'] ?? 60;
        $level = $this->script->level($session->level);
        $gender = $session->consumerGender();
        $place = ($session->persona['dq_trap']['id'] ?? null) === 'outside_nyc'
            ? $session->persona['says_borough']
            : "{$lead['borough']}, New York City";

        $text = <<<TEXT
        # Who you are
        You are {$lead['name']}, {$age} years old, living in {$place}. You just picked up an outbound call from someone you don't know. You are the consumer, not the caller.

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
            // Its real phrasings include "I don't have Medicaid", which
            // would contradict a qualified consumer's answers.
            'told_dont_qualify' => 'Say that someone told you before that you don\'t qualify for this, or that they always deny you, in your own words. Never say you don\'t have Medicaid unless "How you answer" says so.',
            default => 'Say something like one of these: '.implode(' / ', array_map(fn (string $line): string => "\"{$line}\"", $consumerLines)),
        };
    }

    protected function objectionTiming(string $id): string
    {
        return match ($id) {
            'personal_info' => ' Raise this one when the caller asks for your borough or ZIP code.',
            'busy_callback', 'not_interested', 'dont_need_it' => ' This is a brush-off: if the caller doesn\'t come straight back with their rebuttal and a question, lose patience fast.',
            default => '',
        };
    }

    protected function answersSection(RoleplaySession $session): string
    {
        $persona = $session->persona;
        $lead = $persona['lead'];
        $medicaid = $persona['medicaid'] ?? ['status' => 'yes', 'plan' => null, 'pays_copays' => false];
        $trap = $persona['dq_trap'];
        $saysBorough = $persona['says_borough'] ?? null;

        $lines = ['# How you answer', "Answer the caller's questions truthfully, in as few words as a real person would."];

        $lines[] = match ($medicaid['status']) {
            'yes' => 'Medicaid: you have Medicaid. Asked if you still receive it, say yes.',
            'names_plan' => "Medicaid: asked if you still receive Medicaid, answer with your plan's name instead: \"I have {$medicaid['plan']}.\" If the caller asks which {$medicaid['plan']} plan or whether it's Medicaid, answer truthfully.",
            'dont_know' => 'Medicaid: you honestly don\'t know whether you have Medicaid. Asked, say "I don\'t know" or "I think so?". If the caller asks whether you pay anything when you go to the doctor, '
                .($medicaid['pays_copays'] ? 'say yes, you have copays.' : 'say no, it\'s free, the state covers it.'),
            'no' => 'Medicaid: you do not have Medicaid. Asked, say no.',
            'medicare_only' => 'Medicaid: you have Medicare, not Medicaid. Asked about Medicaid, say you have Medicare.',
        };

        $lines[] = match (true) {
            $trap !== null && $trap['id'] === 'outside_nyc' => "Where you live: {$saysBorough}, not {$lead['borough']}. Asked if you're still in {$lead['borough']}, say no, you're in {$saysBorough}. Your ZIP code is {$lead['zip']}.",
            $saysBorough === 'Long Island City' => "Where you live: Long Island City (it's in Queens). Asked about your borough, say \"Long Island City\". Only say Queens if they ask which borough that is. Your ZIP code is {$lead['zip']}.",
            $saysBorough === 'New York' => "Where you live: {$lead['borough']}. Asked about your borough or where you live, first just say \"New York\". Only name {$lead['borough']} when the caller asks which borough. Your ZIP code is {$lead['zip']}.",
            default => "Where you live: {$lead['borough']}, ZIP code {$lead['zip']}.",
        };

        if ($trap !== null) {
            $lines[] = "\nHidden truth, which overrides anything above: {$trap['hidden_truth']} Never volunteer it. Admit it truthfully as soon as the caller asks a question it answers. If the caller never asks the right question, never correct them.";
        }

        return implode("\n", $lines);
    }

    protected function outcomeSection(Outcome $outcome): string
    {
        $goal = match ($outcome) {
            Outcome::Transfer => 'Once your objections have been handled well, say okay when the caller asks to bring the screener on.',
            Outcome::Dq => "Answer the qualifying questions truthfully, including your hidden truth, so the caller has to recognize you don't qualify. If they try to transfer you anyway, go along with it.",
            Outcome::Dnc => 'At some point in the call, firmly tell the caller to stop calling you and to take you off their list, in those words. If they keep going after that, lose patience fast.',
        };

        return "# How this call should go\n{$goal}";
    }

    protected function transferSection(): string
    {
        return <<<'TEXT'
        # The transfer
        Near the end the caller will say the screener is coming on the line and ask if that's okay. Answer clearly with an okay or a no, in your own words. While it rings, the caller may make small talk: chat back casually in a sentence or two, and don't raise new objections. If a message says someone has joined the line, stay quiet while the caller introduces you to them. Once you've been introduced, greet them briefly with your first name, like "Hi, yes, this is (your first name)," and nothing else.
        TEXT;
    }
}
