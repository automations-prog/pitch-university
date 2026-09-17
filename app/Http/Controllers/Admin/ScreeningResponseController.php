<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CallRating;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScreeningResponseResource;
use App\Models\ScreeningResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningResponseController extends Controller
{
    /**
     * Display the given screening response.
     */
    public function show(ScreeningResponse $screeningResponse): Response
    {
        Gate::authorize('view', $screeningResponse);

        return Inertia::render('admin/screening-responses/show', [
            'response' => ScreeningResponseResource::make($screeningResponse->load(['screening', 'callLog'])),
            'ratingOptions' => CallRating::cases(),
        ]);
    }

    /**
     * Remove a screening response.
     */
    public function destroy(ScreeningResponse $screeningResponse): RedirectResponse
    {
        Gate::authorize('delete', $screeningResponse);

        $screeningResponse->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Response deleted.')]);

        return to_route('admin.screening.index');
    }

    /**
     * Remove multiple screening responses at once.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('screening_responses', 'id')],
        ]);

        $responses = ScreeningResponse::whereIn('id', $validated['ids'])->get();

        foreach ($responses as $response) {
            Gate::authorize('delete', $response);
        }

        ScreeningResponse::whereIn('id', $responses->pluck('id'))->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count response deleted.|:count responses deleted.', $responses->count(), ['count' => $responses->count()])]);

        return to_route('admin.screening.index');
    }
}
