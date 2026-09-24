<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\User;
use App\Services\ExamProgress;
use App\Services\TrainingProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrainingTrackController extends Controller
{
    /**
     * The selectable page sizes for the training tracks table.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display every user with a toggle for each training track.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('search')->trim()->toString();
        $role = $request->string('role')->toString();

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $tracks = CourseTrack::query()
            ->orderBy('position')
            ->withCount('users')
            ->get();

        $users = User::query()
            ->with('courseTracks:id')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when(UserRole::tryFrom($role), fn (Builder $query, UserRole $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'track_ids' => $user->courseTracks->modelKeys(),
        ]);

        $paginated = $users->toArray();

        return Inertia::render('admin/training-tracks/index', [
            'tracks' => $tracks->map(fn (CourseTrack $track) => [
                'id' => $track->id,
                'slug' => $track->slug,
                'name' => $track->name,
                'users_count' => $track->users_count,
            ]),
            'users' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'roles' => UserRole::cases(),
            'filters' => ['search' => $search, 'role' => $role, 'per_page' => (string) $perPage],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Display one user's progress in each of their training tracks.
     */
    public function show(User $user): Response
    {
        Gate::authorize('viewAny', User::class);

        $assignedTracks = $user->courseTracks()->orderBy('position')->get();

        $unassignedTracksWithProgress = CourseTrack::query()
            ->whereKeyNot($assignedTracks->modelKeys())
            ->whereHas('modules', fn (Builder $query) => $query
                ->whereHas('quizAttempts', fn (Builder $query) => $query->whereBelongsTo($user))
                ->orWhereHas('lessons.completedBy', fn (Builder $query) => $query->whereKey($user->id)))
            ->orderBy('position')
            ->get();

        return Inertia::render('admin/training-tracks/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'tracks' => [
                ...$assignedTracks->map(fn (CourseTrack $track) => $this->trackProgress($user, $track, isAssigned: true)),
                ...$unassignedTracksWithProgress->map(fn (CourseTrack $track) => $this->trackProgress($user, $track, isAssigned: false)),
            ],
            'availableTracks' => CourseTrack::query()
                ->orderBy('position')
                ->get(['id', 'name'])
                ->map(fn (CourseTrack $track) => [
                    'id' => $track->id,
                    'name' => $track->name,
                    'is_assigned' => $assignedTracks->contains($track),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function trackProgress(User $user, CourseTrack $track, bool $isAssigned): array
    {
        $progress = new TrainingProgress($user, $track);

        $exam = $track->exam;

        return [
            'slug' => $track->slug,
            'name' => $track->name,
            'is_assigned' => $isAssigned,
            ...$progress->trackSummary(),
            'exam' => $exam ? [
                'id' => $exam->id,
                ...ExamProgress::forTrack($user, $exam, $progress)->summary(),
            ] : null,
            'modules' => $progress->modules()->map(fn (CourseModule $module) => [
                'slug' => $module->slug,
                'title' => $module->title,
                ...$progress->summary($module),
                'attempts' => $progress->attempts($module)->map(fn (CourseQuizAttempt $attempt) => [
                    'id' => $attempt->id,
                    'score_pct' => $attempt->score_pct,
                    'passed' => $attempt->passed,
                    'created_at' => $attempt->created_at,
                ])->values(),
            ]),
        ];
    }
}
