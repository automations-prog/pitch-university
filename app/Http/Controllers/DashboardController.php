<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsAgentReportQuery;
use App\Enums\CallRating;
use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CallLog;
use App\Models\CourseTrack;
use App\Models\License;
use App\Models\ScreeningResponse;
use App\Models\User;
use App\Services\AgentTrainingReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BuildsAgentReportQuery;

    /**
     * The selectable page sizes for the agent progress table.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * The tabs on the admin dashboard.
     *
     * @var array<int, string>
     */
    private const TABS = ['overview', 'training', 'screening'];

    /**
     * The most to-do items listed on the overview tab.
     */
    private const TODO_LIMIT = 6;

    /**
     * Display the dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return $this->agentDashboard($user);
        }

        $tracks = CourseTrack::query()->orderBy('position')->get(['id', 'name']);
        $selectedTrack = $tracks->find($request->integer('track'));

        $activeLicenses = License::where('status', LicenseStatus::Active)->orderBy('name')->get(['id', 'name']);

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $buildAgentQuery = fn () => $this->agentReportQuery($request)
            ->when($selectedTrack, fn (Builder $query, CourseTrack $track) => $query
                ->whereHas('courseTracks', fn (Builder $query) => $query->whereKey($track->id)));

        // The charts summarize every agent matching the current filters, not
        // just the current page, so they're built from the full filtered set.
        $allFilteredAgentModels = $buildAgentQuery()->get();
        $report = new AgentTrainingReport(collect($allFilteredAgentModels->modelKeys()), $selectedTrack);
        $allFilteredAgents = $allFilteredAgentModels->map(fn (User $agent) => $this->agentProgressRow($agent, $report));

        $agentsPage = $buildAgentQuery()->paginate($perPage)->withQueryString();
        $agentsPage->through(fn (User $agent) => $this->agentProgressRow($agent, $report));
        $paginated = $agentsPage->toArray();

        // The overview tab always summarizes every agent, whatever filters
        // the training tab's agent table currently has applied.
        $hasAgentFilters = collect(['status', 'license', 'track'])->contains(fn (string $key) => $request->filled($key));
        $overviewReport = $hasAgentFilters
            ? new AgentTrainingReport(User::where('role', UserRole::Agent)->pluck('id'))
            : $report;
        $overviewTracks = $overviewReport->trackOverview();

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : self::TABS[0];

        $totalAgents = User::where('role', UserRole::Agent)->count();
        $activeAgents = User::where('role', UserRole::Agent)->where('status', UserStatus::Active)->count();

        return Inertia::render('dashboard', [
            'isAdmin' => true,
            'stats' => [
                'total_agents' => $totalAgents,
                'active_agents' => $activeAgents,
                'inactive_agents' => $totalAgents - $activeAgents,
                'total_trainings' => $tracks->count(),
            ],
            'agents' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'charts' => $this->chartsFor($allFilteredAgents, $allFilteredAgentModels, $activeLicenses),
            'trackOverview' => $report->trackOverview(),
            'overviewTracks' => $overviewTracks,
            'todos' => $this->todos($overviewTracks),
            'screenings' => $this->screeningOverview(),
            'licenses' => $activeLicenses,
            'trainings' => $tracks,
            'filters' => [
                ...$request->only(['status', 'license', 'track']),
                'per_page' => (string) $perPage,
                'tab' => $tab,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Display the personal dashboard for a non-admin agent: their progress
     * and quiz scores across their assigned training tracks.
     */
    private function agentDashboard(User $user): Response
    {
        $report = new AgentTrainingReport(collect([$user->id]));

        return Inertia::render('dashboard', [
            'isAdmin' => false,
            'progress' => $report->summary($user->id),
            'trainingScores' => $report->trackScores($user->id),
        ]);
    }

    /**
     * The admin's open tasks, derived from live data so each item clears
     * itself once the work is done: rate finished screening calls, chase
     * candidates who haven't taken the AI call, and get assigned agents
     * started on their tracks.
     *
     * @param  Collection<int, array{slug: string, name: string, not_started: int}>  $tracks
     * @return array{items: Collection<int, array{key: string, type: string, title: string, description: string, screening_response_id: int|null}>, total: int}
     */
    private function todos(Collection $tracks): array
    {
        $awaitingReview = ScreeningResponse::query()
            ->whereHas('callLog', fn (Builder $callLog) => $callLog->awaitingReview());
        $awaitingCall = ScreeningResponse::query()
            ->whereHas('callLog', fn (Builder $callLog) => $callLog->whereNull('called_at'));
        $notStartedTracks = $tracks->filter(fn (array $track) => $track['not_started'] > 0);

        $items = collect()
            ->concat((clone $awaitingReview)->latest('id')->limit(self::TODO_LIMIT)->get(['id', 'full_name'])
                ->map(fn (ScreeningResponse $response) => [
                    'key' => "review-{$response->id}",
                    'type' => 'review',
                    'title' => "Review {$response->full_name}",
                    'description' => 'AI screening call is done',
                    'screening_response_id' => $response->id,
                ]))
            ->concat((clone $awaitingCall)->latest('id')->limit(self::TODO_LIMIT)->get(['id', 'full_name'])
                ->map(fn (ScreeningResponse $response) => [
                    'key' => "remind-{$response->id}",
                    'type' => 'remind',
                    'title' => "Remind {$response->full_name}",
                    'description' => 'Still needs to take the AI call',
                    'screening_response_id' => $response->id,
                ]))
            ->concat($notStartedTracks->map(fn (array $track) => [
                'key' => "start-{$track['slug']}",
                'type' => 'start',
                'title' => sprintf('Get %d %s started', $track['not_started'], Str::plural('agent', $track['not_started'])),
                'description' => "{$track['name']} not opened yet",
                'screening_response_id' => null,
            ]));

        return [
            'items' => $items->take(self::TODO_LIMIT)->values(),
            'total' => $awaitingReview->count() + $awaitingCall->count() + $notStartedTracks->count(),
        ];
    }

    /**
     * Where screening candidates are in the funnel: responded, called by the
     * AI voice agent, and rated by an admin, plus the overall gut-check split
     * and the most recent responses.
     *
     * @return array{stats: array{responses: int, called: int, awaiting_call: int, awaiting_review: int, reviewed: int}, gut_check: array<int, array{name: string, value: int}>, recent: Collection<int, array<string, mixed>>}
     */
    private function screeningOverview(): array
    {
        $responses = ScreeningResponse::count();
        $called = CallLog::whereNotNull('called_at')->count();
        $awaitingReview = CallLog::awaitingReview()->count();

        $gutCheckCounts = CallLog::whereNotNull('overall_gut_check')
            ->selectRaw('overall_gut_check, count(*) as total')
            ->groupBy('overall_gut_check')
            ->pluck('total', 'overall_gut_check');

        return [
            'stats' => [
                'responses' => $responses,
                'called' => $called,
                'awaiting_call' => $responses - $called,
                'awaiting_review' => $awaitingReview,
                'reviewed' => $called - $awaitingReview,
            ],
            'gut_check' => collect(CallRating::cases())->map(fn (CallRating $rating) => [
                'name' => $rating->name,
                'value' => (int) $gutCheckCounts->get($rating->value, 0),
            ])->all(),
            'recent' => ScreeningResponse::query()
                ->with('callLog')
                ->latest('id')
                ->limit(3)
                ->get()
                ->map(fn (ScreeningResponse $response) => [
                    'id' => $response->id,
                    'full_name' => $response->full_name,
                    'email' => $response->email,
                    'created_at' => $response->created_at,
                    'called_at' => $response->callLog?->called_at,
                    'overall_gut_check' => $response->callLog?->overall_gut_check,
                ]),
        ];
    }

    /**
     * Build the chart datasets shown alongside the agent table. Derived from
     * the same filtered agent set as the table, so the charts and the table
     * always agree.
     *
     * @param  Collection<int, array<string, mixed>>  $agents
     * @param  EloquentCollection<int, User>  $agentModels
     * @param  EloquentCollection<int, License>  $activeLicenses
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function chartsFor(Collection $agents, EloquentCollection $agentModels, EloquentCollection $activeLicenses): array
    {
        $statusSplit = [
            ['name' => 'Active', 'value' => $agents->where('status', UserStatus::Active)->count()],
            ['name' => 'Inactive', 'value' => $agents->where('status', UserStatus::Inactive)->count()],
        ];

        $completion = [
            ['name' => '0', 'value' => $agents->where('trainings_completed', 0)->count()],
            ['name' => '1-2', 'value' => $agents->whereBetween('trainings_completed', [1, 2])->count()],
            ['name' => '3+', 'value' => $agents->where('trainings_completed', '>=', 3)->count()],
        ];

        $scoreBands = [
            ['name' => 'Not started', 'value' => $agents->whereNull('average_score')->count()],
            ['name' => '<60', 'value' => $agents->whereNotNull('average_score')->where('average_score', '<', 60)->count()],
            ['name' => '60-79', 'value' => $agents->whereBetween('average_score', [60, 79])->count()],
            ['name' => '80-100', 'value' => $agents->whereBetween('average_score', [80, 100])->count()],
        ];

        $perLicense = $activeLicenses->map(fn (License $license) => [
            'name' => $license->name,
            'value' => $agentModels->filter(fn (User $agent) => $agent->licenses->contains('id', $license->id))->count(),
        ])->values()->all();

        return [
            'status_split' => $statusSplit,
            'completion' => $completion,
            'score_bands' => $scoreBands,
            'per_license' => $perLicense,
        ];
    }

    /**
     * The agent row shape shared by the paginated table and the full,
     * unpaginated set used to build the charts.
     *
     * @return array<string, mixed>
     */
    private function agentProgressRow(User $agent, AgentTrainingReport $report): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'status' => $agent->status,
            ...$report->summary($agent->id),
        ];
    }
}
