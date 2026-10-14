<?php

namespace App\Services;

use App\Enums\Outcome;
use App\Enums\RealtimeVoice;
use Random\Randomizer;

/**
 * Builds a Medicaid meals consumer following
 * `ai-roleplay/meals/OBJECTIONS_AND_PERSONAS.md`. On top of the shared draw
 * (objections, quirk, outcome, DQ trap) every persona gets an age and the
 * hidden facts the agent has to uncover: how they answer the Medicaid
 * question (about 1 in 5 don't know, so the copay fallback comes up), the
 * plan they name, and from level 3 a borough answer that is a trap.
 *
 * @phpstan-type Medicaid array{status: 'yes'|'no'|'dont_know'|'names_plan'|'medicare_only', plan: string|null, pays_copays: bool}
 */
class MealsRoleplayPersonaGenerator extends RoleplayPersonaGenerator
{
    /**
     * NYC dialer leads: the borough and ZIP the list has. Each keeps one
     * voice of their gender, so a name always sounds the same.
     *
     * @var list<array{name: string, state: string, borough: string, zip: string, gender: 'male'|'female', voice: string}>
     */
    private const array LEADS = [
        ['name' => 'Maria Rodriguez', 'state' => 'New York', 'borough' => 'Bronx', 'zip' => '10453', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Darnell Washington', 'state' => 'New York', 'borough' => 'Brooklyn', 'zip' => '11212', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Gloria Mendez', 'state' => 'New York', 'borough' => 'Queens', 'zip' => '11101', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Wei Chen', 'state' => 'New York', 'borough' => 'Manhattan', 'zip' => '10002', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Bernice Harris', 'state' => 'New York', 'borough' => 'Brooklyn', 'zip' => '11207', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Luis Santiago', 'state' => 'New York', 'borough' => 'Bronx', 'zip' => '10460', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Ruth Goldberg', 'state' => 'New York', 'borough' => 'Staten Island', 'zip' => '10304', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Anthony Russo', 'state' => 'New York', 'borough' => 'Queens', 'zip' => '11368', 'gender' => 'male', 'voice' => 'cedar'],
        ['name' => 'Shanice Brown', 'state' => 'New York', 'borough' => 'Manhattan', 'zip' => '10027', 'gender' => 'female', 'voice' => 'marin'],
        ['name' => 'Joseph Okafor', 'state' => 'New York', 'borough' => 'Brooklyn', 'zip' => '11226', 'gender' => 'male', 'voice' => 'cedar'],
    ];

    /** Where an `outside_nyc` consumer actually lives. */
    public const array OUTSIDE_NYC = ['Long Island', 'Yonkers', 'Jersey City'];

    /** Borough traps start at this level. */
    public const int BOROUGH_TRAP_MIN_LEVEL = 3;

    /** About 1 in 5 consumers don't know whether they have Medicaid. */
    private const int DOESNT_KNOW_PERCENT = 20;

    /** Of those who do know, how many name their plan instead of saying "Medicaid". */
    private const int NAMES_PLAN_PERCENT = 25;

    /** From level 3, how many qualified consumers give a vague or tricky borough. */
    private const int BOROUGH_TRAP_PERCENT = 40;

    public function __construct(MealsRoleplayScript $script, ?Randomizer $randomizer = null)
    {
        parent::__construct($script, $randomizer);
    }

    /**
     * @return list<array{name: string, state: string, borough: string, zip: string, gender: 'male'|'female', voice: string}>
     */
    protected function leads(): array
    {
        return self::LEADS;
    }

    /**
     * @return array{outcome: Outcome, voice: RealtimeVoice, persona: array<string, mixed>}
     */
    public function generate(int $level): array
    {
        $generated = parent::generate($level);
        $persona = $generated['persona'];
        $trapId = $persona['dq_trap']['id'] ?? null;

        // Medicare-only consumers are 65 and up; everyone else 30–85.
        $persona['age'] = $trapId === 'medicare_only'
            ? $this->randomizer->getInt(65, 85)
            : $this->randomizer->getInt(30, 85);
        $persona['medicaid'] = $this->medicaid($trapId);

        if ($trapId === 'outside_nyc') {
            $persona['says_borough'] = $this->pick(self::OUTSIDE_NYC);
        } elseif ($level >= self::BOROUGH_TRAP_MIN_LEVEL && $this->chance(self::BOROUGH_TRAP_PERCENT)) {
            // Long Island City is in Queens, so only a Queens lead says it.
            $persona['says_borough'] = $persona['lead']['borough'] === 'Queens' && $this->chance(50)
                ? 'Long Island City'
                : 'New York';
        }

        $generated['persona'] = $persona;

        return $generated;
    }

    /**
     * How the consumer answers the Medicaid question, from their
     * disqualifier when they have one.
     *
     * @return Medicaid
     */
    private function medicaid(?string $trapId): array
    {
        return match ($trapId) {
            'no_medicaid' => ['status' => 'no', 'plan' => null, 'pays_copays' => false],
            'copays' => ['status' => 'dont_know', 'plan' => null, 'pays_copays' => true],
            'medicare_only' => ['status' => 'medicare_only', 'plan' => null, 'pays_copays' => false],
            'non_qualifying_plan' => ['status' => 'names_plan', 'plan' => $this->pick(MealsRoleplayScript::PARENT_BRANDS), 'pays_copays' => false],
            default => match (true) {
                $this->chance(self::DOESNT_KNOW_PERCENT) => ['status' => 'dont_know', 'plan' => null, 'pays_copays' => false],
                $this->chance(self::NAMES_PLAN_PERCENT) => ['status' => 'names_plan', 'plan' => $this->pick(MealsRoleplayScript::QUALIFYING_PLANS), 'pays_copays' => false],
                default => ['status' => 'yes', 'plan' => null, 'pays_copays' => false],
            },
        };
    }

    private function chance(int $percent): bool
    {
        return $this->randomizer->getInt(1, 100) <= $percent;
    }
}
