<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Enums\RealtimeVoice;
use Random\Randomizer;

/**
 * Builds a consumer persona following the rules in
 * `ai-roleplay/OBJECTIONS_AND_PERSONAS.md`: objections drawn by weight
 * (without replacement) from those unlocked at the level, a quirk at level
 * 2+, and an outcome drawn from the level's Transfer / DQ / DNC mix, with a
 * DQ trap when it's DQ.
 */
class RoleplayPersonaGenerator
{
    /**
     * Mock dialer leads so the script's (Customer Name), (state) and
     * (zip code) placeholders have something to read back. Each lead keeps
     * one voice of their gender, so a name always sounds the same; marin
     * and cedar, OpenAI's steadiest voices, go to the first two. Only
     * plainly gendered voices are used (no alloy or sage).
     *
     * @var list<array{name: string, state: string, zip: string, gender: 'male'|'female', voice: string}>
     */
    private const array LEADS = [
        ['name' => 'Dorothy Miller', 'state' => 'Florida', 'zip' => '33511', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Harold Jenkins', 'state' => 'Ohio', 'zip' => '43204', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Linda Carter', 'state' => 'Texas', 'zip' => '75217', 'gender' => 'female', 'voice' => 'coral'],
        ['name' => 'Robert Hayes', 'state' => 'Georgia', 'zip' => '30906', 'gender' => 'male', 'voice' => 'ash'],
        ['name' => 'Barbara Nguyen', 'state' => 'Arizona', 'zip' => '85308', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'James Walker', 'state' => 'North Carolina', 'zip' => '27406', 'gender' => 'male', 'voice' => 'echo'],
        ['name' => 'Patricia Moore', 'state' => 'Pennsylvania', 'zip' => '19143', 'gender' => 'female', 'voice' => 'shimmer'],
        ['name' => 'Charles Robinson', 'state' => 'Michigan', 'zip' => '48219', 'gender' => 'male', 'voice' => 'verse'],
    ];

    private readonly Randomizer $randomizer;

    public function __construct(
        private readonly RoleplayScript $script,
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer;
    }

    /** Starting patience is `10 - level`. */
    public static function startingPatience(int $level): int
    {
        return 10 - $level;
    }

    /**
     * @return array{outcome: Outcome, voice: RealtimeVoice, persona: array{lead: array{name: string, state: string, zip: string, gender: 'male'|'female'}, objections: list<string>, quirk: string|null, patience: int, dq_trap: array{id: string, hidden_truth: string}|null}}
     */
    public function generate(int $level): array
    {
        $content = $this->script->content();
        $levelData = $this->script->level($level);
        [$min, $max] = $levelData['objectionRange'];

        $unlocked = array_values(array_filter(
            $content['objections'],
            fn (array $objection): bool => $objection['minLevel'] <= $level,
        ));

        $objections = $this->weightedSample(
            $unlocked,
            $this->randomizer->getInt($min, $max),
            fn (array $objection): int => $objection['weight'],
        );

        $outcomes = array_filter($levelData['outcomeMix'], fn (int $percent): bool => $percent > 0);
        $outcomeKey = $this->weightedSample(
            array_keys($outcomes),
            1,
            fn (string $outcome): int => $outcomes[$outcome],
        )[0];
        $outcome = Outcome::from($outcomeKey);

        $trap = $outcome === Outcome::Dq ? $this->pick($content['dqTraps']) : null;

        ['voice' => $voice] = $lead = $this->pick(self::LEADS);
        unset($lead['voice']);

        return [
            'outcome' => $outcome,
            'voice' => RealtimeVoice::from($voice),
            'persona' => [
                'lead' => $lead,
                'objections' => array_column($objections, 'id'),
                'quirk' => $level >= RoleplayScript::QUIRK_MIN_LEVEL ? $this->pick($content['quirks']) : null,
                'patience' => self::startingPatience($level),
                'dq_trap' => $trap === null ? null : ['id' => $trap['id'], 'hidden_truth' => $trap['hiddenTruth']],
            ],
        ];
    }

    /**
     * Draws `$count` distinct items, each draw weighted by `$weightOf`.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @param  callable(T): int  $weightOf
     * @return list<T>
     */
    private function weightedSample(array $items, int $count, callable $weightOf): array
    {
        $drawn = [];

        while (count($drawn) < $count && $items !== []) {
            $total = array_sum(array_map($weightOf, $items));
            $roll = $this->randomizer->getInt(1, max($total, 1));

            foreach ($items as $index => $item) {
                $roll -= $weightOf($item);

                if ($roll <= 0) {
                    break;
                }
            }

            $drawn[] = $items[$index];
            array_splice($items, $index, 1);
        }

        return $drawn;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->randomizer->getInt(0, count($items) - 1)];
    }
}
