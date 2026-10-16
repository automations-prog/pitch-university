<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeamRequest;
use App\Http\Requests\Admin\UpdateTeamRequest;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * The selectable page sizes for the teams index.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display the teams with their campaign, leaders and member count.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $teams = Team::query()
            ->select('teams.*')
            ->with(['campaign:id,name', 'manager:id,name,team_id'])
            ->withCount(['members as agents_count' => fn (Builder $query) => $query->where('role', UserRole::Agent)])
            ->join('campaigns', 'campaigns.id', '=', 'teams.campaign_id')
            ->when($request->string('search')->trim()->toString(), fn (Builder $query, string $search) => $query
                ->where('teams.name', 'like', "%{$search}%"))
            ->when($request->integer('campaign'), fn (Builder $query, int $campaignId) => $query
                ->where('teams.campaign_id', $campaignId))
            ->when($request->string('manager')->toString(), fn (Builder $query, string $manager) => $manager === 'none'
                ? $query->whereDoesntHave('manager')
                : $query->whereRelation('manager', 'id', (int) $manager))
            ->orderBy('campaigns.name')
            ->orderBy('teams.name')
            ->paginate($perPage)
            ->withQueryString();

        $teams->through(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'campaign' => $team->campaign->only(['id', 'name']),
            'manager' => $team->manager?->name,
            'agents_count' => $team->agents_count,
        ]);

        $paginated = $teams->toArray();

        return Inertia::render('admin/teams/index', [
            'teams' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'filters' => [
                ...$request->only(['search', 'campaign', 'manager']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            'managers' => User::query()
                ->where('role', UserRole::Admin)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new team.
     */
    public function create(): Response
    {
        return Inertia::render('admin/teams/create', $this->formOptions());
    }

    /**
     * Store a newly created team.
     */
    public function store(StoreTeamRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $team = Team::create($request->safe()->only(['campaign_id', 'name']));

            $this->assignManager($team, $request->integer('manager_id') ?: null);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('admin.teams.index');
    }

    /**
     * Show the form for editing the given team.
     */
    public function edit(Team $team): Response
    {
        $team->load('manager:id,team_id');

        return Inertia::render('admin/teams/edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'campaign_id' => $team->campaign_id,
                'manager_id' => $team->manager?->id,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the given team.
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        DB::transaction(function () use ($request, $team) {
            $team->update($request->safe()->only(['campaign_id', 'name']));

            $this->assignManager($team, $request->integer('manager_id') ?: null);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('admin.teams.index');
    }

    /**
     * Remove the given team. Its members stay, without a team.
     */
    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('admin.teams.index');
    }

    /**
     * The campaigns and the admins who can run a team, with the team each
     * one runs now.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            'managers' => User::query()
                ->where('role', UserRole::Admin)
                ->with('team:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'team_id'])
                ->map(fn (User $admin) => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'team' => $admin->team?->name,
                ]),
        ];
    }

    /**
     * Make the given admin the team's one manager. The team's previous
     * admin is taken off it; validation has already made sure the new
     * admin doesn't run another team.
     */
    private function assignManager(Team $team, ?int $managerId): void
    {
        User::query()
            ->where('role', UserRole::Admin)
            ->where('team_id', $team->id)
            ->whereKeyNot($managerId ?? 0)
            ->update(['team_id' => null]);

        if ($managerId !== null) {
            User::whereKey($managerId)->update(['team_id' => $team->id]);
        }
    }
}
