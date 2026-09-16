<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScreeningResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ScreeningResponseController extends Controller
{
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
