<?php

namespace App\Http\Controllers;

use App\Models\CourseExam;
use App\Models\CourseExamAttempt;
use App\Models\CourseTrack;
use App\Services\ExamProgress;
use App\Services\TrainingProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrainingExamController extends Controller
{
    /**
     * Display the track's final exam: each section's status and latest result.
     */
    public function show(Request $request, CourseTrack $track): Response
    {
        [$exam, $progress] = $this->examProgress($request, $track);

        return Inertia::render('training/exam', [
            'track' => ['slug' => $track->slug, 'name' => $track->name],
            'exam' => $progress->summary(),
            'baseAttempts' => ExamProgress::BASE_ATTEMPTS,
        ]);
    }

    /**
     * Start an attempt at a section (or resume the open one) and go take it.
     */
    public function start(Request $request, CourseTrack $track, string $section): RedirectResponse
    {
        [$exam, $progress] = $this->examProgress($request, $track, $section);

        abort_unless($progress->canStart($section), 403, 'This section is not available to take.');

        if ($progress->openAttempt($section) === null) {
            $request->user()->courseExamAttempts()->create([
                'course_exam_id' => $exam->id,
                'section' => $section,
                'question_ids' => $exam->drawQuestionIds($section),
            ]);
        }

        return to_route('training.exam.take', [$track, $section]);
    }

    /**
     * Display the open attempt's questions. Correct answers are never sent.
     */
    public function take(Request $request, CourseTrack $track, string $section): Response|RedirectResponse
    {
        [$exam, $progress] = $this->examProgress($request, $track, $section);

        $attempt = $progress->openAttempt($section);

        if ($attempt === null) {
            return to_route('training.exam.show', $track);
        }

        $questions = $exam->questionsById($section);

        return Inertia::render('training/exam-section', [
            'track' => ['slug' => $track->slug, 'name' => $track->name],
            'examTitle' => $exam->title,
            'section' => [
                'key' => $section,
                'title' => $exam->section($section)['title'],
                'pass_pct' => $exam->section($section)['pass_pct'],
            ],
            'attemptNumber' => $progress->attemptsUsed($section) + 1,
            'attemptsAllowed' => $progress->attemptsAllowed($section),
            'questions' => collect($attempt->question_ids)
                ->filter(fn (string $questionId) => $questions->has($questionId))
                ->map(fn (string $questionId) => [
                    'id' => $questionId,
                    'question' => $questions[$questionId]['question'],
                    'choices' => $questions[$questionId]['choices'],
                ])
                ->values(),
        ]);
    }

    /**
     * Grade and submit the open attempt.
     */
    public function submit(Request $request, CourseTrack $track, string $section): RedirectResponse
    {
        [$exam, $progress] = $this->examProgress($request, $track, $section);

        $attempt = $progress->openAttempt($section);

        abort_if($attempt === null, 404);

        $questions = $exam->questionsById($section)->only($attempt->question_ids);

        $request->validate(
            $questions->mapWithKeys(fn (array $question) => [
                "answers.{$question['id']}" => ['required', 'integer', 'min:0', 'max:'.(count($question['choices']) - 1)],
            ])->all(),
            ['answers.*.required' => 'Please answer every question.'],
        );

        $this->grade($attempt, $questions->all(), $request->input('answers', []), $exam->section($section)['pass_pct']);

        return to_route('training.exam.show', $track);
    }

    /**
     * Score the attempt, record a per-tag breakdown, and mark it submitted.
     *
     * @param  array<string, array{id: string, answer_index: int, tags?: array<int, string>}>  $questions
     * @param  array<string, mixed>  $submittedAnswers
     */
    private function grade(CourseExamAttempt $attempt, array $questions, array $submittedAnswers, int $passPercentage): void
    {
        $answers = [];
        $correctCount = 0;
        $tagBreakdown = [];

        foreach ($questions as $questionId => $question) {
            $answers[$questionId] = (int) $submittedAnswers[$questionId];
            $isCorrect = $answers[$questionId] === $question['answer_index'];
            $correctCount += (int) $isCorrect;

            foreach ($question['tags'] ?? [] as $tag) {
                $tagBreakdown[$tag] ??= ['correct' => 0, 'total' => 0];
                $tagBreakdown[$tag]['total']++;
                $tagBreakdown[$tag]['correct'] += (int) $isCorrect;
            }
        }

        $scorePercentage = count($questions) > 0 ? (int) round($correctCount / count($questions) * 100) : 0;

        $attempt->update([
            'answers' => $answers,
            'score_pct' => $scorePercentage,
            'tag_breakdown' => $tagBreakdown,
            'passed' => $scorePercentage >= $passPercentage,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Authorize access to the track and resolve its exam (and section, if given).
     *
     * @return array{0: CourseExam, 1: ExamProgress}
     */
    private function examProgress(Request $request, CourseTrack $track, ?string $section = null): array
    {
        Gate::authorize('view', $track);

        $exam = $track->exam;

        abort_if($exam === null || ($section !== null && ! $exam->hasSection($section)), 404);

        $progress = ExamProgress::forTrack($request->user(), $exam, new TrainingProgress($request->user(), $track));

        abort_unless($progress->isUnlocked(), 403, 'Pass every module to unlock the final exam.');

        return [$exam, $progress];
    }
}
