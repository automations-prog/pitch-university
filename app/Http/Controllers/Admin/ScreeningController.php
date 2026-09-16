<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScreeningRequest;
use App\Http\Resources\ScreeningResource;
use App\Http\Resources\ScreeningResponseResource;
use App\Models\Screening;
use App\Models\ScreeningResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningController extends Controller
{
    /**
     * Display a listing of the screenings.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Screening::class);

        $responses = ScreeningResponse::with('screening')->latest()->get();

        return Inertia::render('admin/screening/index', [
            'responses' => ScreeningResponseResource::collection($responses),
        ]);
    }

    /**
     * Generate a new screening and its public link.
     */
    public function store(StoreScreeningRequest $request): JsonResponse
    {
        do {
            $token = Str::random(40);
        } while (Screening::where('token', $token)->exists());

        $screening = Screening::create(['token' => $token]);
        $screening->loadCount('responses');

        return response()->json([
            'screening' => ScreeningResource::make($screening),
        ]);
    }

    /**
     * Display the responses collected for a screening.
     */
    public function show(Screening $screening): Response
    {
        Gate::authorize('view', $screening);

        return Inertia::render('admin/screening/show', [
            'screening' => ScreeningResource::make($screening),
            'responses' => ScreeningResponseResource::collection(
                $screening->responses()->latest()->get()
            ),
        ]);
    }
}
