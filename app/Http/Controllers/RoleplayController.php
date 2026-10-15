<?php

namespace App\Http\Controllers;

use App\Enums\RoleplayVertical;
use App\Http\Requests\StoreRoleplaySessionRequest;
use App\Http\Resources\RoleplaySessionResource;
use App\Models\RoleplaySession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoleplayController extends Controller
{
    /**
     * Display the vertical's roleplay page with its script content and the
     * user's recent full calls on it. Training scenes are listed on their
     * module's roleplay page.
     */
    public function index(Request $request, RoleplayVertical $vertical): Response
    {
        $recentSessions = $request->user()->roleplaySessions()
            ->where('vertical', $vertical)
            ->whereNull('course_module_id')
            ->whereNotNull('ended_at')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('roleplay/index', [
            'vertical' => $vertical->toPage(),
            'content' => $vertical->script()->content(),
            'recentSessions' => RoleplaySessionResource::collection($recentSessions),
        ]);
    }

    /**
     * Start a new roleplay session. The persona is built and kept
     * server-side; only its public part goes back to the browser.
     */
    public function store(StoreRoleplaySessionRequest $request, RoleplayVertical $vertical): JsonResponse
    {
        $level = (int) $request->validated('level');
        $generated = $vertical->personaGenerator()->generate($level);

        $session = $request->user()->roleplaySessions()->create([
            'vertical' => $vertical,
            'level' => $level,
            'persona' => $generated['persona'],
            'expected_outcome' => $generated['outcome'],
            'voice' => $generated['voice'],
        ]);

        return response()->json($session->publicPersona(), 201);
    }

    /**
     * Show a finished call's score, transcript and recording. JSON requests
     * get the resource, which the wrap-up polls for the delivery score.
     */
    public function show(Request $request, RoleplaySession $roleplaySession, RoleplayVertical $vertical): Response|RoleplaySessionResource|RedirectResponse
    {
        Gate::authorize('view', $roleplaySession);

        abort_if($roleplaySession->vertical !== $vertical || $roleplaySession->ended_at === null, 404);

        if ($request->wantsJson()) {
            return RoleplaySessionResource::make($roleplaySession);
        }

        // A trainee's scene lives under its training module.
        $module = $roleplaySession->courseModule;

        if ($module !== null && $request->user()->id === $roleplaySession->user_id) {
            return to_route('training.roleplay.attempts.show', [$module->track, $module, $roleplaySession]);
        }

        return Inertia::render('roleplay/sessions/show', [
            'vertical' => $vertical->toPage(),
            'content' => $vertical->script()->content(),
            'session' => RoleplaySessionResource::make($roleplaySession),
            'trail' => $this->trail($request, $roleplaySession),
        ]);
    }

    /**
     * Where the call was opened from, for the breadcrumbs and the back
     * button: an admin reviewing an agent goes back to the agent's
     * training. Null for the trainee's own calls, which belong to the
     * roleplay page.
     *
     * @return list<array{title: string, href: string}>|null
     */
    private function trail(Request $request, RoleplaySession $roleplaySession): ?array
    {
        if ($request->user()->id === $roleplaySession->user_id) {
            return null;
        }

        return [
            ['title' => 'Dashboard', 'href' => route('dashboard')],
            ['title' => 'Training Tracks', 'href' => route('admin.training-tracks.index')],
            ['title' => $roleplaySession->user->name, 'href' => route('admin.training-tracks.show', $roleplaySession->user)],
        ];
    }

    /**
     * Stream a finished call's recording to its owner.
     */
    public function recording(RoleplaySession $roleplaySession, RoleplayVertical $vertical): StreamedResponse
    {
        Gate::authorize('view', $roleplaySession);

        abort_if($roleplaySession->vertical !== $vertical || $roleplaySession->recording_path === null, 404);

        return Storage::disk('local')->response($roleplaySession->recording_path);
    }
}
