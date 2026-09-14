<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLicenseRequest;
use App\Http\Requests\Admin\UpdateLicenseRequest;
use App\Http\Resources\LicenseResource;
use App\Http\Resources\LicenseStepResource;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    /**
     * The selectable page sizes for the licensing index.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display a listing of the licenses.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', License::class);

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $licenses = License::query()
            ->when($request->string('search')->toString(), fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $paginated = LicenseResource::collection($licenses)->response()->getData(true);

        return Inertia::render('admin/licensing/index', [
            'licenses' => [
                'data' => $paginated['data'],
                'links' => $paginated['meta']['links'],
                'meta' => Arr::except($paginated['meta'], ['links']),
            ],
            'filters' => [
                ...$request->only(['search', 'status']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses' => LicenseStatus::cases(),
        ]);
    }

    /**
     * Show the form for creating a new license.
     */
    public function create(): Response
    {
        Gate::authorize('create', License::class);

        return Inertia::render('admin/licensing/create', [
            'statuses' => LicenseStatus::cases(),
        ]);
    }

    /**
     * Store a newly created license.
     */
    public function store(StoreLicenseRequest $request): RedirectResponse
    {
        License::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('License created.')]);

        return to_route('admin.licensing.index');
    }

    /**
     * Show the form for editing the given license.
     */
    public function edit(License $licensing): Response
    {
        Gate::authorize('update', $licensing);

        return Inertia::render('admin/licensing/edit', [
            'license' => LicenseResource::make($licensing),
            'steps' => LicenseStepResource::collection($licensing->steps),
            'statuses' => LicenseStatus::cases(),
        ]);
    }

    /**
     * Update the given license.
     */
    public function update(UpdateLicenseRequest $request, License $licensing): RedirectResponse
    {
        $licensing->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('License updated.')]);

        return to_route('admin.licensing.index');
    }

    /**
     * Remove the given license.
     */
    public function destroy(License $licensing): RedirectResponse
    {
        Gate::authorize('delete', $licensing);

        $licensing->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('License deleted.')]);

        return to_route('admin.licensing.index');
    }
}
