<?php

namespace App\Models;

use Database\Factories\CourseExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A track's final exam. Each section (e.g. product, script) draws a random set
 * of questions from its pool for every attempt.
 *
 * @property int $id
 * @property int $course_track_id
 * @property string $title
 * @property array<int, array{key: string, title: string, pass_pct: int, draw: int, pool: array<int, array{id: string, question: string, choices: array<int, string>, answer_index: int, explanation?: string, tags?: array<int, string>}>}> $sections
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'sections'])]
class CourseExam extends Model
{
    /** @use HasFactory<CourseExamFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sections' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sectionKeys(): array
    {
        return array_column($this->sections, 'key');
    }

    public function hasSection(string $section): bool
    {
        return $this->section($section) !== null;
    }

    /**
     * @return array{key: string, title: string, pass_pct: int, draw: int, pool: array<int, array{id: string, question: string, choices: array<int, string>, answer_index: int, explanation?: string, tags?: array<int, string>}>}|null
     */
    public function section(string $section): ?array
    {
        return collect($this->sections)->firstWhere('key', $section);
    }

    /**
     * The number of questions drawn for each attempt at the section.
     */
    public function questionCount(string $section): int
    {
        return min($this->section($section)['draw'], count($this->section($section)['pool']));
    }

    /**
     * The section's question pool keyed by question id.
     *
     * @return Collection<string, array{id: string, question: string, choices: array<int, string>, answer_index: int, explanation?: string, tags?: array<int, string>}>
     */
    public function questionsById(string $section): Collection
    {
        return collect($this->section($section)['pool'])->keyBy('id');
    }

    /**
     * Randomly draw the question ids for a new attempt at the section.
     *
     * @return array<int, string>
     */
    public function drawQuestionIds(string $section): array
    {
        return collect($this->section($section)['pool'])
            ->shuffle()
            ->take($this->questionCount($section))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * @return BelongsTo<CourseTrack, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(CourseTrack::class, 'course_track_id');
    }

    /**
     * @return HasMany<CourseExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(CourseExamAttempt::class);
    }

    /**
     * @return HasMany<CourseExamRetakeGrant, $this>
     */
    public function retakeGrants(): HasMany
    {
        return $this->hasMany(CourseExamRetakeGrant::class);
    }
}
