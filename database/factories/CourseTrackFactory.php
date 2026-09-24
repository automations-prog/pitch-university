<?php

namespace Database\Factories;

use App\Models\CourseTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTrack>
 */
class CourseTrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(10),
            'position' => 0,
        ];
    }
}
