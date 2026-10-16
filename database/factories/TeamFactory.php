<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'name' => fake()->unique()->words(2, true),
        ];
    }

    /**
     * Make the given admin the team's manager by putting them on it.
     */
    public function managedBy(User $admin): static
    {
        return $this->afterCreating(fn (Team $team) => $admin->update(['team_id' => $team->id]));
    }
}
