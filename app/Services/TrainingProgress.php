<?php

namespace App\Services;

use App\Models\CourseExamAttempt;
use App\Models\CourseExamRetakeGrant;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Resolves a user's progress through one training track: which lessons are
 * complete, which quizzes are passed, and what is unlocked.
 *
 * A module unlocks once the previous module's quiz is passed, and a module's
 * quiz unlocks once every lesson in that module is complete.
 */
class TrainingProgress
{
    /** @var Collection<int, CourseModule> */
    private Collection $modules;

    /** @var array<int, bool> */
    private array $completedLessonIds;

    /** @var SupportCollection<int, SupportCollection<int, CourseQuizAttempt>> */
    private SupportCollection $attemptsByModule;

    private ?ExamProgress $examProgress = null;

    /**
     * The user's modules, completed lessons, quiz attempts and exam data may be
     * passed in when already loaded, as forTracks() does for several tracks.
     *
     * @param  Collection<int, CourseModule>|null  $modules  the track's modules with their lessons
     * @param  array<int, bool>|null  $completedLessonIds
     * @param  SupportCollection<int, SupportCollection<int, CourseQuizAttempt>>|null  $attemptsByModule
     * @param  array{attempts: SupportCollection<int, CourseExamAttempt>, grants: array<string, int>}|null  $examData
     */
    public function __construct(
        public User $user,
        public CourseTrack $track,
        ?Collection $modules = null,
        ?array $completedLessonIds = null,
        ?SupportCollection $attemptsByModule = null,
        private ?array $examData = null,
    ) {
        $this->modules = $modules ?? $track->modules()->with('lessons')->get();

        $this->completedLessonIds = $completedLessonIds ?? $user->completedCourseLessons()
            ->pluck('course_lessons.id')
            ->mapWithKeys(fn (int $lessonId) => [$lessonId => true])
            ->all();

        $this->attemptsByModule = $attemptsByModule ?? $user->courseQuizAttempts()
            ->whereIn('course_module_id', $this->modules->modelKeys())
            ->latest('id')
            ->get()
            ->toBase()
            ->groupBy('course_module_id');
    }

    /**
     * Build the user's progress in each of the given tracks with a fixed number
     * of queries, however many tracks there are.
     *
     * @param  Collection<int, CourseTrack>  $tracks
     * @return SupportCollection<int, self>
     */
    public static function forTracks(User $user, Collection $tracks): SupportCollection
    {
        $tracks->loadMissing(['modules.lessons', 'exam']);

        $moduleIds = $tracks->flatMap(fn (CourseTrack $track) => $track->modules->modelKeys())->all();
        $examIds = $tracks->pluck('exam.id')->filter()->all();

        $completedLessonIds = $user->completedCourseLessons()
            ->pluck('course_lessons.id')
            ->mapWithKeys(fn (int $lessonId) => [$lessonId => true])
            ->all();

        $attemptsByModule = $user->courseQuizAttempts()
            ->whereIn('course_module_id', $moduleIds)
            ->latest('id')
            ->get()
            ->toBase()
            ->groupBy('course_module_id');

        $examAttempts = $user->courseExamAttempts()
            ->whereIn('course_exam_id', $examIds)
            ->latest('id')
            ->get()
            ->toBase()
            ->groupBy('course_exam_id');

        $examGrants = CourseExamRetakeGrant::query()
            ->whereBelongsTo($user)
            ->whereIn('course_exam_id', $examIds)
            ->selectRaw('course_exam_id, section, count(*) as grants_count')
            ->groupBy('course_exam_id', 'section')
            ->toBase()
            ->get()
            ->groupBy('course_exam_id')
            ->map(fn (SupportCollection $rows) => $rows->mapWithKeys(fn (object $row) => [$row->section => (int) $row->grants_count])->all());

        return $tracks->toBase()->map(function (CourseTrack $track) use ($user, $completedLessonIds, $attemptsByModule, $examAttempts, $examGrants) {
            return new self(
                $user,
                $track,
                $track->modules,
                $completedLessonIds,
                $attemptsByModule,
                $track->exam === null ? null : [
                    'attempts' => $examAttempts->get($track->exam->id, collect()),
                    'grants' => $examGrants->get($track->exam->id, []),
                ],
            );
        })->values();
    }

    /**
     * The user's standing on the track's final exam, or null when the track
     * has none.
     */
    public function examProgress(): ?ExamProgress
    {
        if ($this->track->exam === null) {
            return null;
        }

        return $this->examProgress ??= new ExamProgress(
            $this->user,
            $this->track->exam,
            $this->modulesPassed(),
            $this->examData['attempts'] ?? null,
            $this->examData['grants'] ?? null,
        );
    }

