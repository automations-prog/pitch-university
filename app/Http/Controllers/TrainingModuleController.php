<?php

namespace App\Http\Controllers;

use App\Concerns\PresentsCourseContent;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CourseTrack;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every training track's modules to read through, all unlocked and
 * whether or not the track is assigned to the viewer. Nothing here is
 * recorded: lessons aren't marked complete and the quiz is practice only.
 */
class TrainingModuleController extends Controller
{
    use PresentsCourseContent;

    /**
     * Display every track.
     */
    public function index(): Response
    {
        $tracks = CourseTrack::query()
            ->orderBy('position')
            ->withCount('modules')
            ->get();

        return Inertia::render('training-modules/index', [
            'tracks' => $tracks->map(fn (CourseTrack $track) => [
                ...$this->trackData($track),
                'module_count' => $track->modules_count,
            ]),
        ]);
    }

    /**
     * Display a track's modules, all open.
     */
    public function showTrack(CourseTrack $track): Response
    {
        $modules = $track->modules()->withCount('lessons')->get();

        return Inertia::render('training-modules/track', [
            'track' => $this->trackData($track),
            'modules' => $modules->map(fn (CourseModule $module) => [
                ...$this->moduleData($module),
                'lesson_count' => $module->lessons_count,
                'question_count' => count($module->quiz['questions']),
            ]),
        ]);
    }

    /**
     * Display a module's lessons.
     */
    public function showModule(CourseTrack $track, CourseModule $module): Response
    {
        return Inertia::render('training-modules/module', [
            'track' => $this->trackData($track),
            'module' => $this->moduleData($module),
            'lessons' => $module->lessons->map(fn (CourseLesson $lesson) => $this->lessonData($lesson)),
            'questionCount' => count($module->quiz['questions']),
        ]);
    }

    /**
     * Display a single lesson.
     */
    public function showLesson(CourseTrack $track, CourseModule $module, CourseLesson $lesson): Response
    {
        $index = $module->lessons->search(fn (CourseLesson $candidate) => $candidate->id === $lesson->id);
        $previous = $index > 0 ? $module->lessons->get($index - 1) : null;
        $next = $module->lessons->get($index + 1);

        return Inertia::render('training-modules/lesson', [
            'track' => $this->trackData($track),
            'module' => $this->moduleData($module),
            'lesson' => [
                ...$this->lessonData($lesson),
                'blocks' => $lesson->blocks,
            ],
            'lessonNumber' => $index + 1,
            'totalLessons' => $module->lessons->count(),
            'previousLesson' => $previous ? $this->lessonData($previous) : null,
            'nextLesson' => $next ? $this->lessonData($next) : null,
        ]);
    }

    /**
     * Display a module's quiz as practice, with its answers, which is
     * checked in the browser and never saved.
     */
    public function showQuiz(CourseTrack $track, CourseModule $module): Response
    {
        return Inertia::render('training-modules/quiz', [
            'track' => $this->trackData($track),
            'module' => $this->moduleData($module),
            'passPercentage' => $module->passPercentage(),
            'questions' => collect($module->quiz['questions'])->map(fn (array $question) => [
                'id' => $question['id'],
                'question' => $question['question'],
                'choices' => $question['choices'],
                'answer_index' => $question['answer_index'],
                'explanation' => $question['explanation'] ?? null,
            ]),
        ]);
    }
}
