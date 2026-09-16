<?php

namespace Database\Factories;

use App\Models\Screening;
use App\Models\ScreeningResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreeningResponse>
 */
class ScreeningResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'screening_id' => Screening::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'birthday' => fake()->date(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }
}