    /**
     * Whether the track has modules and every one of them is passed.
     */
    public function modulesPassed(): bool
    {
        $summary = $this->trackSummary();

        return $summary['total_modules'] > 0 && $summary['passed_modules'] === $summary['total_modules'];
    }

    /**
     * @return Collection<int, CourseModule>
     */
    public function modules(): Collection
    {
        return $this->modules;
    }

    /**
     * Return the loaded copy of the given module (with its lessons).
     */
    public function module(CourseModule $module): CourseModule
    {
        return $this->modules->firstWhere('id', $module->id) ?? $module->load('lessons');
    }

    public function isLessonComplete(CourseLesson $lesson): bool
    {
        return isset($this->completedLessonIds[$lesson->id]);
    }

    public function completedLessonCount(CourseModule $module): int
    {
        return $this->module($module)->lessons->filter(fn (CourseLesson $lesson) => $this->isLessonComplete($lesson))->count();
    }

    public function isModuleUnlocked(CourseModule $module): bool
    {
        $index = $this->modules->search(fn (CourseModule $candidate) => $candidate->id === $module->id);

        if ($index === false || $index === 0) {
            return true;
        }

        return $this->isModulePassed($this->modules[$index - 1]);
    }

    public function isQuizUnlocked(CourseModule $module): bool
    {
        $module = $this->module($module);

        return $this->isModuleUnlocked($module)
            && $this->completedLessonCount($module) === $module->lessons->count();
    }

    public function isModulePassed(CourseModule $module): bool
    {
        return $this->attempts($module)->contains('passed', true);
    }

    public function bestScore(CourseModule $module): ?int
    {
        return $this->attempts($module)->max('score_pct');
    }

    public function latestAttempt(CourseModule $module): ?CourseQuizAttempt
    {
        return $this->attempts($module)->first();
    }

    /**
     * @return SupportCollection<int, CourseQuizAttempt>
     */
    public function attempts(CourseModule $module): SupportCollection
    {
        return $this->attemptsByModule->get($module->id, collect());
    }

    /**
     * The progress summary shown on module cards and module headers.
     *
     * @return array{is_unlocked: bool, is_quiz_unlocked: bool, is_passed: bool, completed_lessons: int, total_lessons: int, best_score: int|null, attempts_count: int}
     */
    public function summary(CourseModule $module): array
    {
        $module = $this->module($module);

        return [
            'is_unlocked' => $this->isModuleUnlocked($module),
            'is_quiz_unlocked' => $this->isQuizUnlocked($module),
            'is_passed' => $this->isModulePassed($module),
            'completed_lessons' => $this->completedLessonCount($module),
            'total_lessons' => $module->lessons->count(),
            'best_score' => $this->bestScore($module),
            'attempts_count' => $this->attempts($module)->count(),
        ];
    }

    /**
     * The progress summary shown on track cards.
     *
     * @return array{passed_modules: int, total_modules: int}
     */
    public function trackSummary(): array
    {
        return [
            'passed_modules' => $this->modules->filter(fn (CourseModule $module) => $this->isModulePassed($module))->count(),
            'total_modules' => $this->modules->count(),
        ];
    }

    /**
     * The best quiz score of each module the user has attempted.
     *
     * @return SupportCollection<int, int>
     */
    public function bestScores(): SupportCollection
    {
        return $this->modules->toBase()
            ->map(fn (CourseModule $module) => $this->bestScore($module))
            ->filter(fn (?int $score) => $score !== null)
            ->values();
    }

    /**
     * The average of the user's best quiz score per attempted module, or null
     * when no quiz has been attempted yet.
     */
    public function averageScore(): ?int
    {
        $scores = $this->bestScores();

        return $scores->isEmpty() ? null : (int) round($scores->avg());
    }

    /**
     * The track summary plus final exam certification, and whether the track
     * counts as complete: certified when it has an exam, otherwise every module passed.
     *
     * @return array{passed_modules: int, total_modules: int, has_exam: bool, is_certified: bool, is_complete: bool, average_score: int|null}
     */
    public function trackStatus(): array
    {
        $examProgress = $this->examProgress();
        $isCertified = $examProgress?->isPassed() ?? false;

        return [
            ...$this->trackSummary(),
            'has_exam' => $examProgress !== null,
            'is_certified' => $isCertified,
            'is_complete' => $examProgress !== null ? $isCertified : $this->modulesPassed(),
            'average_score' => $this->averageScore(),
        ];
    }
}
