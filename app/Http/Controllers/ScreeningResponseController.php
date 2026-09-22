<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScreeningResponseRequest;
use App\Models\Screening;
use App\Models\ScreeningResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
            'responseToken' => session('responseToken'),
        ]);
    }

    /**
     * Store a new response for the screening.
     */
    public function store(StoreScreeningResponseRequest $request, Screening $screening): RedirectResponse
    {
        do {
            $token = Str::random(40);
        } while (ScreeningResponse::where('token', $token)->exists());

        $screening->responses()->create([
            ...$request->validated(),
            'token' => $token,
        ]);

        return redirect()->route('screening.show', $screening)->with('responseToken', $token);
    }
}
