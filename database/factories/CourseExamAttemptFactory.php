<?php

namespace Database\Factories;

use App\Models\CourseExam;
use App\Models\CourseExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseExamAttempt>
 */
class CourseExamAttemptFactory extends Factory
{
    /**
     * Define the model's default state: a submitted, passing product attempt.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_exam_id' => CourseExam::factory(),
            'section' => 'product',
            'question_ids' => [],
            'answers' => [],
            'score_pct' => 100,
            'tag_breakdown' => [],
            'passed' => true,
            'submitted_at' => now(),
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

    /**
     * Indicate that the attempt has been started but not submitted.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'answers' => null,
            'score_pct' => null,
            'tag_breakdown' => null,
            'passed' => null,
            'submitted_at' => null,
        ]);
    }
}
