<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\CourseTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseModule>
 */
class CourseModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_track_id' => CourseTrack::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(12),
            'est_minutes' => 20,
            'badge' => ['id' => fake()->unique()->slug(2), 'name' => fake()->words(2, true), 'icon' => '🎓'],
            'quiz' => [
                'pass_pct' => 80,
                'questions' => collect(range(1, 5))->map(fn (int $number) => [
                    'id' => "q{$number}",
                    'question' => fake()->sentence().'?',
                    'choices' => ['A', 'B', 'C', 'D'],
                    'answer_index' => 0,
                    'explanation' => fake()->sentence(),
                ])->all(),
            ],
            'position' => 0,
        ];
    }
}
