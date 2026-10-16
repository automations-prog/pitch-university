<?php

namespace App\Http\Middleware;

use App\Enums\LicenseStatus;
use App\Enums\Permission;
use App\Models\CallLog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->loadCount([
                    'licenses' => fn ($query) => $query->where('status', LicenseStatus::Active),
                    'courseTracks',
                ]),
                'permissions' => fn (): array => $request->user()?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'impersonating' => $request->session()->has(config('laravel-impersonate.session_key')),
            ],
            'screeningAwaitingReview' => fn (): ?int => $request->user()?->can(Permission::ViewScreening)
                ? CallLog::awaitingReview()->count()
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
