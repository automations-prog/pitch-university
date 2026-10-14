<?php

namespace Database\Factories;

use App\Enums\Outcome;
use App\Enums\RealtimeVoice;
use App\Enums\RoleplayVertical;
use App\Models\RoleplaySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleplaySession>
 */
class RoleplaySessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vertical' => RoleplayVertical::Medicare,
            'level' => 2,
            'persona' => [
                'lead' => ['name' => 'Dorothy Miller', 'state' => 'Florida', 'zip' => '33511', 'gender' => 'female'],
                'objections' => ['busy'],
                'quirk' => 'dog barking the whole call',
                'patience' => 8,
                'dq_trap' => null,
            ],
            'expected_outcome' => Outcome::Transfer,
            'voice' => RealtimeVoice::Coral,
        ];
    }

    /**
     * The call session has been minted.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'started_at' => now(),
        ]);
    }

    /**
     * The call has been completed.
     */
    public function ended(): static
    {
        return $this->started()->state(fn (array $attributes) => [
            'ended_at' => now(),
            'disposition' => 'transfer',
            'end_reason' => 'agent',
            'passed' => true,
        ]);
    }

    /**
     * The persona should be disqualified by the given trap.
     */
    public function disqualified(string $trapId = 'va', string $hiddenTruth = 'Has VA health care (military veteran).'): static
    {
        return $this->state(fn (array $attributes) => [
            'expected_outcome' => Outcome::Dq,
            'persona' => [
                ...$attributes['persona'],
                'dq_trap' => ['id' => $trapId, 'hidden_truth' => $hiddenTruth],
            ],
        ]);
    }

    /**
     * An ACA call: a working-age lead who is a Marketplace plan holder.
     */
    public function aca(): static
    {
        return $this->state(fn (array $attributes) => [
            'vertical' => RoleplayVertical::Aca,
            'persona' => [
                'lead' => ['name' => 'Ashley Turner', 'state' => 'Texas', 'zip' => '75217', 'gender' => 'female'],
                'objections' => ['what_is_subsidy'],
                'quirk' => 'kids shouting in the background',
                'patience' => 8,
                'dq_trap' => null,
                'age' => 34,
                'coverage' => ['has_marketplace_plan' => true, 'carrier' => null, 'knows_what_they_have' => true, 'receives_ssi' => false],
            ],
        ]);
    }

    /**
     * A Medicaid meals call: a Bronx lead who has Medicaid.
     */
    public function meals(): static
    {
        return $this->state(fn (array $attributes) => [
            'vertical' => RoleplayVertical::Meals,
            'persona' => [
                'lead' => ['name' => 'Maria Rodriguez', 'state' => 'New York', 'borough' => 'Bronx', 'zip' => '10453', 'gender' => 'female'],
                'objections' => ['what_is_this'],
                'quirk' => 'TV loud in the background',
                'patience' => 8,
                'dq_trap' => null,
                'age' => 67,
                'medicaid' => ['status' => 'yes', 'plan' => null, 'pays_copays' => false],
            ],
        ]);
    }
}
