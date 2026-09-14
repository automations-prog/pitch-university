<?php

namespace App\Http\Controllers;

use App\Enums\LicenseStatus;
use App\Http\Resources\LicenseResource;
use App\Http\Resources\LicenseStepResource;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    /**
     * Display the current user's licenses.
     */
    public function index(Request $request): Response
    {
        $licenses = $request->user()->licenses()
            ->where('status', LicenseStatus::Active)
            ->get();

        return Inertia::render('licenses/index', [
            'licenses' => LicenseResource::collection($licenses),
        ]);
    }

    /**
     * Display the given license's instruction steps.
     */
    public function show(License $license): Response
    {
        Gate::authorize('view', $license);

        return Inertia::render('licenses/show', [
            'license' => LicenseResource::make($license),
            'steps' => LicenseStepResource::collection($license->steps),
        ]);
    }
}
