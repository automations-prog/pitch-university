<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WorkLocation;
use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\CourseQuizAttempt;
use App\Models\CourseTrack;
use App\Models\RoleplaySession;
use App\Models\Team;
use App\Models\User;
use App\Services\TrainingProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
     * Display every agent with a toggle for each training track.
     */
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::ViewTrainingTracks);

        $search = $request->string('search')->trim()->toString();

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $tracks = CourseTrack::query()
            ->orderBy('position')
            ->withCount(['users' => fn (Builder $query) => $query->where('role', UserRole::Agent)])
            ->get();

        $users = User::query()
            ->with('courseTracks:id')
            ->where('role', UserRole::Agent)
            ->visibleTo($request->user())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->integer('campaign'), fn (Builder $query, int $campaignId) => $query->whereRelation('team', 'campaign_id', $campaignId))
            ->when($request->integer('team'), fn (Builder $query, int $teamId) => $query->where('team_id', $teamId))
            ->when(WorkLocation::tryFrom($request->string('work_location')->toString()), fn (Builder $query, WorkLocation $location) => $query->where('work_location', $location))
            ->when(UserStatus::tryFrom($request->string('status')->toString()), fn (Builder $query, UserStatus $status) => $query->where('status', $status))
            ->when($request->string('track')->toString(), fn (Builder $query, string $track) => $track === 'none'
                ? $query->whereDoesntHave('courseTracks')
                : $query->whereHas('courseTracks', fn (Builder $query) => $query->whereKey((int) $track)))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
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
            'filters' => [
                ...$request->only(['campaign', 'team', 'work_location', 'status', 'track']),
                'search' => $search,
                'per_page' => (string) $perPage,
            ],
            'teams' => $request->user()->assignableTeams()
                ->with('campaign:id,name')
                ->get(['id', 'name', 'campaign_id'])
                ->sortBy([['campaign.name', 'asc'], ['name', 'asc']])
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'campaign' => $team->campaign->only(['id', 'name']),
                ])
                ->values(),
            'workLocations' => WorkLocation::cases(),
            'statuses' => UserStatus::cases(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Display one user's progress in each of their training tracks.
     */
    public function show(User $user): Response
    {
        Gate::authorize('viewProgress', $user);

        $assignedTracks = $user->courseTracks()->orderBy('position')->get();

        $unassignedTracksWithProgress = CourseTrack::query()
            ->whereKeyNot($assignedTracks->modelKeys())
            ->whereHas('modules', fn (Builder $query) => $query
                ->whereHas('quizAttempts', fn (Builder $query) => $query->whereBelongsTo($user))
                ->orWhereHas('lessons.completedBy', fn (Builder $query) => $query->whereKey($user->id)))
            ->orderBy('position')
            ->get();

        $roleplayCalls = RoleplaySession::query()
            ->whereBelongsTo($user)
            ->whereNotNull('course_module_id')
            ->whereNotNull('ended_at')
            ->latest('id')
            ->get()
            ->groupBy('course_module_id');

        return Inertia::render('admin/training-tracks/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'tracks' => TrainingProgress::forTracks($user, $assignedTracks->concat($unassignedTracksWithProgress))
                ->map(fn (TrainingProgress $progress) => $this->trackProgress($progress, isAssigned: $assignedTracks->contains($progress->track), roleplayCalls: $roleplayCalls)),
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
     * @param  Collection<int, Collection<int, RoleplaySession>>  $roleplayCalls  the user's finished module roleplay calls by module id
     * @return array<string, mixed>
     */
    private function trackProgress(TrainingProgress $progress, bool $isAssigned, Collection $roleplayCalls): array
    {
        $track = $progress->track;
        $examProgress = $progress->examProgress();

        return [
            'slug' => $track->slug,
            'name' => $track->name,
            'is_assigned' => $isAssigned,
            ...$progress->trackSummary(),
            'exam' => $examProgress ? [
                'id' => $examProgress->exam->id,
                ...$examProgress->summary(),
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
                'roleplay_calls' => $roleplayCalls->get($module->id, collect())->map(fn (RoleplaySession $call) => [
                    'id' => $call->id,
                    // Null while the AI is still judging the scene.
                    'passed' => $call->passed,
                    'ended_at' => $call->ended_at,
                    'missed_checks' => $this->missedChecks($call),
                    'url' => route($call->vertical->routePrefix().'.sessions.show', $call),
                ])->values(),
            ]),
        ];
    }

    /**
     * The labels of the checks a module scene failed. A scene only keeps
     * the checks it counts.
     *
     * @return list<string>
     */
    private function missedChecks(RoleplaySession $call): array
    {
        return collect($call->score['checks'] ?? [])
            ->filter(fn (array $check) => $check['passed'] === false)
            ->pluck('label')
            ->values()
            ->all();
    }
}
