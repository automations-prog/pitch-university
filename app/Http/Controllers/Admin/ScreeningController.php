<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScreeningRequest;
use App\Http\Resources\ScreeningResource;
use App\Http\Resources\ScreeningResponseResource;
use App\Models\Screening;
use App\Models\ScreeningResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningController extends Controller
{
    /**
     * The selectable page sizes for the screening index.
     *
     * @var array<int, int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * Display a listing of the screenings.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Screening::class);

        $perPage = $request->integer('per_page', self::PER_PAGE_OPTIONS[0]);
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];

        $responses = ScreeningResponse::with('screening')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $paginated = ScreeningResponseResource::collection($responses)->response()->getData(true);

        return Inertia::render('admin/screening/index', [
            'responses' => [
                'data' => $paginated['data'],
                'links' => $paginated['meta']['links'],
                'meta' => Arr::except($paginated['meta'], ['links']),
            ],
            'filters' => [
                'per_page' => (string) $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
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
