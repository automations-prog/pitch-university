<?php

namespace Database\Factories;

use App\Models\CourseExam;
use App\Models\CourseTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseExam>
 */
class CourseExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Two sections, each drawing 5 of 8 questions whose correct answer is always
     * the first choice.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_track_id' => CourseTrack::factory(),
            'title' => 'Final Exam',
            'sections' => [
                $this->section('product', 'Product Quiz', 'prod'),
                $this->section('script', 'Script Quiz', 'script'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $key, string $title, string $prefix): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'pass_pct' => 80,
            'draw' => 5,
            'pool' => collect(range(1, 8))->map(fn (int $number) => [
                'id' => "{$prefix}_{$number}",
                'question' => fake()->sentence().'?',
                'choices' => ['A', 'B', 'C', 'D'],
                'answer_index' => 0,
                'explanation' => fake()->sentence(),
                'tags' => [$key],
            ])->all(),
        ];
    }
}
