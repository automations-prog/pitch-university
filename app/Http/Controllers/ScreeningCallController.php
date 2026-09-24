<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScreeningCallRequest;
use App\Models\ScreeningResponse;
use App\Services\OpenAiRealtimeClient;
use Illuminate\Http\JsonResponse;

class ScreeningCallController extends Controller
{
    /**
     * Mint an ephemeral Realtime API session for the candidate's browser
     * to open a WebRTC connection with.
     */
    public function session(ScreeningResponse $screeningResponse, OpenAiRealtimeClient $client): JsonResponse
    {
        abort_if($screeningResponse->callLog?->called_at !== null, 409);

        return response()->json($client->createEphemeralSession($screeningResponse->screening->voice));
    }

    /**
     * Store the finished call's transcript and recording against the
     * response's call log.
     */
    public function complete(StoreScreeningCallRequest $request, ScreeningResponse $screeningResponse): JsonResponse
    {
        $callLog = $screeningResponse->callLog;

        abort_if($callLog === null || $callLog->called_at !== null, 409);

        $recordingPath = $request->file('recording')->store('call-recordings', 'public');

        $callLog->update([
            'called_at' => now(),
            'transcript' => $request->validated('transcript'),
            'recording_path' => $recordingPath,
        ]);

        return response()->json(['status' => 'ok']);
    }
}
