<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitCourseQuizRequest;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Services\ExamProgress;
use App\Services\TrainingProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    /**
     * Display the training tracks toggled on for the user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $tracks = $user->courseTracks()->with('exam')->orderBy('position')->get();

        return Inertia::render('training/index', [
            'tracks' => $tracks->map(function (CourseTrack $track) use ($user) {
                $progress = new TrainingProgress($user, $track);

                return [
                    ...$this->trackData($track),
                    'progress' => [
                        ...$progress->trackSummary(),
                        'has_exam' => $track->exam !== null,
                        'is_certified' => $track->exam !== null && ExamProgress::forTrack($user, $track->exam, $progress)->isPassed(),
                    ],
                ];
            }),
        ]);
    }

    /**
     * Display a track's modules with the user's progress.
     */
    public function showTrack(Request $request, CourseTrack $track): Response
    {
        Gate::authorize('view', $track);

        $progress = new TrainingProgress($request->user(), $track);

        return Inertia::render('training/track', [
            'track' => $this->trackData($track),
            'modules' => $progress->modules()->map(fn (CourseModule $module) => [
                ...$this->moduleData($module),
                'progress' => $progress->summary($module),
            ]),
            'exam' => $track->exam
                ? ExamProgress::forTrack($request->user(), $track->exam, $progress)->summary()
                : null,
        ]);
    }

    /**
     * Display a module's lessons and quiz status.
     */
    public function showModule(Request $request, CourseTrack $track, CourseModule $module): Response
    {
        $progress = $this->unlockedModuleProgress($request, $track, $module);
        $module = $progress->module($module);

        return Inertia::render('training/module', [
            'track' => $this->trackData($track),
            'module' => $this->moduleData($module),
            'progress' => $progress->summary($module),
            'lessons' => $module->lessons->map(fn (CourseLesson $lesson) => [
                ...$this->lessonData($lesson),
                'is_complete' => $progress->isLessonComplete($lesson),
            ]),
            'questionCount' => count($module->quiz['questions']),
            'passPercentage' => $module->passPercentage(),
        ]);
    }

    /**
     * Display a single lesson.
     */
    public function showLesson(Request $request, CourseTrack $track, CourseModule $module, CourseLesson $lesson): Response
    {
        $progress = $this->unlockedModuleProgress($request, $track, $module);
        $module = $progress->module($module);

        $index = $module->lessons->search(fn (CourseLesson $candidate) => $candidate->id === $lesson->id);
        $previous = $index > 0 ? $module->lessons->get($index - 1) : null;
        $next = $module->lessons->get($index + 1);

        return Inertia::render('training/lesson', [
            'track' => $this->trackData($track),
            'module' => $this->moduleData($module),
            'lesson' => [
                ...$this->lessonData($lesson),
                'blocks' => $lesson->blocks,
                'is_complete' => $progress->isLessonComplete($lesson),
            ],
            'lessonNumber' => $index + 1,
            'totalLessons' => $module->lessons->count(),
            'previousLesson' => $previous ? $this->lessonData($previous) : null,
            'nextLesson' => $next ? $this->lessonData($next) : null,
        ]);
    }

    /**
     * Mark a lesson complete and move on to the next lesson (or back to the module).
     */
    public function completeLesson(Request $request, CourseTrack $track, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        $progress = $this->unlockedModuleProgress($request, $track, $module);
        $module = $progress->module($module);

        $request->user()->completedCourseLessons()->syncWithoutDetaching([$lesson->id]);

        $next = $module->lessons->firstWhere('position', '>', $lesson->position);

        return $next
            ? to_route('training.lessons.show', [$track, $module, $next])
            : to_route('training.modules.show', [$track, $module]);
    }

    /**
     * Display a module's quiz. Correct answers are only included for a submitted attempt.
     */
    public function showQuiz(Request $request, CourseTrack $track, CourseModule $module): Response
    {
        $progress = $this->unlockedQuizProgress($request, $track, $module);
        $module = $progress->module($module);

        $latestAttempt = $progress->latestAttempt($module);

        return Inertia::render('training/quiz', [
            'track' => $this->trackData($track),
            'module' => $this->moduleData($module),
            'passPercentage' => $module->passPercentage(),
            'questions' => collect($module->quiz['questions'])->map(fn (array $question) => [
                'id' => $question['id'],
                'question' => $question['question'],
                'choices' => $question['choices'],
            ]),
            'latestAttempt' => $latestAttempt ? $this->attemptResult($module, $latestAttempt) : null,
            'isPassed' => $progress->isModulePassed($module),
            'nextModule' => $this->nextModuleData($progress, $module),
        ]);
    }

    /**
     * Grade and store a quiz attempt.
     */
    public function submitQuiz(SubmitCourseQuizRequest $request, CourseTrack $track, CourseModule $module): RedirectResponse
    {
        $progress = $this->unlockedQuizProgress($request, $track, $module);
        $module = $progress->module($module);

        $questions = collect($module->quiz['questions']);

        /** @var array<string, int> $answers */
        $answers = $questions
            ->mapWithKeys(fn (array $question) => [$question['id'] => (int) $request->validated("answers.{$question['id']}")])
            ->all();

        $correctCount = $questions->filter(fn (array $question) => $answers[$question['id']] === $question['answer_index'])->count();
        $scorePercentage = (int) round($correctCount / $questions->count() * 100);

        $request->user()->courseQuizAttempts()->create([
            'course_module_id' => $module->id,
            'score_pct' => $scorePercentage,
            'answers' => $answers,
            'passed' => $scorePercentage >= $module->passPercentage(),
        ]);

        return to_route('training.quiz.show', [$track, $module]);
    }

    /**
     * Authorize access to the track and ensure the module is unlocked.
     */
    private function unlockedModuleProgress(Request $request, CourseTrack $track, CourseModule $module): TrainingProgress
    {
        Gate::authorize('view', $track);

        $progress = new TrainingProgress($request->user(), $track);

        abort_unless($progress->isModuleUnlocked($module), 403, 'Pass the previous module to unlock this one.');

        return $progress;
    }

    /**
     * Authorize access to the track and ensure the module's quiz is unlocked.
     */
    private function unlockedQuizProgress(Request $request, CourseTrack $track, CourseModule $module): TrainingProgress
    {
        $progress = $this->unlockedModuleProgress($request, $track, $module);

        abort_unless($progress->isQuizUnlocked($module), 403, 'Complete every lesson to unlock the quiz.');

        return $progress;
    }

    /**
     * @return array{slug: string, name: string, description: string|null}
     */
    private function trackData(CourseTrack $track): array
    {
        return [
            'slug' => $track->slug,
            'name' => $track->name,
            'description' => $track->description,
        ];
    }

    /**
     * @return array{slug: string, title: string, summary: string|null, est_minutes: int, badge: array<string, string>|null}
     */
    private function moduleData(CourseModule $module): array
    {
        return [
            'slug' => $module->slug,
            'title' => $module->title,
            'summary' => $module->summary,
            'est_minutes' => $module->est_minutes,
            'badge' => $module->badge,
        ];
    }

    /**
     * @return array{slug: string, title: string, est_minutes: int}
     */
    private function lessonData(CourseLesson $lesson): array
    {
        return [
            'slug' => $lesson->slug,
            'title' => $lesson->title,
            'est_minutes' => $lesson->est_minutes,
        ];
    }

    /**
     * @return array{score_pct: int, passed: bool, correct_count: int, created_at: mixed, results: array<int, array{id: string, selected_index: int|null, answer_index: int, is_correct: bool, explanation: string|null}>}
     */
    private function attemptResult(CourseModule $module, CourseQuizAttempt $attempt): array
    {
        $results = collect($module->quiz['questions'])->map(fn (array $question) => [
            'id' => $question['id'],
            'selected_index' => $attempt->answers[$question['id']] ?? null,
            'answer_index' => $question['answer_index'],
            'is_correct' => ($attempt->answers[$question['id']] ?? null) === $question['answer_index'],
            'explanation' => $question['explanation'] ?? null,
        ]);

        return [
            'score_pct' => $attempt->score_pct,
            'passed' => $attempt->passed,
            'correct_count' => $results->where('is_correct', true)->count(),
            'created_at' => $attempt->created_at,
            'results' => $results->all(),
        ];
    }

    /**
     * @return array{slug: string, title: string}|null
     */
    private function nextModuleData(TrainingProgress $progress, CourseModule $module): ?array
    {
        $next = $progress->modules()->firstWhere('position', '>', $module->position);

        return $next ? ['slug' => $next->slug, 'title' => $next->title] : null;
    }
}
