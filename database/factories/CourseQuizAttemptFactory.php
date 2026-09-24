<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseQuizAttempt>
 */
class CourseQuizAttemptFactory extends Factory
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
            'course_module_id' => CourseModule::factory(),
            'score_pct' => 100,
            'answers' => [],
            'passed' => true,
        ];
    }

    /**
     * Indicate that the attempt did not reach the passing score.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'score_pct' => 40,
            'passed' => false,
        ]);
    }
}
