<?php

namespace App\Services;

use App\Models\CourseExamAttempt;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Live training progress for a set of agents, loaded in a handful of grouped
 * queries so the dashboard can summarize every filtered agent at once.
 *
 * Only tracks assigned to an agent count toward their numbers. A track is
 * complete once the agent is certified or, without a final exam, once every
 * module's quiz is passed. The average score is taken over the agent's best
 * quiz score per attempted module.
 */
class AgentTrainingReport
{
    /** @var EloquentCollection<int, CourseTrack> */
    private EloquentCollection $tracks;

    /** @var Collection<int, Collection<int, int>> */
    private Collection $assignedTrackIds;

    /** @var Collection<int, Collection<int, object>> */
    private Collection $moduleScores;

    /** @var Collection<int, Collection<int, int>> */
    private Collection $passedExamSections;

    /** @var Collection<int, Collection<int, int>> */
    private Collection $lessonModuleIds;

    /** @var Collection<int, string> */
    private Collection $lastActivity;

    /**
     * @param  Collection<int, int>  $agentIds
     */
    public function __construct(Collection $agentIds, ?CourseTrack $onlyTrack = null)
    {
        $this->tracks = CourseTrack::query()
            ->when($onlyTrack, fn ($query, CourseTrack $track) => $query->whereKey($track->id))
            ->with(['exam', 'modules:id,course_track_id'])
            ->orderBy('position')
            ->get();

        $moduleIds = $this->tracks->flatMap(fn (CourseTrack $track) => $track->modules->modelKeys())->all();
        $examIds = $this->tracks->pluck('exam.id')->filter()->all();

        $this->assignedTrackIds = DB::table('course_track_user')
            ->whereIn('user_id', $agentIds)
            ->whereIn('course_track_id', $this->tracks->modelKeys())
            ->get(['user_id', 'course_track_id'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->pluck('course_track_id'));

        $this->moduleScores = CourseQuizAttempt::query()
            ->whereIn('user_id', $agentIds)
            ->whereIn('course_module_id', $moduleIds)
            ->selectRaw('user_id, course_module_id, max(score_pct) as best_score, max(passed) as passed')
            ->groupBy('user_id', 'course_module_id')
            ->toBase()
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->keyBy('course_module_id'));

        $this->passedExamSections = CourseExamAttempt::query()
            ->whereIn('user_id', $agentIds)
            ->whereIn('course_exam_id', $examIds)
            ->whereNotNull('submitted_at')
            ->where('passed', true)
            ->selectRaw('user_id, course_exam_id, count(distinct section) as passed_sections')
            ->groupBy('user_id', 'course_exam_id')
            ->toBase()
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->pluck('passed_sections', 'course_exam_id')->map(fn ($count) => (int) $count));

        $lessonActivity = DB::table('course_lesson_user')
            ->join('course_lessons', 'course_lessons.id', '=', 'course_lesson_user.course_lesson_id')
            ->whereIn('course_lesson_user.user_id', $agentIds)
            ->whereIn('course_lessons.course_module_id', $moduleIds)
            ->selectRaw('course_lesson_user.user_id, course_lessons.course_module_id, max(course_lesson_user.created_at) as last_at')
            ->groupBy('course_lesson_user.user_id', 'course_lessons.course_module_id')
            ->get();

