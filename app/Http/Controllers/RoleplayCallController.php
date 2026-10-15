<?php

namespace App\Http\Controllers;

use App\Enums\RoleplayVertical;
use App\Http\Requests\StoreRoleplayCallRequest;
use App\Http\Resources\RoleplaySessionResource;
use App\Jobs\GradeRoleplayDelivery;
use App\Jobs\JudgeRoleplayScene;
use App\Models\RoleplaySession;
use App\Services\ElevenLabsClient;
use App\Services\OpenAiRealtimeClient;
use App\Services\RoleplaySceneGrader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoleplayCallController extends Controller
{
    /**
     * Mint an ephemeral Realtime API session with the consumer's prompt
     * baked in. One mint per session: every mint is a paid Realtime
     * session, so a failed call starts over with a new persona.
     *
     * With an ElevenLabs voice, OpenAI replies in text and the page speaks
     * it through ElevenLabs, with the token and voice under `tts`.
     */
    public function session(RoleplaySession $roleplaySession, RoleplayVertical $vertical, OpenAiRealtimeClient $client, ElevenLabsClient $elevenLabs): JsonResponse
    {
        Gate::authorize('update', $roleplaySession);

        abort_if($roleplaySession->vertical !== $vertical, 404);

        $claimed = RoleplaySession::query()
            ->whereKey($roleplaySession->id)
            ->whereNull('started_at')
            ->whereNull('ended_at')
            ->update(['started_at' => now()]);

        abort_if($claimed === 0, 409);

        $elevenLabsVoice = $elevenLabs->voiceFor($roleplaySession);

        $session = $client->createEphemeralSession(
            $roleplaySession->consumerVoice()?->value,
            $vertical->consumerPrompt()->sessionConfig($roleplaySession, speaksAsText: $elevenLabsVoice !== null),
        );

        if ($elevenLabsVoice !== null) {
            $session['tts'] = [
                'token' => $elevenLabs->createTtsToken(),
                'voice_id' => $elevenLabsVoice,
                'model_id' => config('services.elevenlabs.model'),
            ];
        }

        return response()->json($session);
    }

    /**
     * Store the finished call, grade compliance, and reveal the persona.
     * Delivery is scored in the background. The row is locked so two
     * submits of the same call can't both get through.
     */
    public function complete(StoreRoleplayCallRequest $request, RoleplaySession $roleplaySession, RoleplaySceneGrader $sceneGrader): RoleplaySessionResource
    {
        $roleplaySession = DB::transaction(fn (): RoleplaySession => $this->store(
            $request,
            RoleplaySession::query()->lockForUpdate()->findOrFail($roleplaySession->id),
            $sceneGrader,
        ));

        return RoleplaySessionResource::make($roleplaySession);
    }

    private function store(StoreRoleplayCallRequest $request, RoleplaySession $roleplaySession, RoleplaySceneGrader $sceneGrader): RoleplaySession
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
        $transferClickedAt = $transferClickedAt === null ? null : (int) $transferClickedAt;

        if ($roleplaySession->isScene()) {
            return $this->storeScene($roleplaySession, $transferClickedAt, $sceneGrader);
        }

        $result = $roleplaySession->vertical->grader()->grade($roleplaySession, $transferClickedAt);

        $roleplaySession->fill([
            'score' => [
                'checks' => $result['checks'],
                'transfer_clicked_at' => $transferClickedAt,
                'correct_disposition' => $result['correct_disposition'],
                'correct_reason' => $result['correct_reason'],
            ],
            'passed' => $result['passed'],
            'delivery_status' => 'pending',
        ])->save();

        GradeRoleplayDelivery::dispatch($roleplaySession)->afterCommit();

        return $roleplaySession;
    }

    /**
     * Grade a training scene's word-matched checks now and leave the
     * AI-judged ones to the background. A minute-long scene gets no
     * delivery review.
     */
    private function storeScene(RoleplaySession $roleplaySession, ?int $transferClickedAt, RoleplaySceneGrader $sceneGrader): RoleplaySession
    {
        $result = $sceneGrader->grade($roleplaySession, $transferClickedAt);

        $roleplaySession->fill([
            'score' => [
                'checks' => $result['checks'],
                'transfer_clicked_at' => $transferClickedAt,
                'correct_disposition' => $result['correct_disposition'],
                'correct_reason' => $result['correct_reason'],
            ],
            'passed' => $result['passed'],
        ])->save();

        if ($result['passed'] === null) {
            JudgeRoleplayScene::dispatch($roleplaySession)->afterCommit();
        }

        return $roleplaySession;
    }
}
