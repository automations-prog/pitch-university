<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerticalTrainingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVerticalTrainingRequest;
use App\Http\Requests\Admin\UpdateVerticalTrainingRequest;
use App\Http\Resources\VerticalTrainingResource;
use App\Models\VerticalTraining;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VerticalTrainingController extends Controller
{
    /**
     * The selectable page sizes for the vertical training index.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display a listing of the vertical training programs.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', VerticalTraining::class);

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $trainings = VerticalTraining::query()
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $paginated = VerticalTrainingResource::collection($trainings)->response()->getData(true);

        return Inertia::render('admin/vertical-training/index', [
            'trainings' => [
                'data' => $paginated['data'],
                'links' => $paginated['meta']['links'],
                'meta' => Arr::except($paginated['meta'], ['links']),
            ],
            'filters' => [
                ...$request->only(['search', 'status']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses' => VerticalTrainingStatus::cases(),
        ]);
    }

    /**
     * Show the form for creating a new vertical training program.
     */
    public function create(): Response
    {
        Gate::authorize('create', VerticalTraining::class);

        return Inertia::render('admin/vertical-training/create', [
            'statuses' => VerticalTrainingStatus::cases(),
        ]);
    }

    /**
     * Store a newly created vertical training program.
     */
    public function store(StoreVerticalTrainingRequest $request): RedirectResponse
    {
        VerticalTraining::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vertical training created.')]);

        return to_route('admin.vertical-training.index');
    }

    /**
     * Show the form for editing the given vertical training program.
     */
    public function edit(VerticalTraining $vertical_training): Response
    {
        Gate::authorize('update', $vertical_training);

        return Inertia::render('admin/vertical-training/edit', [
            'training' => VerticalTrainingResource::make($vertical_training),
            'statuses' => VerticalTrainingStatus::cases(),
        ]);
    }

    /**
     * Update the given vertical training program.
     */
    public function update(UpdateVerticalTrainingRequest $request, VerticalTraining $vertical_training): RedirectResponse
    {
        $vertical_training->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vertical training updated.')]);

        return to_route('admin.vertical-training.index');
    }

    /**
     * Remove the given vertical training program.
     */
    public function destroy(VerticalTraining $vertical_training): RedirectResponse
    {
        Gate::authorize('delete', $vertical_training);

        $vertical_training->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vertical training deleted.')]);

        return to_route('admin.vertical-training.index');
    }
}