        $this->lessonModuleIds = $lessonActivity
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->pluck('course_module_id'));

        $examActivity = CourseExamAttempt::query()
            ->whereIn('user_id', $agentIds)
            ->whereIn('course_exam_id', $examIds)
            ->whereNotNull('submitted_at')
            ->selectRaw('user_id, max(submitted_at) as last_at')
            ->groupBy('user_id')
            ->toBase()
            ->get();

        $quizActivity = CourseQuizAttempt::query()
            ->whereIn('user_id', $agentIds)
            ->whereIn('course_module_id', $moduleIds)
            ->selectRaw('user_id, max(created_at) as last_at')
            ->groupBy('user_id')
            ->toBase()
            ->get();

        $this->lastActivity = $quizActivity
            ->concat($lessonActivity)
            ->concat($examActivity)
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => (string) $rows->max('last_at'));
    }

    /**
     * How the agents assigned to each track are progressing, with the average
     * of their best quiz score per attempted module.
     *
     * @return Collection<int, array{slug: string, name: string, has_exam: bool, average_score: int|null, assigned: int, not_started: int, in_progress: int, completed: int}>
     */
    public function trackOverview(): Collection
    {
        return $this->tracks->toBase()->map(function (CourseTrack $track) {
            $agentIds = $this->assignedTrackIds
                ->filter(fn (Collection $trackIds) => $trackIds->contains($track->id))
                ->keys();

            $statuses = $agentIds->map(fn (int $agentId) => $this->status($agentId, $track));
            $completed = $statuses->filter(fn (string $status) => in_array($status, ['certified', 'complete'], true))->count();
            $notStarted = $statuses->filter(fn (string $status) => $status === 'not_started')->count();

            return [
                'slug' => $track->slug,
                'name' => $track->name,
                'has_exam' => $track->exam !== null,
                'average_score' => $this->average($agentIds->flatMap(fn (int $agentId) => $this->bestScores($agentId, $track))),
                'assigned' => $agentIds->count(),
                'not_started' => $notStarted,
                'in_progress' => $agentIds->count() - $notStarted - $completed,
                'completed' => $completed,
            ];
        })->values();
    }

    /**
     * The agent's progress over their assigned tracks.
     *
     * @return array{trainings_completed: int, total_trainings: int, certifications: int, average_score: int|null, last_activity: string|null}
     */
    public function summary(int $agentId): array
    {
        $tracks = $this->assignedTracks($agentId);
        $bestScores = $tracks->flatMap(fn (CourseTrack $track) => $this->bestScores($agentId, $track));
        $lastActivity = $this->lastActivity->get($agentId);

        return [
            'trainings_completed' => $tracks->filter(fn (CourseTrack $track) => $this->isComplete($agentId, $track))->count(),
            'total_trainings' => $tracks->count(),
            'certifications' => $tracks->filter(fn (CourseTrack $track) => $this->isCertified($agentId, $track))->count(),
            'average_score' => $this->average($bestScores),
            'last_activity' => $lastActivity ? substr($lastActivity, 0, 10) : null,
        ];
    }

    /**
     * The agent's status and average score in each assigned track.
     *
     * @return Collection<int, array{id: int, name: string, status: string, average_score: int|null}>
     */
    public function trackScores(int $agentId): Collection
    {
        return $this->assignedTracks($agentId)->map(fn (CourseTrack $track) => [
            'id' => $track->id,
            'name' => $track->name,
            'status' => $this->status($agentId, $track),
            'average_score' => $this->average($this->bestScores($agentId, $track)),
        ])->values();
    }

    /**
     * @return Collection<int, CourseTrack>
     */
    private function assignedTracks(int $agentId): Collection
    {
        $trackIds = $this->assignedTrackIds->get($agentId, collect());

        return $this->tracks->toBase()->filter(fn (CourseTrack $track) => $trackIds->contains($track->id));
    }

    /**
     * One of: certified, complete, exam_next, in_progress, not_started.
     */
    private function status(int $agentId, CourseTrack $track): string
    {
        return match (true) {
            $this->isCertified($agentId, $track) => 'certified',
            $this->isComplete($agentId, $track) => 'complete',
            $this->modulesPassed($agentId, $track) => 'exam_next',
            $this->hasStarted($agentId, $track) => 'in_progress',
            default => 'not_started',
        };
    }

    /**
     * Whether the agent has completed a lesson or attempted a quiz in the track.
     */
    private function hasStarted(int $agentId, CourseTrack $track): bool
    {
        $moduleIds = $track->modules->modelKeys();

        return $this->moduleScores->get($agentId, collect())->keys()->intersect($moduleIds)->isNotEmpty()
            || $this->lessonModuleIds->get($agentId, collect())->intersect($moduleIds)->isNotEmpty();
    }

    private function isComplete(int $agentId, CourseTrack $track): bool
    {
        return $track->exam !== null
            ? $this->isCertified($agentId, $track)
            : $this->modulesPassed($agentId, $track);
    }

    private function isCertified(int $agentId, CourseTrack $track): bool
    {
        if ($track->exam === null) {
            return false;
        }

        $passedSections = $this->passedExamSections->get($agentId, collect())->get($track->exam->id, 0);

        return $passedSections >= count($track->exam->sectionKeys());
    }

    private function modulesPassed(int $agentId, CourseTrack $track): bool
    {
        $scores = $this->moduleScores->get($agentId, collect());

        return $track->modules->isNotEmpty()
            && $track->modules->every(fn (CourseModule $module) => (bool) $scores->get($module->id)?->passed);
    }

    /**
     * @return Collection<int, int>
     */
    private function bestScores(int $agentId, CourseTrack $track): Collection
    {
        $scores = $this->moduleScores->get($agentId, collect());

        return $track->modules->toBase()
            ->map(fn (CourseModule $module) => $scores->get($module->id)?->best_score)
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (int) $score)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $scores
     */
    private function average(Collection $scores): ?int
    {
        return $scores->isEmpty() ? null : (int) round($scores->avg());
    }
}
