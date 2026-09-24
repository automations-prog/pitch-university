<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\CourseTrack;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrainingProgressController extends Controller
{
    /**
     * Display each user's progress and quiz scores in a training track.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $tracks = CourseTrack::query()->orderBy('position')->get();
        $track = $tracks->firstWhere('slug', $request->string('track')->toString()) ?? $tracks->first();
        $search = $request->string('search')->trim()->toString();

        $modules = CourseModule::query()
            ->where('course_track_id', $track?->id)
            ->orderBy('position')
            ->withCount('lessons')
            ->get();
        $moduleIds = $modules->modelKeys();
        $exam = $track?->exam;
        $examSectionKeys = $exam?->sectionKeys() ?? [];

        $users = User::query()
            ->when($track === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($track !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereHas('courseTracks', fn (Builder $query) => $query->whereKey($track->id))
                ->orWhereHas('courseQuizAttempts', fn (Builder $query) => $query->whereIn('course_module_id', $moduleIds))
                ->orWhereHas('completedCourseLessons', fn (Builder $query) => $query->whereIn('course_module_id', $moduleIds))))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->withExists(['courseTracks as is_assigned' => fn (Builder $query) => $query->whereKey($track?->id)])
            ->withCount(['completedCourseLessons' => fn (Builder $query) => $query->whereIn('course_module_id', $moduleIds)])
            ->with(['courseQuizAttempts' => fn ($query) => $query
                ->whereIn('course_module_id', $moduleIds)
                ->selectRaw('user_id, course_module_id, max(score_pct) as best_score, max(passed) as passed, count(*) as attempts_count')
                ->groupBy('user_id', 'course_module_id')])
            ->with(['courseExamAttempts' => fn ($query) => $query
                ->where('course_exam_id', $exam?->id)
                ->whereNotNull('submitted_at')
                ->selectRaw('user_id, section, max(score_pct) as best_score, max(passed) as passed, count(*) as attempts_count')
                ->groupBy('user_id', 'section')])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_assigned' => (bool) $user->getAttribute('is_assigned'),
            'completed_lessons' => $user->completed_course_lessons_count,
            'modules' => $modules->mapWithKeys(function (CourseModule $module) use ($user) {
                $attempt = $user->courseQuizAttempts->firstWhere('course_module_id', $module->id);

                return [$module->slug => $attempt ? [
                    'best_score' => (int) $attempt->getAttribute('best_score'),
                    'passed' => (bool) $attempt->getAttribute('passed'),
                    'attempts_count' => (int) $attempt->getAttribute('attempts_count'),
                ] : null];
            }),
            'exam' => $exam === null ? null : $this->examScores($user, $examSectionKeys),
        ]);

        $paginated = $users->toArray();

        return Inertia::render('admin/training/progress', [
            'users' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'tracks' => $tracks->map(fn (CourseTrack $option) => ['slug' => $option->slug, 'name' => $option->name]),
            'modules' => $modules->map(fn (CourseModule $module) => [
                'slug' => $module->slug,
                'title' => $module->title,
            ]),
            'totalLessons' => (int) $modules->sum('lessons_count'),
            'examSections' => collect($exam->sections ?? [])->map(fn (array $section) => [
                'key' => $section['key'],
                'title' => $section['title'],
            ])->values(),
            'filters' => ['track' => $track?->slug, 'search' => $search],
        ]);
    }

    /**
     * Best score per exam section, and whether every section is passed.
     *
     * @param  array<int, string>  $sectionKeys
     * @return array{is_certified: bool, sections: array<string, array{best_score: int, passed: bool, attempts_count: int}|null>}
     */
    private function examScores(User $user, array $sectionKeys): array
    {
        $sections = collect($sectionKeys)->mapWithKeys(function (string $section) use ($user) {
            $attempt = $user->courseExamAttempts->firstWhere('section', $section);

            return [$section => $attempt ? [
                'best_score' => (int) $attempt->getAttribute('best_score'),
                'passed' => (bool) $attempt->getAttribute('passed'),
                'attempts_count' => (int) $attempt->getAttribute('attempts_count'),
            ] : null];
        });

        return [
            'is_certified' => $sections->isNotEmpty() && $sections->every(fn (?array $score) => $score !== null && $score['passed']),
            'sections' => $sections->all(),
        ];
    }
}
