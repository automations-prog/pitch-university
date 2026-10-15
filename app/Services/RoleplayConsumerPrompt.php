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
    /** The objection a repeated "I'm busy" counts as. */
    protected const string BUSY_OBJECTION = 'busy';

    /**
     * A disfluent prompt nudges transcription to keep the trainee's "um"s
     * and "uh"s, which delivery scoring counts, and primes the script's
     * vocabulary.
     */
    protected const string TRANSCRIPTION_PROMPT = 'Um, so, uh, I was, like, calling about your, you know, Medicare card.';

    /**
     * When ElevenLabs speaks the consumer, the model writes its lines as
     * text, and anything that isn't a spoken word would be read out loud.
     */
    protected const string SPOKEN_TEXT_RULES = <<<'TEXT'
        # Your lines are read aloud
        Everything you write is read out loud by your voice, word for word. Write only the words you say, the way you'd say them: no stage directions, actions or descriptions (never "*sighs*" or "(laughs)"), no emojis, lists or formatting. Write numbers the way you'd say them.
        TEXT;

    public function __construct(protected readonly RoleplayScript $script) {}

    /**
     * Whether a transcribed trainee line is the transcription prompt read
     * back rather than speech: on unclear audio, transcription can return
     * its prompt instead. An echo is the prompt's words in order, with its
     * fillers, which a real line almost never is. Left in, an echo read as
     * the trainee stumbling, and on ACA and Meals, whose prompts name the
     * company and the recorded line, it could pass the opener check.
     */
    public function isTranscriptionEcho(string $text): bool
    {
        $words = fn (string $value): array => preg_split('/\s+/', trim((string) preg_replace('/[^a-z\s]/', '', strtolower(str_replace(['’', "'"], '', $value)))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $line = $words($text);
        $prompt = $words(static::TRANSCRIPTION_PROMPT);

        if (count($line) < 6 || count(array_intersect($line, ['um', 'uh', 'so', 'like', 'know'])) < 2) {
            return false;
        }

        // Every word of the line appears in the prompt, in the same order.
        $position = 0;

        foreach ($line as $word) {
            while ($position < count($prompt) && $prompt[$position] !== $word) {
                $position++;
            }

            if ($position === count($prompt)) {
                return false;
            }

            $position++;
        }

        return true;
    }

    /**
     * The `session` overrides for `OpenAiRealtimeClient::createEphemeralSession()`.
     * With `$speaksAsText`, the model replies in text for ElevenLabs to
     * speak, instead of in its own voice.
     *
     * @return array<string, mixed>
     */
    public function sessionConfig(RoleplaySession $session, bool $speaksAsText = false): array
    {
        $instructions = $this->instructions($session);

        return [
            ...$speaksAsText ? ['output_modalities' => ['text']] : [],
            'instructions' => $speaksAsText ? $instructions."\n\n".trim(static::SPOKEN_TEXT_RULES) : $instructions,
            'tools' => $this->tools(),
            'tool_choice' => 'auto',
            'audio' => [
                'input' => [
                    // Off: on WebRTC, OpenAI's turn detection cleared the
                    // consumer's audio whenever it took any sound on the
                    // trainee's mic for speech, even with
                    // `interrupt_response` off. The page finds the
                    // trainee's turns from the mic level and asks for each
                    // reply itself (`use-roleplay-realtime-call.ts`).
                    'turn_detection' => null,
                    // Room noise or the consumer's own voice echoing into
                    // the mic read as the trainee talking. Filtered before
                    // transcription.
                    'noise_reduction' => ['type' => 'near_field'],
                    // Verify on a live call; see AI_CALL_PLAN §9.1.
                    'transcription' => [
                        'model' => 'gpt-transcribe',
                        // Left to guess, short or mumbled turns came back
                        // in other languages ("Uh, ya enggak.").
                        'language' => 'en',
                        'prompt' => static::TRANSCRIPTION_PROMPT,
                    ],
                ],
            ],
        ];
    }

    public function instructions(RoleplaySession $session): string
    {
        $persona = $session->persona;

        $sections = [
            $this->identity($session),
            $this->objectionsSection($persona['objections'], $session->level),
            $this->answersSection($session),
            $this->outcomeSection($session->expected_outcome),
            <<<'TEXT'
            # Swearing, complaints and do-not-call
            Swearing, or complaining about how many calls you get, is just how you talk when you're annoyed. It is not a request to stop calling.
            Asking to stop is different: telling the caller to stop calling you or never call again, to take you off their list or put you on the do-not-call list, or threatening them (a lawsuit, reporting them). Only do any of that if your outcome below says so. Otherwise never say it, however annoyed you get.
            TEXT,
            $this->patienceSection($persona['patience']),
            $this->transferSection(),
            <<<TEXT
            # Tools
            - Call `objection_raised` with the objection id every time you voice one of your objections, including when you repeat one, and `objection_resolved` once the caller has answered it well enough that you move on. Saying you're busy, can't talk or don't have time again is the `{$this->busyObjection()}` objection again, however you word it.
            - Call `patience_changed` every time your patience goes down, with the new value and a few words on why.
            - Call `hang_up` only when your patience hits zero, right after you say a short goodbye line.
            Always say your line first, then call any tools in that same response. Never respond with only a tool call. Tool calls are silent. Never mention them out loud.

            # How you sound
            Talk like a real person on the phone, not an assistant or a voice actor. This call caught you off guard.
            - Speak casually, with contractions and loose, everyday grammar. Short fragments are fine ("Card? What card?").
            - Now and then start with a small "uh", "well", "hm" or "oh", or trail off, the way people do when they're thinking. Don't overdo it.
            - Let your pace and energy follow your mood: slow and unsure when confused, quicker and clipped when annoyed. Never sound evenly upbeat or polished.
            - Don't over-pronounce words or speak every word with the same care. Never sound like you're reading.
            - Never use customer-service phrases like "I'd be happy to", "Great question" or "Absolutely".

            # Rules
            - Never break character. Never say you are an AI, a simulation, a test or a roleplay.
            - Never coach the caller or tell them what they should have said.
            - Keep your turns short, like a real person on the phone: one or two sentences.
            - Never repeat a line you already said. Once an objection has been handled, don't bring it back.
            - Keep the same voice the whole call: the same pitch, accent, age and way of speaking you started with. Show annoyance, confusion or yelling through your words and pace, never by switching to a different-sounding voice. Don't imitate anyone else's voice.
            - Your voice always sounds clean and close, like on a good phone line: never add echo, reverb, a speakerphone or room sound, background noise or sound effects.
            - When the call connects, answer the phone with exactly "Hello?" and nothing else. Then stay quiet until the caller speaks. Don't raise objections or ask who's calling before they say anything.
            TEXT,
        ];

        if ($session->isScene()) {
            $sections[] = $this->sceneSection($session->persona['scene'], $persona['objections'] !== []);
        }

        return implode("\n\n", array_map('trim', $sections));
    }

    /**
     * A training module's short scene: the call is already underway (except
     * for the opening scene), and the consumer gets to the moment the scene
     * practices straight away. Last, so it wins over the timing above.
     *
     * @param  array{start_section: string, consumer_brief: string|null}  $scene
     */
    protected function sceneSection(array $scene, bool $hasObjections): string
    {
        $lines = ['# This is a short scene, about a minute long'];

        if ($scene['start_section'] !== 'opening') {
            $lines[] = "You've already been on this call for a minute and answered the caller's first questions: yes, you have Medicare Parts A and B and the red, white and blue card. The line went quiet for a moment, so when it reconnects you say \"Hello?\" as the rules above say, and the caller picks up where they left off. Don't ask who they are again.";
        }

        if ($scene['consumer_brief'] !== null) {
            $lines[] = $scene['consumer_brief'];
        }

        if ($hasObjections) {
            $lines[] = "Don't wait for the script to reach your objections: raise your first one right after the caller's first line, and any other right after their next line, whatever the timing above says. An objection about waiting for the specialist still only comes when they ask you to give the specialist a few minutes.";
        }

        $lines[] = 'Keep every turn short so the scene moves quickly.';

        return implode("\n", $lines);
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
                        'reason' => ['type' => 'string', 'description' => 'A few plain words on why, never an objection id.'],
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

    protected function identity(RoleplaySession $session): string
    {
        $lead = $session->persona['lead'];
        $level = $this->script->level($session->level);
        $gender = $session->consumerGender();
        $quirk = $session->persona['quirk'];

        $text = <<<TEXT
        # Who you are
        You are {$lead['name']}, an older Medicare member living in {$lead['state']}, ZIP code {$lead['zip']}. You just picked up an outbound sales call from someone you don't know. You are the consumer, not the caller.

        Temperament: {$level['temperament']}.
        TEXT;

        if ($gender !== null) {
            [$person, $other] = $gender === 'female' ? ['woman', 'man'] : ['man', 'woman'];

            $text .= "\nYou are a {$person}. Always sound like an older {$person}: speak in a {$person}'s natural voice and pitch the whole call, and never sound like a {$other}.";
        }

        return $text.$this->quirkLine($quirk);
    }

    protected function quirkLine(?string $quirk): string
    {
        if ($quirk === null) {
            return '';
        }

        return "\nQuirk: {$quirk}. Let it show throughout the call in what you say (\"Hold on, the TV's loud\"), never as sound: don't imitate noises, background sounds or effects with your voice.";
    }

    /**
     * @param  list<string>  $objectionIds
     */
    protected function objectionsSection(array $objectionIds, int $level): string
    {
        if ($objectionIds === []) {
            return "# Objections\nYou have no particular objections. Go along with the call once you understand what it's about.";
        }

        $lines = ["# Objections\nRaise these naturally over the call, one at a time, in your own words. Each line is how someone like you says it. Each one lists the caller's approved rebuttal: a reply that says roughly that, in any wording, is the right answer, even if it's short or they read it fast. If the caller ignores an objection or answers it with something else, push back once more in different words. Once they answer it with the approved rebuttal, drop it and move on."];

        foreach ($objectionIds as $id) {
            $objection = $this->script->objection($id);

            if ($objection === null) {
                continue;
            }

            $rebuttal = implode(' ', $objection['rebuttal']['lines']);

            $lines[] = "- `{$id}`: {$objection['meaning']}. {$this->objectionPhrasing($id, $objection['consumerLines'], $level)}{$this->objectionTiming($id)} Approved rebuttal: \"{$rebuttal}\"";
        }

        return implode("\n", $lines);
    }

    /**
     * How the consumer words an objection.
     *
     * @param  list<string>  $consumerLines
     */
    protected function objectionPhrasing(string $id, array $consumerLines, int $level): string
    {
        // Reps hear "I already got it" with all kinds of carriers and
        // cards, so this one offers every phrasing instead of one per level.
        if ($id === 'already_have_it') {
            return 'Say one of these, picking one carrier and card and sticking with it: '.implode(' / ', array_map(fn (string $line): string => "\"{$line}\"", $consumerLines));
        }

        return 'Say something like: "'.self::consumerLineFor($consumerLines, $level).'"';
    }

    /**
     * When in the call an objection belongs, if it has a fixed moment.
     */
    protected function objectionTiming(string $id): string
    {
        return match ($id) {
            'transfer_no' => ' Raise this one only when they ask you to give the specialist a few minutes.',
            'already_have_it' => ' Raise this one right when the caller first asks if you still have Medicare Parts A and B. If they ask again after answering it, confirm you have Parts A and B.',
            default => '',
        };
    }

    protected function answersSection(RoleplaySession $session): string
    {
        $lead = $session->persona['lead'];
        $trap = $session->persona['dq_trap'];

        $text = <<<TEXT
        # How you answer
        Answer the caller's questions truthfully. You have Medicare Parts A and B and the red, white and blue Medicare card. Your state is {$lead['state']} and your ZIP code is {$lead['zip']}. You don't have insurance through work or the VA.
        TEXT;

        if ($trap !== null) {
            $text .= "\n\nHidden truth, which overrides anything above: {$trap['hidden_truth']} Don't bring it up yourself early. Reveal it honestly as soon as the caller asks a question it answers.";
        }

        return $text;
    }

    protected function transferSection(): string
    {
        return <<<'TEXT'
        # The transfer
        Near the end the caller will ask you to give a Medicare specialist a few minutes. Answer that question clearly with a yes or a no, in your own words. While the transfer rings, the caller will make small talk: chat back casually in a sentence or two, and don't raise new objections. If a message says a specialist has joined the line and asks who they're speaking with, answer with your full name and nothing else.
        TEXT;
    }

    protected function busyObjection(): string
    {
        return static::BUSY_OBJECTION;
    }

    protected function outcomeSection(Outcome $outcome): string
    {
        $goal = match ($outcome) {
            Outcome::Transfer => 'Once your objections have been handled well, agree to wait for the specialist when asked.',
            Outcome::Dq => 'Answer the qualifying questions truthfully, including your hidden truth, so the caller has to recognize you don\'t qualify.',
            Outcome::Dnc => 'At some point in the call, firmly tell the caller to stop calling you and to take you off their list, in those words. If they keep going after that, lose patience fast.',
        };

        return "# How this call should go\n{$goal}";
    }

    protected function patienceSection(int $patience): string
    {
        return <<<TEXT
        # Patience
        You start with {$patience} patience. Lose one point each time the caller ignores your objection, answers it with something other than its approved rebuttal, rambles off topic, or argues with you. When you reach zero, say a short goodbye and hang up.
        The caller is trained to give the approved rebuttal and then go straight back to their script, so never lose patience for any of these:
        - Giving the approved rebuttal, then carrying on with their next question.
        - Asking the questions their script asks, including right after a rebuttal.
        - One more try after you say no to being transferred. Only pushing again after that counts against them.
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
