import { useCallback, useEffect, useRef, useState } from 'react';
import { xsrfHeader } from '@/lib/csrf';
import type { RoleplaySessionResult } from '@/lib/roleplay-data';
import {
    complete as completeCall,
    session as createCallSession,
} from '@/routes/roleplay/call';

/**
 * Drives a live roleplay call: the trainee reads the script, and an AI
 * consumer answers over OpenAI's Realtime API via WebRTC.
 *
 * WebRTC plumbing, recording and the hang-up silence watch are copied from
 * `use-realtime-call.ts` (the screening call); see the notes there. What's
 * different:
 *
 * - The prompt, tools and turn detection are minted into the session
 *   server-side (`RoleplayConsumerPrompt`), so this sends no
 *   `session.update`. The persona's outcome and DQ trap never ship with
 *   the page.
 * - The model reports patience and objections through function calls,
 *   answered with `function_call_output` so it keeps going.
 * - Nothing is uploaded when the call ends. The trainee codes the call
 *   first, then `complete()` uploads everything and returns the grade.
 *
 * IMPORTANT — as with the screening call, verify the event names and the
 * function-calling shapes below against the current OpenAI Realtime docs
 * before relying on this in production.
 */

const REALTIME_CALLS_URL = 'https://api.openai.com/v1/realtime/calls';

const HANGUP_SILENCE_RMS_THRESHOLD = 0.02;
const HANGUP_SILENCE_HOLD_MS = 1_300;
const HANGUP_WATCH_INTERVAL_MS = 100;
const HANGUP_SAFETY_TIMEOUT_MS = 20_000;

/**
 * Delivery scoring (AI_CALL_PLAN §9) needs when each side was talking.
 * The consumer's speech is read off the remote audio level (independent of
 * event names); the agent's from the server VAD's speech_started/stopped.
 */
const LEVEL_WATCH_INTERVAL_MS = 100;
const SPEECH_RMS_THRESHOLD = 0.02;
const CONSUMER_SILENCE_HOLD_MS = 600;
const LOUDNESS_SAMPLE_MS = 1_000;
const MAX_EVENTS = 5_000;

/** Simulated ringing between clicking Transfer and the specialist joining. */
const TRANSFER_RING_MS = 3_000;

const SPECIALIST_LINE = 'Who am I speaking with?';

const SPECIALIST_JOINS_MESSAGE = `(The caller has transferred you. After a little ringing, a licensed Medicare specialist is now on the line and says: "${SPECIALIST_LINE}" Answer the specialist.)`;

const RECORDING_MIME_CANDIDATES: { mimeType: string; extension: string }[] = [
    { mimeType: 'audio/webm;codecs=opus', extension: 'webm' },
    { mimeType: 'audio/webm', extension: 'webm' },
    { mimeType: 'audio/ogg;codecs=opus', extension: 'ogg' },
    { mimeType: 'audio/ogg', extension: 'ogg' },
    { mimeType: 'audio/mp4', extension: 'm4a' },
];

function pickSupportedRecordingType(): {
    mimeType: string;
    extension: string;
} {
    const supported = RECORDING_MIME_CANDIDATES.find(({ mimeType }) =>
        MediaRecorder.isTypeSupported(mimeType),
    );

    if (!supported) {
        throw new Error(
            'This browser does not support any compatible audio recording format.',
        );
    }

    return supported;
}

// Event names have moved between Realtime API versions; match the known
// candidates, same as the screening call.
const ASSISTANT_TRANSCRIPT_EVENTS = new Set([
    'response.audio_transcript.done',
    'response.output_audio_transcript.done',
]);
const AGENT_TRANSCRIPT_EVENTS = new Set([
    'conversation.item.input_audio_transcription.completed',
    'conversation.item.audio_transcription.completed',
]);

export type RoleplayCallPhase =
    | 'idle'
    | 'connecting'
    | 'active'
    | 'transferring'
    | 'ended'
    | 'error';

export type RoleplayEndReason = 'agent' | 'hung_up';

