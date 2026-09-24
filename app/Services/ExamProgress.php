<?php

namespace App\Services;

use App\Models\CourseExam;
use App\Models\CourseExamAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves a user's standing on a track's final exam.
 *
 * The exam unlocks once every module in the track is passed. Each section is
 * attempted and retaken on its own: a user gets BASE_ATTEMPTS attempts per
 * section plus one per admin-granted retake, and the exam is passed (the user
 * is certified) once every section has a passing attempt.
 */
class ExamProgress
{
    /**
     * Attempts allowed per section before an admin must grant a retake.
     */
    public const BASE_ATTEMPTS = 2;

    /** @var Collection<string, Collection<int, CourseExamAttempt>> */
    private Collection $attemptsBySection;

    /** @var array<string, int> */
    private array $grantsBySection;

    public function __construct(public User $user, public CourseExam $exam, private bool $modulesPassed)
    {
        $this->attemptsBySection = $exam->attempts()
            ->whereBelongsTo($user)
            ->latest('id')
            ->get()
            ->toBase()
            ->groupBy('section');

        $this->grantsBySection = $exam->retakeGrants()
            ->whereBelongsTo($user)
            ->selectRaw('section, count(*) as grants_count')
            ->groupBy('section')
            ->pluck('grants_count', 'section')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Build the exam progress for a user on a track, given their module progress.
     */
    public static function forTrack(User $user, CourseExam $exam, TrainingProgress $trackProgress): self
    {
        $summary = $trackProgress->trackSummary();

        return new self($user, $exam, $summary['total_modules'] > 0 && $summary['passed_modules'] === $summary['total_modules']);
    }

    public function isUnlocked(): bool
    {
        return $this->modulesPassed;
    }

    public function isPassed(): bool
    {
        return collect($this->exam->sectionKeys())->every(fn (string $section) => $this->isSectionPassed($section));
    }

    public function isSectionPassed(string $section): bool
    {
        return $this->submittedAttempts($section)->contains('passed', true);
    }

    public function attemptsUsed(string $section): int
    {
        return $this->submittedAttempts($section)->count();
    }

    public function attemptsAllowed(string $section): int
    {
        return self::BASE_ATTEMPTS + ($this->grantsBySection[$section] ?? 0);
    }

    public function isOutOfAttempts(string $section): bool
    {
        return ! $this->isSectionPassed($section) && $this->attemptsUsed($section) >= $this->attemptsAllowed($section);
    }

    /**
     * The attempt currently being taken for the section, if any.
     */
    public function openAttempt(string $section): ?CourseExamAttempt
    {
        return $this->attempts($section)->first(fn (CourseExamAttempt $attempt) => ! $attempt->isSubmitted());
    }

    public function canStart(string $section): bool
    {
        return $this->isUnlocked()
            && ! $this->isSectionPassed($section)
            && ($this->openAttempt($section) !== null || ! $this->isOutOfAttempts($section));
    }

    /**
     * @return Collection<int, CourseExamAttempt>
     */
    public function attempts(string $section): Collection
    {
        return $this->attemptsBySection->get($section, collect());
    }

    /**
     * @return Collection<int, CourseExamAttempt>
     */
    public function submittedAttempts(string $section): Collection
    {
        return $this->attempts($section)->filter(fn (CourseExamAttempt $attempt) => $attempt->isSubmitted())->values();
    }

    /**
     * locked | not_started | in_progress | failed | passed. "failed" means a
     * section is out of attempts and needs an admin-granted retake.
     */
    public function status(): string
    {
        return match (true) {
            $this->isPassed() => 'passed',
            ! $this->isUnlocked() => 'locked',
            collect($this->exam->sectionKeys())->contains(fn (string $section) => $this->isOutOfAttempts($section)) => 'failed',
            $this->attemptsBySection->isNotEmpty() => 'in_progress',
            default => 'not_started',
        };
    }

    /**
     * The summary shown to the user and to admins. Correct answers are never included.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'title' => $this->exam->title,
            'status' => $this->status(),
            'is_unlocked' => $this->isUnlocked(),
            'is_passed' => $this->isPassed(),
            'sections' => collect($this->exam->sectionKeys())->map(fn (string $section) => [
                'key' => $section,
                'title' => $this->exam->section($section)['title'],
                'pass_pct' => $this->exam->section($section)['pass_pct'],
                'question_count' => $this->exam->questionCount($section),
                'attempts_used' => $this->attemptsUsed($section),
                'attempts_allowed' => $this->attemptsAllowed($section),
                'is_passed' => $this->isSectionPassed($section),
                'is_out_of_attempts' => $this->isOutOfAttempts($section),
                'has_open_attempt' => $this->openAttempt($section) !== null,
                'can_start' => $this->canStart($section),
                'best_score' => $this->submittedAttempts($section)->max('score_pct'),
                'attempts' => $this->submittedAttempts($section)->map(fn (CourseExamAttempt $attempt) => [
                    'id' => $attempt->id,
                    'score_pct' => $attempt->score_pct,
                    'passed' => $attempt->passed,
                    'tag_breakdown' => $attempt->tag_breakdown,
                    'submitted_at' => $attempt->submitted_at,
                ])->all(),
            ])->all(),
        ];
    }
}
