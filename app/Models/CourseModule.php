<?php

namespace App\Models;

use Database\Factories\CourseModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_track_id
 * @property string $slug
 * @property string $title
 * @property string|null $summary
 * @property int $est_minutes
 * @property array{id: string, name: string, icon: string}|null $badge
 * @property array{pass_pct: int, questions: array<int, array{id: string, question: string, choices: array<int, string>, answer_index: int, explanation?: string, tags?: array<int, string>}>} $quiz
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'title', 'summary', 'est_minutes', 'badge', 'quiz', 'position'])]
class CourseModule extends Model
{
    /** @use HasFactory<CourseModuleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'badge' => 'array',
            'quiz' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function passPercentage(): int
    {
        return (int) ($this->quiz['pass_pct'] ?? 80);
    }

    /**
     * @return BelongsTo<CourseTrack, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(CourseTrack::class, 'course_track_id');
    }

    /**
     * @return HasMany<CourseLesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('position');
    }

    /**
     * @return HasMany<CourseQuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(CourseQuizAttempt::class);
    }
}
