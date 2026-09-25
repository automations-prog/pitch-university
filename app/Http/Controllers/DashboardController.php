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
            'screenings' => $this->screeningOverview(),
            'licenses' => $activeLicenses,
            'trainings' => $tracks,
            'filters' => [
                ...$request->only(['status', 'license', 'track']),
                'per_page' => (string) $perPage,
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
        $reviewed = CallLog::whereNotNull('called_at')->whereNotNull('overall_gut_check')->count();

        $gutCheckCounts = CallLog::whereNotNull('overall_gut_check')
            ->selectRaw('overall_gut_check, count(*) as total')
            ->groupBy('overall_gut_check')
            ->pluck('total', 'overall_gut_check');

        return [
            'stats' => [
                'responses' => $responses,
                'called' => $called,
                'awaiting_call' => $responses - $called,
                'awaiting_review' => $called - $reviewed,
                'reviewed' => $reviewed,
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
