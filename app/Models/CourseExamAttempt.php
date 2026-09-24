<?php

namespace App\Models;

use Database\Factories\CourseExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt at one section of a final exam. It is "open" (being taken) until
 * submitted_at is set.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_exam_id
 * @property string $section
 * @property array<int, string> $question_ids
 * @property array<string, int>|null $answers
 * @property int|null $score_pct
 * @property array<string, array{correct: int, total: int}>|null $tag_breakdown
 * @property bool|null $passed
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'course_exam_id', 'section', 'question_ids', 'answers', 'score_pct', 'tag_breakdown', 'passed', 'submitted_at'])]
class CourseExamAttempt extends Model
{
    /** @use HasFactory<CourseExamAttemptFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'answers' => 'array',
            'tag_breakdown' => 'array',
            'passed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * @param  Builder<CourseExamAttempt>  $query
     */
    public function scopeSubmitted(Builder $query): void
    {
        $query->whereNotNull('submitted_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CourseExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(CourseExam::class, 'course_exam_id');
    }
}
