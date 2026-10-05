<?php

namespace Database\Factories;

use App\Enums\Outcome;
use App\Enums\RealtimeVoice;
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
}
