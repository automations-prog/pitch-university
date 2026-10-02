<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleplayCallRequest;
use App\Http\Resources\RoleplaySessionResource;
use App\Jobs\GradeRoleplayDelivery;
use App\Models\RoleplaySession;
use App\Services\OpenAiRealtimeClient;
use App\Services\RoleplayConsumerPrompt;
use App\Services\RoleplayGrader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoleplayCallController extends Controller
{
    /**
     * Mint an ephemeral Realtime API session with the consumer's prompt
     * baked in. One mint per session: every mint is a paid Realtime
     * session, so a failed call starts over with a new persona.
     */
    public function session(RoleplaySession $roleplaySession, OpenAiRealtimeClient $client, RoleplayConsumerPrompt $prompt): JsonResponse
    {
        Gate::authorize('update', $roleplaySession);

        $claimed = RoleplaySession::query()
            ->whereKey($roleplaySession->id)
            ->whereNull('started_at')
            ->whereNull('ended_at')
            ->update(['started_at' => now()]);

        abort_if($claimed === 0, 409);

        return response()->json($client->createEphemeralSession(
            $roleplaySession->voice?->value,
            $prompt->sessionConfig($roleplaySession),
        ));
    }

    /**
     * Store the finished call, grade compliance, and reveal the persona.
     * Delivery is scored in the background. The row is locked so two
     * submits of the same call can't both get through.
     */
    public function complete(StoreRoleplayCallRequest $request, RoleplaySession $roleplaySession, RoleplayGrader $grader): RoleplaySessionResource
    {
        $roleplaySession = DB::transaction(fn (): RoleplaySession => $this->store(
            $request,
            RoleplaySession::query()->lockForUpdate()->findOrFail($roleplaySession->id),
            $grader,
        ));

        return RoleplaySessionResource::make($roleplaySession);
    }

    private function store(StoreRoleplayCallRequest $request, RoleplaySession $roleplaySession, RoleplayGrader $grader): RoleplaySession
    {
        abort_if($roleplaySession->started_at === null || $roleplaySession->ended_at !== null, 409);

        $roleplaySession->fill([
            'transcript' => $request->validated('transcript'),
            'events' => $request->events(),
            'recording_path' => $request->file('recording')->store('roleplay-recordings', 'local'),
            'disposition' => $request->validated('disposition'),
            'end_reason' => $request->validated('end_reason'),
            'ended_at' => now(),
        ]);

        $transferClickedAt = $request->validated('transfer_clicked_at');
        $result = $grader->grade($roleplaySession, $transferClickedAt === null ? null : (int) $transferClickedAt);

        $roleplaySession->fill([
            'score' => ['checks' => $result['checks'], 'transfer_clicked_at' => $transferClickedAt],
            'passed' => $result['passed'],
            'delivery_status' => 'pending',
        ])->save();

        GradeRoleplayDelivery::dispatch($roleplaySession)->afterCommit();

        return $roleplaySession;
    }
}
