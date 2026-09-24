<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_module_id' => CourseModule::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(4),
            'est_minutes' => 5,
            'blocks' => [
                ['type' => 'text', 'html' => '<p>'.fake()->paragraph().'</p>'],
            ],
            'position' => 0,
        ];
    }
}