export type LiveTranscriptLine = {
    id: number;
    speaker: 'agent' | 'consumer' | 'system';
    text: string;
    /** Seconds into the call the line was spoken. */
    at: number;
};

type EphemeralSession = { value?: string };

type FunctionCall = { call_id?: string; name?: string; arguments?: string };

/** Matches `RoleplayDeliveryMetrics::EVENT_FIELDS`. Times are ms into the call. */
type CallEvent =
    | { type: 'agent_speech' | 'consumer_speech'; start: number; end: number }
    | {
          type: 'objection_raised' | 'objection_resolved';
          id: string;
          at: number;
      }
    | { type: 'patience_changed'; patience: number; reason: string; at: number }
    | { type: 'transfer_clicked'; at: number }
    | { type: 'agent_loudness'; rms: number; at: number };

function rmsOf(
    analyser: AnalyserNode,
    levels: Uint8Array<ArrayBuffer>,
): number {
    analyser.getByteTimeDomainData(levels);

    let sumSquares = 0;
    for (let i = 0; i < levels.length; i++) {
        const normalized = (levels[i] - 128) / 128;
        sumSquares += normalized * normalized;
    }

    return Math.sqrt(sumSquares / levels.length);
}

function formatOffset(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

function parseArguments(raw: string | undefined): Record<string, unknown> {
    try {
        return JSON.parse(raw ?? '{}') as Record<string, unknown>;
    } catch {
        return {};
    }
}

export function useRoleplayRealtimeCall({
    sessionId,
    startingPatience,
}: {
    sessionId: number;
    startingPatience: number;
}) {
    const [phase, setPhase] = useState<RoleplayCallPhase>('idle');
    const [muted, setMuted] = useState(false);
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const [patience, setPatience] = useState(startingPatience);
    const [openObjections, setOpenObjections] = useState<string[]>([]);
    const [transcript, setTranscript] = useState<LiveTranscriptLine[]>([]);
    const [endReason, setEndReason] = useState<RoleplayEndReason | null>(null);
    const [error, setError] = useState<string | null>(null);

    const peerConnectionRef = useRef<RTCPeerConnection | null>(null);
    const dataChannelRef = useRef<RTCDataChannel | null>(null);
    const localStreamRef = useRef<MediaStream | null>(null);
    const audioContextRef = useRef<AudioContext | null>(null);
    const recorderRef = useRef<MediaRecorder | null>(null);
    const recordedChunksRef = useRef<Blob[]>([]);
    const recordingRef = useRef<Blob | null>(null);
    const remoteAudioElRef = useRef<HTMLAudioElement | null>(null);
    const remoteAnalyserRef = useRef<AnalyserNode | null>(null);
    const wiredRemoteStreamIdRef = useRef<string | null>(null);
    const recordingTypeRef = useRef({
        mimeType: 'audio/webm',
        extension: 'webm',
    });

    const callStartedAtRef = useRef<number | null>(null);
    const elapsedIntervalRef = useRef<ReturnType<typeof setInterval> | null>(
        null,
    );
    const hangupTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const hangupWatchIntervalRef = useRef<ReturnType<
        typeof setInterval
    > | null>(null);
    const transferTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );

    const transcriptRef = useRef<LiveTranscriptLine[]>([]);
    /** When each conversation item started, so lines sort by when they were spoken. */
    const itemStartedAtRef = useRef(new Map<string, number>());
    const transferClickedAtRef = useRef<number | null>(null);
    const awaitingSpecialistReplyRef = useRef(false);
    const pendingEndReasonRef = useRef<RoleplayEndReason | null>(null);
    const callInProgressRef = useRef(false);
    const endingRef = useRef(false);

    const eventsRef = useRef<CallEvent[]>([]);
    const micAnalyserRef = useRef<AnalyserNode | null>(null);
    const levelWatchIntervalRef = useRef<ReturnType<typeof setInterval> | null>(
        null,
    );
    const agentSpeechStartRef = useRef<number | null>(null);
    const consumerSpeechStartRef = useRef<number | null>(null);
    const consumerQuietSinceRef = useRef<number | null>(null);

    const secondsIntoCall = useCallback(
        () =>
            callStartedAtRef.current === null
                ? 0
                : Math.floor((Date.now() - callStartedAtRef.current) / 1000),
        [],
    );

    const msIntoCall = useCallback(
        () =>
            callStartedAtRef.current === null
                ? 0
                : Date.now() - callStartedAtRef.current,
        [],
    );

    const logEvent = useCallback((event: CallEvent) => {
        if (eventsRef.current.length < MAX_EVENTS) {
            eventsRef.current.push(event);
        }
    }, []);

    /** Close any speech segment still open when the call ends. */
    const flushSpeech = useCallback(() => {
        const end = msIntoCall();

        if (agentSpeechStartRef.current !== null) {
            logEvent({
                type: 'agent_speech',
                start: agentSpeechStartRef.current,
                end,
            });
            agentSpeechStartRef.current = null;
        }

        if (consumerSpeechStartRef.current !== null) {
            logEvent({
                type: 'consumer_speech',
                start: consumerSpeechStartRef.current,
                end: consumerQuietSinceRef.current ?? end,
            });
            consumerSpeechStartRef.current = null;
        }
    }, [logEvent, msIntoCall]);

    /**
     * Samples both audio levels: the consumer's to find when they speak,
     * and the trainee's mic while they speak, for the monotone check.
     */
    const startLevelWatch = useCallback(() => {
        const remoteLevels = new Uint8Array(512);
        const micLevels = new Uint8Array(512);
        let loudnessSum = 0;
        let loudnessCount = 0;
        let lastSampleAt = 0;

        levelWatchIntervalRef.current = setInterval(() => {
            const now = msIntoCall();
            const remote = remoteAnalyserRef.current;

            if (remote) {
                const isSpeaking =
                    rmsOf(remote, remoteLevels) > SPEECH_RMS_THRESHOLD;

                if (isSpeaking) {
                    consumerSpeechStartRef.current ??= now;
                    consumerQuietSinceRef.current = null;
                } else if (consumerSpeechStartRef.current !== null) {
                    consumerQuietSinceRef.current ??= now;

                    if (
                        now - consumerQuietSinceRef.current >=
                        CONSUMER_SILENCE_HOLD_MS
                    ) {
                        logEvent({
                            type: 'consumer_speech',
                            start: consumerSpeechStartRef.current,
                            end: consumerQuietSinceRef.current,
                        });
                        consumerSpeechStartRef.current = null;
                        consumerQuietSinceRef.current = null;
                    }
                }
            }

            const mic = micAnalyserRef.current;

            if (mic && agentSpeechStartRef.current !== null) {
                loudnessSum += rmsOf(mic, micLevels);
                loudnessCount++;

                if (now - lastSampleAt >= LOUDNESS_SAMPLE_MS) {
                    logEvent({
                        type: 'agent_loudness',
                        rms: Number((loudnessSum / loudnessCount).toFixed(4)),
                        at: now,
                    });
                    loudnessSum = 0;
                    loudnessCount = 0;
                    lastSampleAt = now;
                }
            }
        }, LEVEL_WATCH_INTERVAL_MS);
    }, [logEvent, msIntoCall]);

    const appendLine = useCallback(
        (speaker: LiveTranscriptLine['speaker'], text: string, at: number) => {
            const line = {
                id: transcriptRef.current.length,
                speaker,
                text,
                at,
            };

            transcriptRef.current = [...transcriptRef.current, line].sort(
                (a, b) => a.at - b.at || a.id - b.id,
            );
            setTranscript(transcriptRef.current);
        },
        [],
    );

    const sendEvent = useCallback((event: Record<string, unknown>) => {
        if (dataChannelRef.current?.readyState === 'open') {
            dataChannelRef.current.send(JSON.stringify(event));
        }
    }, []);

    const cleanup = useCallback(() => {
        for (const timeout of [hangupTimeoutRef, transferTimeoutRef]) {
            if (timeout.current) {
                clearTimeout(timeout.current);
                timeout.current = null;
            }
        }
        for (const interval of [
            elapsedIntervalRef,
            hangupWatchIntervalRef,
            levelWatchIntervalRef,
        ]) {
            if (interval.current) {
                clearInterval(interval.current);
                interval.current = null;
            }
        }

        dataChannelRef.current?.close();
        dataChannelRef.current = null;

        peerConnectionRef.current?.close();
        peerConnectionRef.current = null;

        localStreamRef.current?.getTracks().forEach((track) => track.stop());
        localStreamRef.current = null;

        if (
            audioContextRef.current &&
            audioContextRef.current.state !== 'closed'
        ) {
            void audioContextRef.current.close();
        }
        audioContextRef.current = null;

        remoteAudioElRef.current?.remove();
        remoteAudioElRef.current = null;

        callInProgressRef.current = false;
        wiredRemoteStreamIdRef.current = null;
        remoteAnalyserRef.current = null;
        micAnalyserRef.current = null;
    }, []);

    /**
     * Stops the call and keeps the recording in memory until the trainee
     * codes the call.
     */
    const finishCall = useCallback(
        (reason: RoleplayEndReason) => {
            if (endingRef.current) {
                return;
            }
            endingRef.current = true;

            flushSpeech();

            const finish = () => {
                recordingRef.current = new Blob(recordedChunksRef.current, {
                    type: recordingTypeRef.current.mimeType,
                });
                cleanup();
                setEndReason(reason);
                setPhase('ended');
            };

            const recorder = recorderRef.current;

            if (recorder && recorder.state !== 'inactive') {
                recorder.onstop = finish;
                recorder.stop();
            } else {
                finish();
            }
        },
        [cleanup, flushSpeech],
    );

    /**
     * Ends the call once the consumer's current line has actually finished
     * playing, by watching the remote track's audio level. See
     * `use-realtime-call.ts` for why a fixed delay doesn't work.
     */
    const endAfterConsumerFinishes = useCallback(
        (reason: RoleplayEndReason) => {
            if (hangupWatchIntervalRef.current || hangupTimeoutRef.current) {
                return;
            }

            const analyser = remoteAnalyserRef.current;

            hangupTimeoutRef.current = setTimeout(
                () => finishCall(reason),
                HANGUP_SAFETY_TIMEOUT_MS,
            );

            if (!analyser) {
                return;
            }

            const levels = new Uint8Array(analyser.fftSize);
            let hasHeardSpeech = false;
            let silenceStartedAt: number | null = null;

            hangupWatchIntervalRef.current = setInterval(() => {
                if (rmsOf(analyser, levels) > HANGUP_SILENCE_RMS_THRESHOLD) {
                    hasHeardSpeech = true;
                    silenceStartedAt = null;
                    return;
                }

                if (!hasHeardSpeech) {
                    return;
                }

                silenceStartedAt ??= Date.now();

                if (Date.now() - silenceStartedAt >= HANGUP_SILENCE_HOLD_MS) {
                    finishCall(reason);
                }
            }, HANGUP_WATCH_INTERVAL_MS);
        },
        [finishCall],
    );

    const handleFunctionCall = useCallback(
        (call: FunctionCall) => {
            const args = parseArguments(call.arguments);
            const at = secondsIntoCall();
            const atMs = msIntoCall();

            switch (call.name) {
                case 'patience_changed': {
                    const next = Math.max(
                        0,
                        Math.min(startingPatience, Number(args.patience)),
                    );

                    if (!Number.isNaN(next)) {
                        setPatience(next);
                        logEvent({
                            type: 'patience_changed',
                            patience: next,
                            reason:
                                typeof args.reason === 'string'
                                    ? args.reason
                                    : '',
                            at: atMs,
                        });
                        appendLine(
                            'system',
                            `Patience ${next}/${startingPatience}${typeof args.reason === 'string' && args.reason ? ` · ${args.reason}` : ''}`,
                            at,
                        );
                    }
                    break;
                }
                case 'objection_raised':
                    logEvent({
                        type: 'objection_raised',
                        id: String(args.id),
                        at: atMs,
                    });
                    setOpenObjections((current) =>
                        current.includes(String(args.id))
                            ? current
                            : [...current, String(args.id)],
                    );
                    break;
                case 'objection_resolved':
                    logEvent({
                        type: 'objection_resolved',
                        id: String(args.id),
                        at: atMs,
                    });
                    setOpenObjections((current) =>
                        current.filter((id) => id !== String(args.id)),
                    );
                    break;
                case 'hang_up':
                    setPatience(0);
                    pendingEndReasonRef.current = 'hung_up';
                    appendLine('system', 'The consumer hung up.', at);
                    endAfterConsumerFinishes('hung_up');
                    break;
            }

            if (call.call_id) {
                sendEvent({
                    type: 'conversation.item.create',
                    item: {
                        type: 'function_call_output',
                        call_id: call.call_id,
                        output: JSON.stringify({ ok: true }),
                    },
                });
            }
        },
        [
            appendLine,
            endAfterConsumerFinishes,
            logEvent,
            msIntoCall,
            secondsIntoCall,
            sendEvent,
            startingPatience,
        ],
    );

    const handleServerEvent = useCallback(
        (event: Record<string, unknown>) => {
            const type = event.type as string | undefined;

            if (import.meta.env.DEV) {
                // eslint-disable-next-line no-console
                console.debug('[roleplay-call]', type, event);
            }

            if (type === 'error') {
                // eslint-disable-next-line no-console
                console.error('[roleplay-call] server error', event);
                return;
            }

            if (type === 'input_audio_buffer.speech_started') {
                agentSpeechStartRef.current ??= msIntoCall();
                return;
            }

            if (type === 'input_audio_buffer.speech_stopped') {
                if (agentSpeechStartRef.current !== null) {
                    logEvent({
                        type: 'agent_speech',
                        start: agentSpeechStartRef.current,
                        end: msIntoCall(),
                    });
                    agentSpeechStartRef.current = null;
                }
                return;
            }

            const itemId =
                typeof event.item_id === 'string' ? event.item_id : null;

            if (type === 'input_audio_buffer.committed' && itemId) {
                itemStartedAtRef.current.set(itemId, secondsIntoCall());
                return;
            }

            if (type === 'response.output_item.added') {
                const item = event.item as { id?: string } | undefined;

                if (item?.id) {
                    itemStartedAtRef.current.set(item.id, secondsIntoCall());
                }
                return;
            }

            if (type === 'response.function_call_arguments.done') {
                handleFunctionCall(event as FunctionCall);
                return;
            }

            if (type === 'response.done') {
                const output =
                    ((event.response as { output?: { type?: string }[] })
                        ?.output as { type?: string }[] | undefined) ?? [];
                const calledTools = output.some(
                    (item) => item.type === 'function_call',
                );
                const spoke = output.some((item) => item.type === 'message');

                // A tool-only response leaves the model waiting on our
                // outputs; ask it to carry on unless it just hung up.
                if (calledTools && !spoke && !pendingEndReasonRef.current) {
                    sendEvent({ type: 'response.create' });
                }
                return;
            }

            const text = (event.transcript as string | undefined)?.trim();

            if (!type || !text) {
                return;
            }

            const at =
                (itemId === null
                    ? undefined
                    : itemStartedAtRef.current.get(itemId)) ??
                secondsIntoCall();

            if (ASSISTANT_TRANSCRIPT_EVENTS.has(type)) {
                appendLine('consumer', text, at);

                if (awaitingSpecialistReplyRef.current) {
                    awaitingSpecialistReplyRef.current = false;
                    endAfterConsumerFinishes('agent');
                }
                return;
            }

            if (AGENT_TRANSCRIPT_EVENTS.has(type)) {
                appendLine('agent', text, at);
            }
        },
        [
            appendLine,
            endAfterConsumerFinishes,
            handleFunctionCall,
            logEvent,
            msIntoCall,
            secondsIntoCall,
            sendEvent,
        ],
    );

    const toggleMute = useCallback(() => {
        const stream = localStreamRef.current;

        if (!stream) {
            return;
        }

        setMuted((current) => {
            const next = !current;
            stream.getAudioTracks().forEach((track) => {
                track.enabled = !next;
            });

            return next;
        });
    }, []);

    const endCall = useCallback(() => finishCall('agent'), [finishCall]);

    /**
     * Cold transfer: the trainee goes quiet, it rings, then a specialist
     * asks who they're speaking with. The call ends once the consumer
     * answers. When Transfer was clicked is sent with the call so grading
     * can check the consumer said yes first.
     */
    const transfer = useCallback(() => {
        if (phase !== 'active') {
            return;
        }

        const at = secondsIntoCall();
        transferClickedAtRef.current = at;
        logEvent({ type: 'transfer_clicked', at: msIntoCall() });
        setPhase('transferring');
        appendLine('system', 'Transferring… ringing the specialist.', at);

        localStreamRef.current?.getAudioTracks().forEach((track) => {
            track.enabled = false;
        });
        setMuted(true);

        transferTimeoutRef.current = setTimeout(() => {
            appendLine(
                'system',
                `Specialist: “${SPECIALIST_LINE}”`,
                secondsIntoCall(),
            );
            awaitingSpecialistReplyRef.current = true;
            sendEvent({ type: 'response.cancel' });
            sendEvent({
                type: 'conversation.item.create',
                item: {
                    type: 'message',
                    role: 'user',
                    content: [
                        { type: 'input_text', text: SPECIALIST_JOINS_MESSAGE },
                    ],
                },
            });
            sendEvent({ type: 'response.create' });
        }, TRANSFER_RING_MS);
    }, [appendLine, logEvent, msIntoCall, phase, secondsIntoCall, sendEvent]);

    const start = useCallback(async () => {
        if (callInProgressRef.current) {
            return;
        }
        callInProgressRef.current = true;
        endingRef.current = false;
        setPhase('connecting');
        setError(null);

        try {
            // Mic first: the permission prompt can take a while, and the
            // ephemeral key is short-lived.
            const localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            localStreamRef.current = localStream;

            const sessionResponse = await fetch(
                createCallSession.url(sessionId),
                {
                    method: 'POST',
                    headers: { Accept: 'application/json', ...xsrfHeader() },
                    credentials: 'same-origin',
                },
            );

            if (!sessionResponse.ok) {
                throw new Error(
                    sessionResponse.status === 409
                        ? 'This call was already started. Start a new call.'
                        : `Could not start the call (HTTP ${sessionResponse.status}).`,
                );
            }

            const ephemeralKey = (
                (await sessionResponse.json()) as EphemeralSession
            ).value;

            if (!ephemeralKey) {
                throw new Error('Missing realtime session credentials.');
            }

            const pc = new RTCPeerConnection();
            peerConnectionRef.current = pc;
            localStream
                .getTracks()
                .forEach((track) => pc.addTrack(track, localStream));

            const audioContext = new AudioContext();
            audioContextRef.current = audioContext;
            const destination = audioContext.createMediaStreamDestination();
            const micSource = audioContext.createMediaStreamSource(localStream);
            micSource.connect(destination);

            const micAnalyser = audioContext.createAnalyser();
            micAnalyser.fftSize = 512;
            micSource.connect(micAnalyser);
            micAnalyserRef.current = micAnalyser;

            const remoteAudio = document.createElement('audio');
            remoteAudio.autoplay = true;
            remoteAudioElRef.current = remoteAudio;

            pc.ontrack = (trackEvent) => {
                const remoteStream = trackEvent.streams[0];
                remoteAudio.srcObject = remoteStream;

                if (wiredRemoteStreamIdRef.current === remoteStream.id) {
                    return;
                }
                wiredRemoteStreamIdRef.current = remoteStream.id;

                const remoteSource =
                    audioContext.createMediaStreamSource(remoteStream);
                remoteSource.connect(destination);

                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 512;
                remoteSource.connect(analyser);
                remoteAnalyserRef.current = analyser;
            };

            recordingTypeRef.current = pickSupportedRecordingType();
            const recorder = new MediaRecorder(destination.stream, {
                mimeType: recordingTypeRef.current.mimeType,
            });
            recordedChunksRef.current = [];
            recorder.ondataavailable = (dataEvent) => {
                if (dataEvent.data.size > 0) {
                    recordedChunksRef.current.push(dataEvent.data);
                }
            };
            recorderRef.current = recorder;

            const dataChannel = pc.createDataChannel('oai-events');
            dataChannelRef.current = dataChannel;

            dataChannel.addEventListener('open', () => {
                // The consumer picks up the phone ("Hello?").
                sendEvent({ type: 'response.create' });

                recorder.start(1000);
                callStartedAtRef.current = Date.now();
                eventsRef.current = [];
                startLevelWatch();
                setPhase('active');
                elapsedIntervalRef.current = setInterval(
                    () => setElapsedSeconds(secondsIntoCall()),
                    1000,
                );
            });

            dataChannel.addEventListener('message', (messageEvent) => {
                try {
                    handleServerEvent(JSON.parse(messageEvent.data as string));
                } catch {
                    // Ignore malformed/unrecognized events.
                }
            });

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);

            const sdpResponse = await fetch(REALTIME_CALLS_URL, {
                method: 'POST',
                body: offer.sdp,
                headers: {
                    Authorization: `Bearer ${ephemeralKey}`,
                    'Content-Type': 'application/sdp',
                },
            });

            if (!sdpResponse.ok) {
                throw new Error(
                    `Could not connect to the call (HTTP ${sdpResponse.status}).`,
                );
            }

            await pc.setRemoteDescription({
                type: 'answer',
                sdp: await sdpResponse.text(),
            });
        } catch (caught) {
            cleanup();
            setError(
                caught instanceof Error
                    ? caught.message
                    : 'Could not start the call.',
            );
            setPhase('error');
        }
    }, [
        cleanup,
        handleServerEvent,
        secondsIntoCall,
        sendEvent,
        sessionId,
        startLevelWatch,
    ]);

    /**
     * Uploads the coded call and returns the server's grade.
     */
    const complete = useCallback(
        async (disposition: string): Promise<RoleplaySessionResult> => {
            const formData = new FormData();
            formData.append(
                'transcript',
                transcriptRef.current
                    .filter((line) => line.speaker !== 'system')
                    .map(
                        (line) =>
                            `[${formatOffset(line.at)}] ${line.speaker}: ${line.text}`,
                    )
                    .join('\n'),
            );
            formData.append(
                'recording',
                recordingRef.current ?? new Blob(),
                `call.${recordingTypeRef.current.extension}`,
            );
            formData.append('disposition', disposition);
            formData.append('end_reason', endReason ?? 'agent');
            formData.append('events', JSON.stringify(eventsRef.current));

            if (transferClickedAtRef.current !== null) {
                formData.append(
                    'transfer_clicked_at',
                    String(transferClickedAtRef.current),
                );
            }

            const response = await fetch(completeCall.url(sessionId), {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json', ...xsrfHeader() },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(
                    `Could not save the call (HTTP ${response.status}).`,
                );
            }

            return (await response.json()) as RoleplaySessionResult;
        },
        [endReason, sessionId],
    );

    useEffect(() => cleanup, [cleanup]);

    return {
        phase,
        muted,
        elapsedSeconds,
        patience,
        openObjections,
        transcript,
        endReason,
        error,
        start,
        toggleMute,
        endCall,
        transfer,
        complete,
    };
}
