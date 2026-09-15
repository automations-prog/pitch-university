<?php

namespace App\Http\Controllers;

use App\Concerns\BuildsAgentReportQuery;
use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VerticalTrainingStatus;
use App\Models\License;
use App\Models\User;
use App\Models\VerticalTraining;
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

        $totalTrainings = VerticalTraining::where('status', VerticalTrainingStatus::Active)->count();

        $trainingId = $request->string('vertical_training')->toString();

        $selectedTraining = $trainingId
            ? VerticalTraining::where('status', VerticalTrainingStatus::Active)->find($trainingId)
            : null;

        $activeLicenses = License::where('status', LicenseStatus::Active)->orderBy('name')->get(['id', 'name']);

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $buildAgentQuery = fn () => $this->agentReportQuery($request);

        // The charts summarize every agent matching the current filters, not
        // just the current page, so they're built from the full filtered set.
        $allFilteredAgentModels = $buildAgentQuery()->get();
        $allFilteredAgents = $allFilteredAgentModels->map(
            fn (User $agent) => $this->agentProgressRow($agent, $totalTrainings, $selectedTraining),
        );

        $agentsPage = $buildAgentQuery()->paginate($perPage)->withQueryString();
        $agentsPage->through(fn (User $agent) => $this->agentProgressRow($agent, $totalTrainings, $selectedTraining));
        $paginated = $agentsPage->toArray();

        $totalAgents = User::where('role', UserRole::Agent)->count();
        $activeAgents = User::where('role', UserRole::Agent)->where('status', UserStatus::Active)->count();

        return Inertia::render('dashboard', [
            'isAdmin' => true,
            'stats' => [
                'total_agents' => $totalAgents,
                'active_agents' => $activeAgents,
                'inactive_agents' => $totalAgents - $activeAgents,
                'total_trainings' => $totalTrainings,
            ],
            'agents' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'charts' => $this->chartsFor($allFilteredAgents, $allFilteredAgentModels, $activeLicenses),
            'licenses' => $activeLicenses,
            'trainings' => VerticalTraining::where('status', VerticalTrainingStatus::Active)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                ...$request->only(['status', 'license', 'vertical_training']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Display the personal dashboard for a non-admin agent: their own sample
     * progress plus a per-training breakdown.
     */
    private function agentDashboard(User $user): Response
    {
        $trainings = VerticalTraining::where('status', VerticalTrainingStatus::Active)->orderBy('name')->get();

        $trainingScores = $trainings->map(fn (VerticalTraining $training) => [
            'id' => $training->id,
            'name' => $training->name,
            ...$this->mockProgressFor($user, 1, $training),
        ])->values();

        return Inertia::render('dashboard', [
            'isAdmin' => false,
            'progress' => $this->mockProgressFor($user, $trainings->count()),
            'trainingScores' => $trainingScores,
        ]);
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
    private function agentProgressRow(User $agent, int $totalTrainings, ?VerticalTraining $training): array
    {
        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'status' => $agent->status,
            ...$this->mockProgressFor($agent, $totalTrainings, $training),
        ];
    }
}
