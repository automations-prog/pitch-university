<?php

namespace App\Models;

use App\Enums\RoleplayVertical;
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
 * @property array{vertical: string, title: string, briefing: string, context: string, consumer_brief?: string|null, start_section: string, end_section: string, max_seconds: int, transfer: bool, code_call: bool, level: int, outcomes?: array{transfer?: int, dq?: int, dnc?: int}, objections?: list<string>, objection_count?: array{0: int, 1: int}, dq_traps?: list<string>, required_checks?: list<string>, judged?: list<array{key: string, label: string, rubric: string}>}|null $roleplay
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'title', 'summary', 'est_minutes', 'badge', 'quiz', 'roleplay', 'position'])]
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
            'roleplay' => 'array',
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
     * Whether the module ends with a short roleplay scene the trainee must
     * pass after the quiz.
     */
    public function hasRoleplay(): bool
    {
        return $this->roleplay !== null;
    }

    public function roleplayVertical(): ?RoleplayVertical
    {
        return $this->hasRoleplay() ? RoleplayVertical::from($this->roleplay['vertical']) : null;
    }

    /**
     * The part of the roleplay a call's session keeps, so a call is run and
     * graded by the scene it started with, even if the module changes.
     *
     * @return array{title: string, context: string, consumer_brief: string|null, start_section: string, max_seconds: int, transfer: bool, code_call: bool, required_checks: list<string>, judged: list<array{key: string, label: string, rubric: string}>}
     */
    public function roleplayScene(): array
    {
        return [
            'title' => $this->roleplay['title'],
            'context' => $this->roleplay['context'],
            'consumer_brief' => $this->roleplay['consumer_brief'] ?? null,
            'start_section' => $this->roleplay['start_section'],
            'max_seconds' => $this->roleplay['max_seconds'],
            'transfer' => $this->roleplay['transfer'],
            'code_call' => $this->roleplay['code_call'],
            'required_checks' => $this->roleplay['required_checks'] ?? [],
            'judged' => $this->roleplay['judged'] ?? [],
        ];
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

    /**
     * @return HasMany<RoleplaySession, $this>
     */
    public function roleplaySessions(): HasMany
    {
        return $this->hasMany(RoleplaySession::class);
    }
}
