<?php

namespace App\Http\Controllers;

use App\Enums\RealtimeVoice;
use App\Http\Requests\StoreRoleplaySessionRequest;
use App\Http\Resources\RoleplaySessionResource;
use App\Models\RoleplaySession;
use App\Services\RoleplayPersonaGenerator;
use App\Services\RoleplayScript;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoleplayController extends Controller
{
    /**
     * Display the roleplay page with the script content and the user's
     * recent calls.
     */
    public function index(Request $request, RoleplayScript $script): Response
    {
        $recentSessions = $request->user()->roleplaySessions()
            ->whereNotNull('ended_at')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('roleplay/index', [
            'content' => $script->content(),
            'recentSessions' => RoleplaySessionResource::collection($recentSessions),
        ]);
    }

    /**
     * Start a new roleplay session. The persona is built and kept
     * server-side; only its public part goes back to the browser.
     */
    public function store(StoreRoleplaySessionRequest $request, RoleplayPersonaGenerator $generator): JsonResponse
    {
        $level = (int) $request->validated('level');
        $generated = $generator->generate($level);
        $voices = RealtimeVoice::cases();

        $session = $request->user()->roleplaySessions()->create([
            'level' => $level,
            'persona' => $generated['persona'],
            'expected_outcome' => $generated['outcome'],
            'voice' => $voices[array_rand($voices)],
        ]);

        return response()->json($session->publicPersona(), 201);
    }

    /**
     * Show a finished call's score, transcript and recording. JSON requests
     * get the resource, which the wrap-up polls for the delivery score.
     */
    public function show(Request $request, RoleplaySession $roleplaySession, RoleplayScript $script): Response|RoleplaySessionResource
    {
        Gate::authorize('view', $roleplaySession);

        abort_if($roleplaySession->ended_at === null, 404);

        if ($request->wantsJson()) {
            return RoleplaySessionResource::make($roleplaySession);
        }

        return Inertia::render('roleplay/sessions/show', [
            'content' => $script->content(),
            'session' => RoleplaySessionResource::make($roleplaySession),
        ]);
    }

    /**
     * Stream a finished call's recording to its owner.
     */
    public function recording(RoleplaySession $roleplaySession): StreamedResponse
    {
        Gate::authorize('view', $roleplaySession);

        abort_if($roleplaySession->recording_path === null, 404);

        return Storage::disk('local')->response($roleplaySession->recording_path);
    }
}
