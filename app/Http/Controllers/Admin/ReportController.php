<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\BuildsAgentReportQuery;
use App\Enums\LicenseStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LicenseResource;
use App\Models\CourseTrack;
use App\Models\License;
use App\Models\User;
use App\Services\AgentTrainingReport;
use Illuminate\Database\Eloquent\Builder;
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
     * Display each agent's live training track progress and licenses.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $tracks = CourseTrack::query()->orderBy('position')->get(['id', 'name']);
        $selectedTrack = $tracks->find($request->integer('track'));

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $agents = $this->agentReportQuery($request)
            ->when($selectedTrack, fn (Builder $query, CourseTrack $track) => $query
                ->whereHas('courseTracks', fn (Builder $query) => $query->whereKey($track->id)))
            ->paginate($perPage)
            ->withQueryString();

        $report = new AgentTrainingReport(collect($agents->getCollection()->modelKeys()), $selectedTrack);

        $agents->through(fn (User $agent) => [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'status' => $agent->status,
            'licenses' => LicenseResource::collection($agent->licenses),
            ...$report->summary($agent->id),
        ]);

        $paginated = $agents->toArray();

        return Inertia::render('admin/reports/index', [
            'agents' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'filters' => [
                ...$request->only(['search', 'status', 'license', 'track']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses' => UserStatus::cases(),
            'licenses' => License::where('status', LicenseStatus::Active)->orderBy('name')->get(['id', 'name']),
            'trainings' => $tracks,
        ]);
    }
}
