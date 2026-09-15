<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsAgentReportQuery;
use App\Enums\LicenseStatus;
use App\Enums\UserStatus;
use App\Enums\VerticalTrainingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LicenseResource;
use App\Models\License;
use App\Models\User;
use App\Models\VerticalTraining;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use BuildsAgentReportQuery;

    /**
     * The selectable page sizes for the reports table.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display the agent training/license report.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $totalTrainings = VerticalTraining::where('status', VerticalTrainingStatus::Active)->count();

        $trainingId = $request->string('vertical_training')->toString();

        $selectedTraining = $trainingId
            ? VerticalTraining::where('status', VerticalTrainingStatus::Active)->find($trainingId)
            : null;

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $agents = $this->agentReportQuery($request)->paginate($perPage)->withQueryString();

        $agents->through(fn (User $agent) => [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'status' => $agent->status,
            'licenses' => LicenseResource::collection($agent->licenses),
            ...$this->mockProgressFor($agent, $totalTrainings, $selectedTraining),
        ]);

        $paginated = $agents->toArray();

        return Inertia::render('admin/reports/index', [
            'agents' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'filters' => [
                ...$request->only(['search', 'status', 'license', 'vertical_training']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses' => UserStatus::cases(),
            'licenses' => License::where('status', LicenseStatus::Active)->orderBy('name')->get(['id', 'name']),
            'trainings' => VerticalTraining::where('status', VerticalTrainingStatus::Active)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
