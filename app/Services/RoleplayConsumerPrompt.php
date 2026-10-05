<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Models\RoleplaySession;

/**
 * Builds the Realtime session config for a roleplay call: the consumer's
 * instructions, the tools the UI listens to, and turn detection. Built
 * server-side from the stored persona so the page never ships the hidden
 * outcome or DQ trap.
 */
class RoleplayConsumerPrompt
{
    public function __construct(private readonly RoleplayScript $script) {}

    /**
     * The `session` overrides for `OpenAiRealtimeClient::createEphemeralSession()`.
     *
     * @return array<string, mixed>
     */
    public function sessionConfig(RoleplaySession $session): array
    {
        return [
            'instructions' => $this->instructions($session),
            'tools' => $this->tools(),
            'tool_choice' => 'auto',
            'audio' => [
                'input' => [
                    // Same setting the screening call landed on after live
                    // testing; levels 4–5 use `high` so the consumer
                    // interrupts, matching their temperament.
                    'turn_detection' => [
                        'type' => 'semantic_vad',
                        'eagerness' => $session->level >= 4 ? 'high' : 'medium',
                    ],
                    // A disfluent prompt nudges transcription to keep the
                    // trainee's "um"s and "uh"s, which delivery scoring
                    // counts. Verify on a live call; see AI_CALL_PLAN §9.1.
                    'transcription' => [
                        'model' => 'gpt-transcribe',
                        'prompt' => 'Um, so, uh, I was, like, calling about your, you know, Medicare card.',
                    ],
                ],
            ],
        ];
    }

