<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeamInvitationRequest;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamInvitationController extends Controller
{
    /**
     * The selectable page sizes for the invite links index.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display the invite links of the teams the user may put people on.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TeamInvitation::class);

        $user = $request->user();
        $teams = $user->assignableTeams()->with('campaign:id,name')->get(['id', 'name', 'campaign_id']);

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $invitations = TeamInvitation::query()
            ->whereIn('team_id', $teams->modelKeys())
            ->with(['team.campaign:id,name', 'creator:id,name'])
            ->when($request->string('search')->trim()->toString(), fn (Builder $query, string $search) => $query
                ->whereRelation('team', 'name', 'like', "%{$search}%"))
            ->when($request->integer('campaign'), fn (Builder $query, int $campaignId) => $query
                ->whereRelation('team', 'campaign_id', $campaignId))
            ->when($request->integer('team'), fn (Builder $query, int $teamId) => $query->where('team_id', $teamId))
            ->when(in_array($request->string('status')->toString(), TeamInvitation::STATUSES, true), fn (Builder $query) => $query
                ->withStatus($request->string('status')->toString()))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $invitations->through(fn (TeamInvitation $invitation) => [
            'id' => $invitation->id,
            'url' => route('invitations.show', $invitation->token),
            'status' => $invitation->status(),
            'team' => ['id' => $invitation->team->id, 'name' => $invitation->team->name],
            'campaign' => $invitation->team->campaign->only(['id', 'name']),
            'created_by' => $invitation->creator?->name,
            'uses_count' => $invitation->uses_count,
            'max_uses' => $invitation->max_uses,
            'expires_at' => $invitation->expires_at,
            'created_at' => $invitation->created_at,
        ]);

        $paginated = $invitations->toArray();

        return Inertia::render('admin/invitations/index', [
            'invitations' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'filters' => [
                ...$request->only(['search', 'campaign', 'team', 'status']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses' => TeamInvitation::STATUSES,
            'teams' => $teams
                ->sortBy([['campaign.name', 'asc'], ['name', 'asc']])
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'campaign' => $team->campaign->only(['id', 'name']),
                ])
                ->values(),
        ]);
    }

    /**
     * Create a new invite link for a team.
     */
    public function store(StoreTeamInvitationRequest $request): RedirectResponse
    {
        $expiresInDays = $request->validated('expires_in_days');

        TeamInvitation::create([
            'team_id' => $request->validated('team_id'),
            'created_by' => $request->user()->id,
            'role' => UserRole::Agent,
            'expires_at' => $expiresInDays ? now()->addDays((int) $expiresInDays) : null,
            'max_uses' => $request->validated('max_uses'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invite link created.')]);

        return to_route('admin.invitations.index');
    }

    /**
     * Revoke the given invite link. Accounts made with it are kept.
     */
    public function destroy(TeamInvitation $invitation): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $invitation->revoked_at = now();
        $invitation->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invite link revoked.')]);

        return to_route('admin.invitations.index');
    }
}
