<?php

namespace Database\Factories;

use App\Enums\LicenseStatus;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle().' License',
            'status' => LicenseStatus::Active,
        ];
    }

    /**
     * Indicate that the license is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LicenseStatus::Inactive,
        ]);
    }
}