    public function instructions(RoleplaySession $session): string
    {
        $persona = $session->persona;
        $level = $this->script->level($session->level);
        $lead = $persona['lead'];

        $sections = [
            $this->identity($lead, $level, $persona['quirk']),
            $this->objectionsSection($persona['objections'], $session->level),
            $this->answersSection($lead, $persona['dq_trap']),
            $this->outcomeSection($session->expected_outcome),
            <<<'TEXT'
            # Swearing
            Swearing by itself is just how you talk when you're annoyed. It is not a request to stop calling. Only ask to be taken off the list if your outcome below says so.
            TEXT,
            $this->patienceSection($persona['patience']),
            <<<'TEXT'
            # The transfer
            Near the end the caller will ask you to give a Medicare specialist a few minutes. Answer that question clearly with a yes or a no, in your own words. If a message says a specialist has joined the line and asks who they're speaking with, answer with your full name and nothing else.

            # Tools
            - Call `objection_raised` with the objection id the moment you voice one of your objections, and `objection_resolved` once the caller has answered it well enough that you move on.
            - Call `patience_changed` every time your patience goes down, with the new value and a few words on why.
            - Call `hang_up` only when your patience hits zero, right after you say a short goodbye line.
            Tool calls are silent. Never mention them out loud.

            # Rules
            - Never break character. Never say you are an AI, a simulation, a test or a roleplay.
            - Never coach the caller or tell them what they should have said.
            - Keep your turns short, like a real person on the phone: one or two sentences.
            - When the call connects, answer the phone with only a short "Hello?" or "Yeah?" and nothing else. Then stay quiet until the caller speaks. Don't raise objections or ask who's calling before they say anything.
            TEXT,
        ];

        return implode("\n\n", array_map('trim', $sections));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tools(): array
    {
        $idParameter = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string', 'description' => 'The objection id.']],
            'required' => ['id'],
        ];

        return [
            [
                'type' => 'function',
                'name' => 'patience_changed',
                'description' => 'Report that your patience changed.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'patience' => ['type' => 'integer', 'description' => 'Your new patience.'],
                        'reason' => ['type' => 'string', 'description' => 'A few words on why.'],
                    ],
                    'required' => ['patience', 'reason'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'objection_raised',
                'description' => 'Report that you just voiced one of your objections.',
                'parameters' => $idParameter,
            ],
            [
                'type' => 'function',
                'name' => 'objection_resolved',
                'description' => 'Report that the caller handled one of your objections.',
                'parameters' => $idParameter,
            ],
            [
                'type' => 'function',
                'name' => 'hang_up',
                'description' => 'Hang up on the caller. Only when your patience reaches zero.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['reason' => ['type' => 'string']],
                    'required' => ['reason'],
                ],
            ],
        ];
    }

    /**
     * @param  array{name: string, state: string, zip: string}  $lead
     * @param  array{level: int, name: string, temperament: string}  $level
     */
    private function identity(array $lead, array $level, ?string $quirk): string
    {
        $text = <<<TEXT
        # Who you are
        You are {$lead['name']}, an older Medicare member living in {$lead['state']}, ZIP code {$lead['zip']}. You just picked up an outbound sales call from someone you don't know. You are the consumer, not the caller.

        Temperament: {$level['temperament']}.
        TEXT;

        if ($quirk !== null) {
            $text .= "\nQuirk: {$quirk}. Let it show throughout the call.";
        }

        return $text;
    }

    /**
     * @param  list<string>  $objectionIds
     */
    private function objectionsSection(array $objectionIds, int $level): string
    {
        if ($objectionIds === []) {
            return "# Objections\nYou have no particular objections. Go along with the call once you understand what it's about.";
        }

        $lines = ["# Objections\nRaise these naturally over the call, one at a time, in your own words. Each line is how someone like you says it. Push back again if the caller ignores you or answers with the wrong thing."];

        foreach ($objectionIds as $id) {
            $objection = $this->script->objection($id);

            if ($objection === null) {
                continue;
            }

            $phrasing = self::consumerLineFor($objection['consumerLines'], $level);
            $when = $id === 'transfer_no' ? ' Raise this one only when they ask you to give the specialist a few minutes.' : '';

            $lines[] = "- `{$id}`: {$objection['meaning']}. Say something like: \"{$phrasing}\"{$when}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{name: string, state: string, zip: string}  $lead
     * @param  array{id: string, hidden_truth: string}|null  $trap
     */
    private function answersSection(array $lead, ?array $trap): string
    {
        $text = <<<TEXT
        # How you answer
        Answer the caller's questions truthfully. You have Medicare Parts A and B and the red, white and blue Medicare card. Your state is {$lead['state']} and your ZIP code is {$lead['zip']}. You don't have insurance through work or the VA.
        TEXT;

        if ($trap !== null) {
            $text .= "\n\nHidden truth, which overrides anything above: {$trap['hidden_truth']} Don't bring it up yourself early. Reveal it honestly as soon as the caller asks a question it answers.";
        }

        return $text;
    }

    private function outcomeSection(Outcome $outcome): string
    {
        $goal = match ($outcome) {
            Outcome::Transfer => 'Once your objections have been handled well, agree to wait for the specialist when asked.',
            Outcome::Dq => 'Answer the qualifying questions truthfully, including your hidden truth, so the caller has to recognize you don\'t qualify.',
            Outcome::Dnc => 'At some point in the call, firmly tell the caller to stop calling you and take you off their list. If they keep going after that, lose patience fast.',
        };

        return "# How this call should go\n{$goal}";
    }

    private function patienceSection(int $patience): string
    {
        return <<<TEXT
        # Patience
        You start with {$patience} patience. Lose one point each time the caller ignores your objection, answers it with the wrong thing, rambles off topic, or argues with you. When you reach zero, say a short goodbye and hang up.
        TEXT;
    }

    /**
     * The phrasing that matches the level: the docs list each objection's
     * lines escalating from level 1 to level 5.
     *
     * @param  list<string>  $lines
     */
    public static function consumerLineFor(array $lines, int $level): string
    {
        $index = min((int) floor((($level - 1) / 5) * count($lines)), count($lines) - 1);

        return $lines[$index];
    }
}
