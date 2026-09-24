<?php

namespace App\Services;

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

    public function __construct(public User $user, public CourseTrack $track)
    {
        $this->modules = $track->modules()->with('lessons')->get();

        $this->completedLessonIds = $user->completedCourseLessons()
            ->pluck('course_lessons.id')
            ->mapWithKeys(fn (int $lessonId) => [$lessonId => true])
            ->all();

        $this->attemptsByModule = $user->courseQuizAttempts()
            ->whereIn('course_module_id', $this->modules->modelKeys())
            ->latest('id')
            ->get()
            ->toBase()
            ->groupBy('course_module_id');
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
}
