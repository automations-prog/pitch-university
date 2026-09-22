<?php

namespace Database\Factories;

use App\Models\Screening;
use App\Models\ScreeningResponse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'token' => Str::random(40),
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone_number' => fake()->phoneNumber(),
        ];
    }
}
