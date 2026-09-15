<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\LicenseResource;
use App\Http\Resources\UserResource;
use App\Jobs\PullAgentsFromCustomerApiJob;
use App\Models\License;
use App\Models\User;
use App\Support\PullProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * The selectable page sizes for the users index.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $users = User::query()
            ->with('licenses')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('role')->toString(), fn ($query, string $role) => $query->where('role', $role))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $paginated = UserResource::collection($users)->response()->getData(true);

        return Inertia::render('admin/users/index', [
            'users' => [
                'data' => $paginated['data'],
                'links' => $paginated['meta']['links'],
                'meta' => Arr::except($paginated['meta'], ['links']),
            ],
            'filters' => [
                ...$request->only(['search', 'role', 'status']),
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
            'licenses' => LicenseResource::collection(License::query()->where('status', LicenseStatus::Active)->orderBy('name')->get()),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', [
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
            'licenses' => LicenseResource::collection(License::query()->where('status', LicenseStatus::Active)->orderBy('name')->get()),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password', 'role', 'status']));

        $user->licenses()->sync($request->validated('license_ids') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    /**
     * Show the form for editing the given user.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/edit', [
            'user' => UserResource::make($user),
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
            'licenses' => LicenseResource::collection($user->licenses),
            'availableLicenses' => LicenseResource::collection(License::query()->where('status', LicenseStatus::Active)->orderBy('name')->get()),
        ]);
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->except(['password']));

        if ($password = $request->validated('password')) {
            $user->password = $password;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * Remove the given user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }

    /**
     * Remove multiple users at once.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $users = User::whereIn('id', $validated['ids'])->get();

        foreach ($users as $user) {
            Gate::authorize('delete', $user);
        }

        User::whereIn('id', $users->pluck('id'))->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count user deleted.|:count users deleted.', $users->count(), ['count' => $users->count()])]);

        return to_route('admin.users.index');
    }

    /**
     * Assign a license to multiple users at once.
     */
    public function bulkAssignLicense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'license_id' => ['required', 'integer', Rule::exists('licenses', 'id')->where('status', LicenseStatus::Active->value)],
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();

        foreach ($users as $user) {
            Gate::authorize('update', $user);
        }

        $license = License::findOrFail($validated['license_id']);

        foreach ($users as $user) {
            $user->licenses()->syncWithoutDetaching([$license->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            ':license assigned to :count user.|:license assigned to :count users.',
            $users->count(),
            ['license' => $license->name, 'count' => $users->count()]
        )]);

        return to_route('admin.users.index');
    }

    /**
     * Dispatch a job that pulls the student roster from the Customer API.
     */
    public function pullAgents(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $progressId = $request->string('progress_id')->toString() ?: (string) Str::uuid();

        PullProgress::start($progressId);

        PullAgentsFromCustomerApiJob::dispatch($request->user(), $progressId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agent pull started.')]);

        return back();
    }

    /**
     * Report the progress of a "Pull agents" run for the frontend to poll.
     */
    public function pullStatus(Request $request, string $progressId): JsonResponse
    {
        Gate::authorize('create', User::class);

        return response()->json(PullProgress::status($progressId));
    }

    /**
     * Start impersonating the given user.
     */
    /**
     * Forces a full-page reload instead of a client-side Inertia visit, so
     * any pages prefetched as the admin (which carry the admin's auth
     * props) are discarded rather than served stale once impersonating.
     */
    public function impersonate(Request $request, User $user): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('impersonate', $user);

        $request->user()->impersonate($user);

        return Inertia::location(route('dashboard'));
    }
}
