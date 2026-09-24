<?php

namespace Database\Factories;

use App\Models\CourseExam;
use App\Models\CourseExamRetakeGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseExamRetakeGrant>
 */
class CourseExamRetakeGrantFactory extends Factory
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
            'course_exam_id' => CourseExam::factory(),
            'section' => 'product',
            'granted_by' => null,
        ];
    }
}
