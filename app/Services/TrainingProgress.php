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
 * A module's quiz unlocks once every lesson in that module is complete. A
 * module with a roleplay unlocks it once the quiz is passed, and the module
 * only counts as passed once the roleplay is too. The next module unlocks
 * once the previous module is passed.
 *
 * Trainees who passed a later module's quiz before a module got its
 * roleplay skip that roleplay rather than being locked out again.
 */
class TrainingProgress
{
    /** @var Collection<int, CourseModule> */
    private Collection $modules;

    /** @var array<int, bool> */
    private array $completedLessonIds;

    /** @var SupportCollection<int, SupportCollection<int, CourseQuizAttempt>> */
    private SupportCollection $attemptsByModule;

    /** @var array<int, array{attempts_count: int, passed: bool}> */
    private array $roleplayResults;

    private ?ExamProgress $examProgress = null;

    /**
     * The user's modules, completed lessons, quiz attempts and exam data may be
     * passed in when already loaded, as forTracks() does for several tracks.
     *
     * @param  Collection<int, CourseModule>|null  $modules  the track's modules with their lessons
     * @param  array<int, bool>|null  $completedLessonIds
     * @param  SupportCollection<int, SupportCollection<int, CourseQuizAttempt>>|null  $attemptsByModule
     * @param  array{attempts: SupportCollection<int, CourseExamAttempt>, grants: array<string, int>}|null  $examData
     * @param  array<int, array{attempts_count: int, passed: bool}>|null  $roleplayResults  finished module roleplay calls by module id
     */
    public function __construct(
        public User $user,
        public CourseTrack $track,
        ?Collection $modules = null,
        ?array $completedLessonIds = null,
        ?SupportCollection $attemptsByModule = null,
        private ?array $examData = null,
        ?array $roleplayResults = null,
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

        $this->roleplayResults = $roleplayResults ?? self::roleplayResults($user, $this->modules->modelKeys());
    }

    /**
     * How many finished roleplay calls the user made for each module, and
     * whether any passed.
     *
     * @param  list<int>  $moduleIds
     * @return array<int, array{attempts_count: int, passed: bool}>
     */
    private static function roleplayResults(User $user, array $moduleIds): array
    {
        return $user->roleplaySessions()
            ->whereIn('course_module_id', $moduleIds)
            ->whereNotNull('ended_at')
            ->selectRaw('course_module_id, count(*) as attempts_count, max(passed) as passed')
            ->groupBy('course_module_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->course_module_id => [
                'attempts_count' => (int) $row->attempts_count,
                'passed' => (bool) $row->passed,
            ]])
            ->all();
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

        $roleplayResults = self::roleplayResults($user, $moduleIds);

        return $tracks->toBase()->map(function (CourseTrack $track) use ($user, $completedLessonIds, $attemptsByModule, $examAttempts, $examGrants, $roleplayResults) {
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
                $roleplayResults,
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

    public function isQuizPassed(CourseModule $module): bool
    {
        return $this->attempts($module)->contains('passed', true);
    }

    /**
     * Whether the module's quiz is passed and, when it has one, its roleplay
     * is passed or skipped.
     */
    public function isModulePassed(CourseModule $module): bool
    {
        return $this->isQuizPassed($module)
            && in_array($this->roleplayStatus($module), [null, 'passed', 'skipped'], true);
    }

    public function isRoleplayUnlocked(CourseModule $module): bool
    {
        $module = $this->module($module);

        return $module->hasRoleplay()
            && $this->isModuleUnlocked($module)
            && $this->isQuizPassed($module);
    }

    /**
     * Where the user stands on the module's roleplay, or null when the
     * module has none.
     *
     * @return 'passed'|'skipped'|'locked'|'failed'|'not_started'|null
     */
    public function roleplayStatus(CourseModule $module): ?string
    {
        $module = $this->module($module);

        if (! $module->hasRoleplay()) {
            return null;
        }

        return match (true) {
            $this->roleplayResults[$module->id]['passed'] ?? false => 'passed',
            $this->isLaterQuizPassed($module) => 'skipped',
            ! $this->isRoleplayUnlocked($module) => 'locked',
            $this->roleplayAttemptsCount($module) > 0 => 'failed',
            default => 'not_started',
        };
    }

    public function roleplayAttemptsCount(CourseModule $module): int
    {
        return $this->roleplayResults[$module->id]['attempts_count'] ?? 0;
    }

    /**
     * Whether the user passed the quiz of any module after this one, which
     * only happens when they got there before this module had a roleplay.
     */
    private function isLaterQuizPassed(CourseModule $module): bool
    {
        return $this->modules
            ->filter(fn (CourseModule $candidate) => $candidate->position > $module->position)
            ->contains(fn (CourseModule $candidate) => $this->isQuizPassed($candidate));
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
     * @return array{is_unlocked: bool, is_quiz_unlocked: bool, is_quiz_passed: bool, is_passed: bool, completed_lessons: int, total_lessons: int, best_score: int|null, attempts_count: int, roleplay: array{title: string, status: string, attempts_count: int}|null}
     */
    public function summary(CourseModule $module): array
    {
        $module = $this->module($module);
        $roleplayStatus = $this->roleplayStatus($module);

        return [
            'is_unlocked' => $this->isModuleUnlocked($module),
            'is_quiz_unlocked' => $this->isQuizUnlocked($module),
            'is_quiz_passed' => $this->isQuizPassed($module),
            'is_passed' => $this->isModulePassed($module),
            'completed_lessons' => $this->completedLessonCount($module),
            'total_lessons' => $module->lessons->count(),
            'best_score' => $this->bestScore($module),
            'attempts_count' => $this->attempts($module)->count(),
            'roleplay' => $roleplayStatus === null ? null : [
                'title' => $module->roleplay['title'],
                'status' => $roleplayStatus,
                'attempts_count' => $this->roleplayAttemptsCount($module),
            ],
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
