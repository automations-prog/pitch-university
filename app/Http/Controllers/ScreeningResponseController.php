<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScreeningResponseRequest;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningResponseController extends Controller
{
    /**
     * Display the public screening form.
     */
    public function show(Screening $screening): Response
    {
        return Inertia::render('screening/show', [
            'token' => $screening->token,
            'alreadySubmitted' => $screening->responses()->exists(),
        ]);
    }

    /**
     * Store a new response for the screening.
     */
    public function store(StoreScreeningResponseRequest $request, Screening $screening): RedirectResponse
    {
        abort_if($screening->responses()->exists(), 409);

        $screening->responses()->create($request->validated());

        return redirect()->route('screening.show', $screening);
    }
}
