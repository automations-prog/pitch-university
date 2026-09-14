<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseStep>
 */
class LicenseStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'order' => 0,
        ];
    }
}
