<?php

namespace Database\Factories;

use App\Enums\VerticalTrainingStatus;
use App\Models\VerticalTraining;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerticalTraining>
 */
class VerticalTrainingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle().' Training',
            'status' => VerticalTrainingStatus::Active,
        ];
    }

    /**
     * Indicate that the training is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VerticalTrainingStatus::Inactive,
        ]);
    }
}
