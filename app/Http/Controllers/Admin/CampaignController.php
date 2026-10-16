<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * The selectable page sizes for the campaigns index.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display the campaigns with their team and agent counts.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $campaigns = Campaign::query()
            ->withCount(['teams', 'users as agents_count' => fn (Builder $query) => $query->where('users.role', UserRole::Agent)])
            ->when($request->string('search')->trim()->toString(), fn (Builder $query, string $search) => $query
                ->where('name', 'like', "%{$search}%"))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query
                ->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'is_active'])
            ->withQueryString();

        $paginated = $campaigns->toArray();

        return Inertia::render('admin/campaigns/index', [
            'campaigns' => [
                'data' => $paginated['data'],
                'links' => $paginated['links'],
                'meta' => Arr::except($paginated, ['data', 'links']),
            ],
            'filters' => [
                ...$request->only(['search', 'status']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Show the form for creating a new campaign.
     */
    public function create(): Response
    {
        return Inertia::render('admin/campaigns/create');
    }

    /**
     * Store a newly created campaign.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        Campaign::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign created.')]);

        return to_route('admin.campaigns.index');
    }

    /**
     * Show the form for editing the given campaign.
     */
    public function edit(Campaign $campaign): Response
    {
        return Inertia::render('admin/campaigns/edit', [
            'campaign' => $campaign->only(['id', 'name', 'is_active']),
        ]);
    }

    /**
     * Update the given campaign.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign updated.')]);

        return to_route('admin.campaigns.index');
    }

    /**
     * Remove the given campaign and its teams. Their members stay, without
     * a team.
     */
    public function destroy(Campaign $campaign): RedirectResponse
    {
        $campaign->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return to_route('admin.campaigns.index');
    }
}
